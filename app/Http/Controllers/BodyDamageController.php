<?php

namespace App\Http\Controllers;

use App\Models\BodyDamage;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSession;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * État carrosserie : pointage des anomalies sur un schéma du véhicule
 * (description + photo). Accessible pendant un service ouvert sur le véhicule
 * (ou aux gestionnaires). Les points restent visibles jusqu'à résolution.
 */
class BodyDamageController extends Controller
{
    private const VIEWS = ['avant', 'arriere', 'gauche', 'droite', 'dessus'];

    public function __construct(private readonly TenantContext $tenant) {}

    public function store(Request $request, Vehicle $vehicle): RedirectResponse
    {
        abort_unless($this->tenant->organisation()->bodyInspectionEnabled(), 404);
        abort_unless($this->canAccess($vehicle, $request->user()), 403);

        $validated = $request->validate([
            'view' => ['required', Rule::in(self::VIEWS)],
            'pos_x' => ['required', 'numeric', 'min:0', 'max:100'],
            'pos_y' => ['required', 'numeric', 'min:0', 'max:100'],
            'description' => ['required', 'string', 'max:500'],
            'photo' => ['nullable', 'file', 'image', 'max:10240'],
        ]);

        $path = $request->hasFile('photo')
            ? $request->file('photo')->store("body-damages/{$this->tenant->id()}", 'local')
            : null;

        $vehicle->bodyDamages()->create([
            'view' => $validated['view'],
            'pos_x' => $validated['pos_x'],
            'pos_y' => $validated['pos_y'],
            'description' => $validated['description'],
            'photo_path' => $path,
            'status' => 'ouverte',
            'reported_by' => $request->user()->id,
        ]);

        return back(303)->with('status', 'Anomalie carrosserie enregistrée.');
    }

    public function resolve(Request $request, Vehicle $vehicle, BodyDamage $bodyDamage): RedirectResponse
    {
        abort_unless($this->tenant->organisation()->bodyInspectionEnabled(), 404);
        abort_unless($bodyDamage->vehicle_id === $vehicle->id, 404);
        abort_unless($this->canAccess($vehicle, $request->user()), 403);

        $bodyDamage->update([
            'status' => 'resolue',
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return back(303)->with('status', 'Anomalie marquée comme résolue.');
    }

    public function photo(Request $request, Vehicle $vehicle, BodyDamage $bodyDamage)
    {
        abort_unless($this->tenant->organisation()->bodyInspectionEnabled(), 404);
        abort_unless($bodyDamage->vehicle_id === $vehicle->id, 404);
        abort_unless($this->canAccess($vehicle, $request->user()), 403);
        abort_unless($bodyDamage->photo_path && Storage::disk('local')->exists($bodyDamage->photo_path), 404);

        return response()->file(Storage::disk('local')->path($bodyDamage->photo_path));
    }

    /** Suppression (correction) : réservée aux gestionnaires. */
    public function destroy(Vehicle $vehicle, BodyDamage $bodyDamage): RedirectResponse
    {
        abort_unless($this->tenant->organisation()->bodyInspectionEnabled(), 404);
        abort_unless($bodyDamage->vehicle_id === $vehicle->id, 404);

        if ($bodyDamage->photo_path && Storage::disk('local')->exists($bodyDamage->photo_path)) {
            Storage::disk('local')->delete($bodyDamage->photo_path);
        }
        $bodyDamage->delete();

        return back(303)->with('status', 'Anomalie carrosserie supprimée.');
    }

    /** Gestionnaire, ou agent ayant une session ouverte sur ce véhicule. */
    private function canAccess(Vehicle $vehicle, User $user): bool
    {
        if ($user->can('vehicles.manage')) {
            return true;
        }

        return VehicleSession::query()->open()
            ->where('vehicle_id', $vehicle->id)
            ->where('user_id', $user->id)
            ->exists();
    }
}
