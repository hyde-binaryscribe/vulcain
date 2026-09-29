<?php

namespace App\Http\Controllers;

use App\Models\VehicleSession;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Suivi de service (gestionnaire) : services en cours et historique des prises /
 * fins de service, avec le détail des vérifications.
 */
class ServiceSessionController extends Controller
{
    public function index(): Response
    {
        $format = fn (VehicleSession $s) => [
            'id' => $s->id,
            'vehicle' => $s->vehicle?->callsign ?: $s->vehicle?->name,
            'agent' => $s->user?->name,
            'opened_at' => $s->opened_at?->fr('d/m/Y H:i'),
            'closed_at' => $s->closed_at?->fr('d/m/Y H:i'),
            'open_mileage' => $s->open_mileage,
            'close_mileage' => $s->close_mileage,
            'close_reason' => $s->close_reason,
            'closed_by' => $s->closer?->name,
            'open_responses' => $this->responses($s->open_responses),
            'close_responses' => $this->responses($s->close_responses),
            'open_notes' => $s->open_notes,
            'close_notes' => $s->close_notes,
        ];

        $open = VehicleSession::query()->open()
            ->with(['vehicle:id,name,callsign', 'user:id,name'])
            ->latest('opened_at')
            ->get()
            ->map($format);

        $history = VehicleSession::query()
            ->whereNotNull('closed_at')
            ->with(['vehicle:id,name,callsign', 'user:id,name', 'closer:id,name'])
            ->latest('opened_at')
            ->limit(100)
            ->get()
            ->map($format);

        return Inertia::render('ServiceSessions/Index', [
            'open' => $open,
            'history' => $history,
        ]);
    }

    /**
     * Normalise les réponses stockées (label / valeur lisible / alerte).
     *
     * @param  array<int, mixed>|null  $raw
     * @return list<array<string, mixed>>
     */
    private function responses(?array $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        return collect($raw)->map(function ($r) {
            $value = $r['value'] ?? null;
            $display = match (true) {
                ($r['type'] ?? null) === 'tristate' => $value === 'ok' ? 'OK' : ($value === 'nok' ? 'NOK' : '—'),
                ($r['type'] ?? null) === 'checkbox' => $value ? 'Oui' : 'Non',
                ($r['type'] ?? null) === 'photo' => null,
                default => ($value === null || $value === '') ? '—' : (string) $value,
            };

            return [
                'label' => $r['label'] ?? '',
                'display' => $display,
                'has_photo' => ! empty($r['photo_path']),
                'alert' => ! empty($r['alert']),
            ];
        })->all();
    }
}
