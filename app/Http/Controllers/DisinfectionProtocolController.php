<?php

namespace App\Http\Controllers;

use App\Domain\Fleet\DisinfectionType;
use App\Domain\Sectors\Sector;
use App\Models\DisinfectionProtocol;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bibliothèque de protocoles de désinfection — réservée au transport sanitaire
 * (ambulance privée), pré-remplie selon les niveaux recommandés (cadre ARS).
 */
class DisinfectionProtocolController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    /** Fonctionnalité propre au secteur ambulance privée. */
    private function ensureSector(): void
    {
        abort_unless($this->tenant->organisation()?->sector === Sector::AMBULANCE_PRIVEE, 404);
    }

    public function index(): Response
    {
        $this->ensureSector();

        // Pré-remplissage à la première visite (recommandations ARS du secteur).
        DisinfectionProtocol::ensureSeeded($this->tenant->organisation());

        $protocols = DisinfectionProtocol::query()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(fn (DisinfectionProtocol $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->type->value,
                'type_label' => $p->type->label(),
                'cadence' => $p->cadence,
                'frequency_days' => $p->frequency_days,
                'procedure' => $p->procedure,
                'display_order' => $p->display_order,
                'is_active' => $p->is_active,
            ]);

        return Inertia::render('DisinfectionProtocols/Index', [
            'protocols' => $protocols,
            'types' => DisinfectionType::options(),
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureSector();
        DisinfectionProtocol::create($this->validated($request));

        return back()->with('status', 'Protocole de désinfection créé.');
    }

    public function update(Request $request, DisinfectionProtocol $disinfectionProtocol): RedirectResponse
    {
        $this->ensureSector();
        $disinfectionProtocol->update($this->validated($request));

        return back()->with('status', 'Protocole de désinfection mis à jour.');
    }

    public function toggle(DisinfectionProtocol $disinfectionProtocol): RedirectResponse
    {
        $this->ensureSector();
        $disinfectionProtocol->is_active = ! $disinfectionProtocol->is_active;
        $disinfectionProtocol->save();

        return back()->with('status', 'Statut du protocole mis à jour.');
    }

    public function destroy(DisinfectionProtocol $disinfectionProtocol): RedirectResponse
    {
        $this->ensureSector();
        $disinfectionProtocol->delete();

        return back()->with('status', 'Protocole de désinfection supprimé.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(DisinfectionType::class)],
            'cadence' => ['nullable', 'string', 'max:100'],
            'frequency_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'procedure' => ['nullable', 'string', 'max:5000'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['display_order'] ??= 0;
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
