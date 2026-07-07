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
            ],
            'status' => session('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'track_expiry_in_mobile' => ['boolean'],
        ]);

        $org = $this->tenant->organisation();
        $org->settings = array_merge($org->settings ?? [], [
            'track_expiry_in_mobile' => (bool) ($validated['track_expiry_in_mobile'] ?? false),
        ]);
        $org->save();

        return back()->with('status', 'Réglages enregistrés.');
    }
}
