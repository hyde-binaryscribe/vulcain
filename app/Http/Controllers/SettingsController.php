<?php

namespace App\Http\Controllers;

use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(): Response
    {
        $org = $this->tenant->organisation();

        return Inertia::render('Settings/Index', [
            'settings' => [
                'track_expiry_in_mobile' => $org->tracksExpiryInMobile(),
                'bags_enabled' => $org->bagsEnabled(),
                'vehicle_access_qr_only' => $org->vehicleAccessQrOnly(),
                'fuel_tracking_enabled' => $org->fuelTrackingEnabled(),
                'service_start_steps' => $org->serviceStartSteps(),
            ],
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
            'service_start_steps' => $steps,
        ]);
        $org->save();

        return back()->with('status', 'Réglages enregistrés.');
    }
}
