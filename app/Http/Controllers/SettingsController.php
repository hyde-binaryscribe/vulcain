<?php

namespace App\Http\Controllers;

use App\Models\KanbanBoard;
use App\Models\KanbanColumn;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(): Response
    {
        $org = $this->tenant->organisation();
        KanbanBoard::ensureSeeded($org);

        return Inertia::render('Settings/Index', [
            'settings' => [
                'track_expiry_in_mobile' => $org->tracksExpiryInMobile(),
                'bags_enabled' => $org->bagsEnabled(),
                'vehicle_access_qr_only' => $org->vehicleAccessQrOnly(),
                'fuel_tracking_enabled' => $org->fuelTrackingEnabled(),
                'body_inspection_enabled' => $org->bodyInspectionEnabled(),
                'maintenance_alert_days' => $org->maintenanceAlertDays(),
                'maintenance_alert_km' => $org->maintenanceAlertKm(),
                'expiry_alert_days' => $org->expiryAlertDays(),
                'anomaly_entry_column_id' => $org->anomalyEntryColumnId(),
                'service_start_steps' => $org->serviceStartSteps(),
            ],
            // Colonnes Kanban (préfixées du tableau) pour désigner l'entrée des anomalies.
            'kanbanColumns' => KanbanColumn::query()->with('board:id,name')
                ->orderBy('kanban_board_id')->orderBy('display_order')->get()
                ->map(fn (KanbanColumn $c) => [
                    'id' => $c->id,
                    'label' => ($c->board?->name ? $c->board->name.' — ' : '').$c->name,
                ]),
            'status' => session('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'track_expiry_in_mobile' => ['boolean'],
            'bags_enabled' => ['boolean'],
            'vehicle_access_qr_only' => ['boolean'],
            'fuel_tracking_enabled' => ['boolean'],
            'body_inspection_enabled' => ['boolean'],
            'maintenance_alert_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'maintenance_alert_km' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'expiry_alert_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'anomaly_entry_column_id' => ['nullable', Rule::exists('kanban_columns', 'id')->where('organisation_id', $this->tenant->id())],
            'service_start_steps' => ['nullable', 'array', 'max:50'],
            'service_start_steps.*' => ['nullable', 'string', 'max:500'],
        ]);

        // Nettoyage des étapes de prise de service (lignes vides ignorées).
        $steps = array_values(array_filter(
            array_map(fn ($s) => trim((string) $s), $validated['service_start_steps'] ?? []),
            fn ($s) => $s !== '',
        ));

        $org = $this->tenant->organisation();
        $org->settings = array_merge($org->settings ?? [], [
            'track_expiry_in_mobile' => (bool) ($validated['track_expiry_in_mobile'] ?? false),
            'bags_enabled' => (bool) ($validated['bags_enabled'] ?? false),
            'vehicle_access_qr_only' => (bool) ($validated['vehicle_access_qr_only'] ?? false),
            'fuel_tracking_enabled' => (bool) ($validated['fuel_tracking_enabled'] ?? false),
            'body_inspection_enabled' => (bool) ($validated['body_inspection_enabled'] ?? false),
            'maintenance_alert_days' => $validated['maintenance_alert_days'] ?? 14,
            'maintenance_alert_km' => $validated['maintenance_alert_km'] ?? 500,
            'expiry_alert_days' => $validated['expiry_alert_days'] ?? 30,
            'anomaly_entry_column_id' => $validated['anomaly_entry_column_id'] ?? null,
            'service_start_steps' => $steps,
        ]);
        $org->save();

        return back()->with('status', 'Réglages enregistrés.');
    }
}
