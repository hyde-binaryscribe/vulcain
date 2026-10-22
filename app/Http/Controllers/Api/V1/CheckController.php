<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Events\EventStatus;
use App\Domain\Events\EventType;
use App\Domain\Fleet\VehicleDisinfection;
use App\Http\Controllers\Controller;
use App\Models\DisinfectionRecord;
use App\Models\Event;
use App\Models\Vehicle;
use App\Models\VehicleSession;
use Illuminate\Http\JsonResponse;

/**
 * API v1 — vérifications des véhicules (lien Itineris, lecture seule) : dernière
 * prise de service et ses contrôles, désinfection (statut et dernières opérations),
 * anomalies ouvertes. Une seule requête pour tout le parc. Jamais de photo.
 */
class CheckController extends Controller
{
    public function index(): JsonResponse
    {
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

        $data = $vehicles->map(function (Vehicle $v) use ($sessions, $disinfections, $statuses, $anomalies) {
            $s = $sessions->get($v->id);
            $status = $statuses[$v->id] ?? null;

            return [
                'uuid' => $v->uuid,
                'service' => $s ? [
                    'open' => $s->closed_at === null,
                    'opened_at' => $s->opened_at?->toIso8601String(),
                    'opened_by' => $s->user?->name,
                    'partner' => $s->partner?->name,
                    'closed_at' => $s->closed_at?->toIso8601String(),
                    'closed_by' => $s->closer?->name,
                    'checks' => $this->checks($s->open_responses ?? $s->open_steps ?? []),
                    'end_checks' => $this->checks($s->close_responses ?? []),
                ] : null,
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
            ->map(fn (array $r) => [
                'label' => (string) $r['label'],
                'type' => $r['type'] ?? null,
                'value' => ($r['type'] ?? null) === 'photo' ? null : ($r['value'] ?? null),
                'alert' => isset($r['alert']['severity']) ? [
                    'severity' => (string) $r['alert']['severity'],
                    'message' => (string) ($r['alert']['message'] ?? ''),
                ] : null,
            ])->values()->all();
    }
}
