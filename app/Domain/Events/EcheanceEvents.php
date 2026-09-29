<?php

namespace App\Domain\Events;

use App\Domain\Fleet\MaintenanceStatus;
use App\Domain\Fleet\VehicleDisinfection;
use App\Domain\Support\Severity;
use App\Models\Document;
use App\Models\Event;
use App\Models\KanbanBoard;
use App\Models\KanbanColumn;
use App\Models\Organisation;
use App\Models\StockLot;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;

/**
 * Génère (et clôture) les événements d'échéance d'une organisation à partir des
 * statuts calculés : entretien (temporel + km), désinfection, péremption des
 * lots de matériel, expiration des documents (carte grise, agrément, CT…).
 *
 * Idempotent : un seul événement ouvert par échéance (clé de source stable). Les
 * échéances levées voient leur événement automatiquement résolu.
 *
 * Le contexte tenant doit être positionné avant l'appel (cloisonnement des
 * lectures/écritures).
 */
class EcheanceEvents
{
    /** @return array{created:int,closed:int} */
    public static function generate(Organisation $organisation, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $entryColumn = KanbanBoard::entryColumnId($organisation);

        /** @var array<string, array<string, mixed>> $relevant  source_key => attrs */
        $relevant = [];

        // --- Entretien (temporel + kilométrique) ---
        $vehicles = Vehicle::query()->get();
        $alertDays = $organisation->maintenanceAlertDays();
        $alertKm = $organisation->maintenanceAlertKm();

        foreach ($vehicles as $vehicle) {
            $status = MaintenanceStatus::forVehicleRecords(
                $vehicle->maintenances()->get(),
                $vehicle->mileage !== null ? (int) $vehicle->mileage : null,
                $now,
                $alertDays,
                $alertKm,
            );
            if ($status->severity !== null) {
                $due = $status->dueAt?->format('d/m/Y');
                $dueKm = $status->dueMileage !== null ? number_format((int) $status->dueMileage, 0, ',', ' ').' km' : null;
                $detail = collect([$due, $dueKm])->filter()->implode(' · ');
                $relevant['ech:maintenance:'.$vehicle->id] = [
                    'type' => EventType::AUTRE->value,
                    'title' => "Entretien {$vehicle->name} — ".mb_strtolower($status->label()),
                    'description' => $detail !== '' ? "Échéance : {$detail}." : null,
                    'priority' => self::priority($status->severity),
                    'vehicle_id' => $vehicle->id,
                ];
            }
        }

        // --- Désinfection (par protocoles affectés) ---
        $disinfectionStatuses = VehicleDisinfection::statusForMany($vehicles, $now);
        foreach ($vehicles as $vehicle) {
            $status = $disinfectionStatuses[$vehicle->id] ?? null;
            if ($status !== null && $status->severity !== null) {
                $relevant['ech:disinfection:'.$vehicle->id] = [
                    'type' => EventType::AUTRE->value,
                    'title' => "Désinfection {$vehicle->name} — ".mb_strtolower($status->label()),
                    'description' => $status->dueAt !== null ? 'Échéance : '.$status->dueAt->format('d/m/Y').'.' : null,
                    'priority' => self::priority($status->severity),
                    'vehicle_id' => $vehicle->id,
                ];
            }
        }

        // --- Péremption des lots de matériel ---
        $soonDays = $organisation->expiryAlertDays();
        $limit = $now->copy()->addDays($soonDays)->endOfDay();
        $lots = StockLot::query()
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', $limit)
            ->where('quantity', '>', 0)
            ->with('material:id,name')
            ->get();
        foreach ($lots as $lot) {
            $expired = $lot->expiry_date !== null && $lot->expiry_date->lessThan($now->copy()->startOfDay());
            $relevant['ech:lot:'.$lot->id] = [
                'type' => EventType::AUTRE->value,
                'title' => ($expired ? 'Lot périmé' : 'Lot bientôt périmé')." — {$lot->material?->name} (".$lot->label().')',
                'description' => 'Péremption : '.$lot->expiry_date?->format('d/m/Y').'.',
                'priority' => $expired ? 'haute' : 'normale',
                'material_id' => $lot->material_id,
            ];
        }

        // --- Expiration des documents (véhicule : carte grise, agrément, CT…) ---
        $documents = Document::query()
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', $limit)
            ->get();
        foreach ($documents as $document) {
            $expired = $document->expires_at !== null && $document->expires_at->lessThan($now->copy()->startOfDay());
            $vehicleId = $document->documentable_type === Vehicle::class ? $document->documentable_id : null;
            $relevant['ech:document:'.$document->id] = [
                'type' => EventType::AUTRE->value,
                'title' => ($expired ? 'Document expiré' : 'Document à renouveler')." — {$document->category} : {$document->title}",
                'description' => 'Validité : '.$document->expires_at?->format('d/m/Y').'.',
                'priority' => $expired ? 'haute' : 'normale',
                'vehicle_id' => $vehicleId,
            ];
        }

        // --- Création des manquants (déduplication sur événement ouvert) ---
        $created = 0;
        $openByKey = Event::query()
            ->whereIn('source_key', array_keys($relevant) ?: ['__none__'])
            ->whereIn('status', [EventStatus::A_TRAITER->value, EventStatus::EN_COURS->value])
            ->pluck('source_key')
            ->flip();

        foreach ($relevant as $key => $attrs) {
            if ($openByKey->has($key)) {
                continue;
            }
            Event::create([
                ...$attrs,
                'source_key' => $key,
                'status' => EventStatus::A_TRAITER->value,
                'kanban_column_id' => $entryColumn,
            ]);
            $created++;
        }

        // --- Clôture automatique des échéances levées ---
        $closed = Event::query()
            ->where('source_key', 'like', 'ech:%')
            ->when($relevant !== [], fn ($q) => $q->whereNotIn('source_key', array_keys($relevant)))
            ->whereIn('status', [EventStatus::A_TRAITER->value, EventStatus::EN_COURS->value])
            ->update([
                'status' => EventStatus::RESOLU->value,
                'resolved_at' => $now,
                'kanban_column_id' => self::resolvedColumnId($organisation) ?? $entryColumn,
            ]);

        return ['created' => $created, 'closed' => $closed];
    }

    private static function priority(Severity $severity): string
    {
        return match ($severity) {
            Severity::CRITICAL => 'haute',
            Severity::WARNING => 'normale',
            default => 'basse',
        };
    }

    /** Première colonne « terminée » du tableau, pour y ranger les échéances levées. */
    private static function resolvedColumnId(Organisation $organisation): ?int
    {
        KanbanBoard::ensureSeeded($organisation);

        return KanbanColumn::query()
            ->where('is_done', true)
            ->orderBy('kanban_board_id')->orderBy('display_order')
            ->value('id');
    }
}
