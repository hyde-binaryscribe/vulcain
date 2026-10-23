<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Events\EventStatus;
use App\Domain\Events\EventType;
use App\Domain\Fleet\VehicleDisinfection;
use App\Http\Controllers\Controller;
use App\Models\DisinfectionRecord;
use App\Models\Event;
use App\Models\OxygenBottle;
use App\Models\OxygenBottleLog;
use App\Models\ServiceProtocolField;
use App\Models\Vehicle;
use App\Models\VehicleSession;
use Illuminate\Http\JsonResponse;

/**
 * API v1 — vérifications des véhicules (lien Itineris, lecture seule) : dernière
 * prise de service et ses contrôles, désinfection (statut et dernières opérations),
 * anomalies ouvertes, oxygène embarqué (bouteilles de la dotation et derniers relevés).
 * Une seule requête pour tout le parc. Jamais de photo.
 */
class CheckController extends Controller
{
    /** Champs de protocole de l'organisation, par id et par libellé (contrôles saisis avant l'enregistrement de l'id). */
    private array $fieldsById = [];

    private array $fieldsByLabel = [];

    public function index(): JsonResponse
    {
        $fields = ServiceProtocolField::query()->get(['id', 'label', 'type', 'config']);
        $this->fieldsById = $fields->keyBy('id')->all();
        $this->fieldsByLabel = $fields->keyBy(fn ($f) => mb_strtolower(trim($f->label)))->all();

        $vehicles = Vehicle::query()->get(['id', 'uuid']);
        $ids = $vehicles->pluck('id');

        // Dernière session (prise de service) par véhicule.
        $sessions = VehicleSession::query()
            ->whereIn('vehicle_id', $ids)
            ->whereIn('id', VehicleSession::query()->selectRaw('max(id)')->whereIn('vehicle_id', $ids)->groupBy('vehicle_id'))
            ->with(['user:id,name', 'partner:id,name', 'closer:id,name'])
            ->get()->keyBy('vehicle_id');

        // Dernière opération de désinfection par véhicule et par nature.
        $disinfections = DisinfectionRecord::query()
            ->whereIn('vehicle_id', $ids)
            ->whereIn('id', DisinfectionRecord::query()->selectRaw('max(id)')->whereIn('vehicle_id', $ids)->groupBy('vehicle_id', 'type'))
            ->with(['user:id,name', 'protocol:id,name'])
            ->get()->groupBy('vehicle_id');

        $statuses = VehicleDisinfection::statusForMany($vehicles);

        $anomalies = Event::query()
            ->whereIn('vehicle_id', $ids)
            ->where('type', EventType::ANOMALIE->value)
            ->whereIn('status', [EventStatus::A_TRAITER->value, EventStatus::EN_COURS->value])
            ->latest()
            ->get(['id', 'vehicle_id', 'title', 'priority', 'status', 'created_at'])
            ->groupBy('vehicle_id');

        // Dotation d'oxygène (bouteilles actives) et derniers mouvements de chaque bouteille.
        $bottles = OxygenBottle::query()
            ->whereIn('vehicle_id', $ids)->where('is_active', true)
            ->orderBy('display_order')->orderBy('id')
            ->get()->groupBy('vehicle_id');
        $bottleLogs = OxygenBottleLog::query()
            ->whereIn('vehicle_id', $ids)->where('recorded_at', '>=', now()->subDays(30))
            ->with('user:id,name')->latest('recorded_at')->latest('id')
            ->get()->groupBy('oxygen_bottle_id');

        $data = $vehicles->map(function (Vehicle $v) use ($sessions, $disinfections, $statuses, $anomalies, $bottles, $bottleLogs) {
            $s = $sessions->get($v->id);
            $status = $statuses[$v->id] ?? null;

            $checks = $s ? $this->checks($s->open_responses ?? $s->open_steps ?? []) : [];
            $endChecks = $s ? $this->checks($s->close_responses ?? []) : [];

            return [
                'uuid' => $v->uuid,
                'service' => $s ? [
                    'open' => $s->closed_at === null,
                    'opened_at' => $s->opened_at?->toIso8601String(),
                    'opened_by' => $s->openerName(),
                    'partner' => $s->partnerName(),
                    'closed_at' => $s->closed_at?->toIso8601String(),
                    'closed_by' => $s->closerName(),
                    'checks' => $checks,
                    'end_checks' => $endChecks,
                ] : null,
                // Dernier niveau d'oxygène : fin de service si elle en contient un, sinon prise de service.
                'o2' => $s ? ($this->o2($endChecks, $s->closed_at) ?? $this->o2($checks, $s->opened_at)) : null,
                'oxygen' => ($bottles->get($v->id) ?? collect())
                    ->map(fn (OxygenBottle $b) => $this->bottle($b, $bottleLogs->get($b->id) ?? collect()))
                    ->values()->all(),
                'disinfection' => [
                    'state' => $status?->state,
                    'label' => $status?->label(),
                    'due_at' => $status?->dueAt?->toIso8601String(),
                    'last' => ($disinfections->get($v->id) ?? collect())
                        ->sortByDesc('performed_at')->values()
                        ->map(fn (DisinfectionRecord $d) => [
                            'type' => $d->type?->value,
                            'label' => $d->type?->label(),
                            'performed_at' => $d->performed_at?->toIso8601String(),
                            'by' => $d->user?->name,
                            'protocol' => $d->protocol?->name,
                        ])->all(),
                ],
                'anomalies' => ($anomalies->get($v->id) ?? collect())->map(fn (Event $e) => [
                    'title' => $e->title,
                    'priority' => $e->priority,
                    'status' => $e->status,
                    'created_at' => $e->created_at?->toIso8601String(),
                ])->values()->all(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Contrôles d'une prise ou fin de service : libellé, valeur, alerte (gravité, message).
     * Jamais la photo ni son chemin.
     *
     * @param  array<int, mixed>  $rows
     * @return list<array{label: string, type: ?string, value: mixed, alert: ?array}>
     */
    private function checks(array $rows): array
    {
        return collect($rows)
            ->filter(fn ($r) => is_array($r) && isset($r['label']))
            ->map(function (array $r) {
                $field = $this->fieldsById[$r['field_id'] ?? 0] ?? $this->fieldsByLabel[mb_strtolower(trim((string) $r['label']))] ?? null;
                $config = $field && is_array($field->config) ? $field->config : [];
                $value = ($r['type'] ?? null) === 'photo' ? null : ($r['value'] ?? null);

                return [
                    'label' => (string) $r['label'],
                    'type' => $r['type'] ?? null,
                    'value' => $value,
                    'percent' => $r['percent'] ?? $field?->percentFor($value),
                    'unit' => $r['unit'] ?? $config['unit'] ?? null,
                    'key' => $this->keyOf($r, $config),
                    'alert' => isset($r['alert']['severity']) ? [
                        'severity' => (string) $r['alert']['severity'],
                        'message' => (string) ($r['alert']['message'] ?? ''),
                    ] : null,
                ];
            })->values()->all();
    }

    /**
     * Donnée partagée avec Itineris : celle choisie sur le champ (« Transmis à Itineris »),
     * sinon reconnue au libellé d'une jauge ou d'un nombre (« oxygène », « O2 »).
     */
    private function keyOf(array $row, array $config): ?string
    {
        $share = $row['share'] ?? $config['share'] ?? null;
        if ($share !== null) {
            return (string) $share;
        }
        $numeric = in_array($row['type'] ?? null, ['gauge', 'number'], true);

        return $numeric && preg_match('/oxyg|\bo2\b/iu', (string) $row['label']) ? 'o2' : null;
    }

    /** Niveau d'oxygène parmi des contrôles, avec l'heure du relevé. */
    private function o2(array $checks, $at): ?array
    {
        $c = collect($checks)->first(fn ($k) => $k['key'] === 'o2' && $k['value'] !== null && $k['value'] !== '');
        if ($c === null) {
            return null;
        }

        return [
            'label' => $c['label'],
            'value' => (string) $c['value'],
            'unit' => $c['unit'],
            'percent' => $c['percent'],
            'alert' => $c['alert'],
            'at' => $at?->toIso8601String(),
        ];
    }

    /**
     * Bouteille de la dotation : niveau (bar et %), plage, statut, dernier relevé et
     * les 5 derniers mouvements (30 jours) : relevé, changement de bouteille, statut.
     */
    private function bottle(OxygenBottle $b, $logs): array
    {
        return [
            'label' => $b->label,
            'serial_number' => $b->serial_number,
            'capacity_l' => $b->capacity_l !== null ? (float) $b->capacity_l : null,
            'pressure' => $b->current_pressure,
            'pressure_min' => $b->pressure_min,
            'pressure_max' => $b->pressure_max,
            'percent' => $b->percent(),
            'status' => $b->status,
            'status_label' => $b->statusLabel(),
            'checked_at' => $b->last_checked_at?->toIso8601String(),
            'logs' => $logs->take(5)->map(fn (OxygenBottleLog $l) => [
                'action' => $l->action,
                'label' => $l->actionLabel(),
                'pressure' => $l->pressure,
                'percent' => $l->percent,
                'serial_number' => $l->serial_number,
                'previous_serial' => $l->previous_serial,
                'note' => $l->note,
                'by' => $l->user?->name,
                'at' => $l->recorded_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
