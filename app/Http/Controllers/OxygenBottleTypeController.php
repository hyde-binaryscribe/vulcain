<?php

namespace App\Http\Controllers;

use App\Models\OxygenBottleType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catalogue des types de bouteilles d'oxygène (B2, B5, B11, B15…) : caractéristiques
 * pré-réglées qui pré-remplissent une bouteille à la déclaration.
 */
class OxygenBottleTypeController extends Controller
{
    public function index(): Response
    {
        OxygenBottleType::ensureSeeded();

        $types = OxygenBottleType::query()->orderBy('display_order')->orderBy('name')->get()
            ->map(fn (OxygenBottleType $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'capacity_l' => $t->capacity_l !== null ? (float) $t->capacity_l : null,
                'volume_l' => $t->volume_l,
                'pressure_min' => $t->pressure_min,
                'pressure_max' => $t->pressure_max,
                'is_active' => $t->is_active,
            ]);

        return Inertia::render('OxygenBottleTypes/Index', [
            'types' => $types,
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        OxygenBottleType::create($this->validated($request));

        return back()->with('status', 'Type de bouteille créé.');
    }

    public function update(Request $request, OxygenBottleType $oxygenBottleType): RedirectResponse
    {
        $oxygenBottleType->update($this->validated($request));

        return back()->with('status', 'Type de bouteille mis à jour.');
    }

    public function toggle(OxygenBottleType $oxygenBottleType): RedirectResponse
    {
        $oxygenBottleType->update(['is_active' => ! $oxygenBottleType->is_active]);

        return back()->with('status', 'Statut mis à jour.');
    }

    public function destroy(OxygenBottleType $oxygenBottleType): RedirectResponse
    {
        $oxygenBottleType->delete();

        return back()->with('status', 'Type de bouteille supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'capacity_l' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'volume_l' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'pressure_min' => ['required', 'integer', 'min:0', 'max:999'],
            'pressure_max' => ['required', 'integer', 'min:1', 'max:1000', 'gt:pressure_min'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['display_order'] ??= 0;
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
