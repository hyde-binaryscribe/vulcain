<?php

namespace App\Http\Controllers;

use App\Domain\Protocol\ProtocolFrequency;
use App\Domain\Protocol\ProtocolScope;
use App\Domain\Protocol\ProtocolScopeType;
use App\Domain\Protocol\ProtocolType;
use App\Models\Location;
use App\Models\ProtocolTemplate;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProtocolTemplateController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly ProtocolScope $scope,
    ) {}

    public function index(): Response
    {
        $templates = ProtocolTemplate::query()
            ->with('vehicle:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (ProtocolTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'types' => $t->types ?? [],
                'type_labels' => $t->typeLabels(),
                'target' => $this->scope->targetLabel($t),
                'materials_count' => $this->scope->materials($t)->count(),
                'frequency' => $t->frequency->value,
                'frequency_label' => $t->frequency->label(),
                'is_active' => $t->is_active,
                'version' => $t->version,
            ]);

        return Inertia::render('Templates/Index', [
            'templates' => $templates,
            'vehicles' => Vehicle::query()->orderBy('name')->get(['id', 'name']),
            'locations' => $this->locationOptions(),
            'frequencies' => ProtocolFrequency::options(),
            'typeOptions' => ProtocolType::options(),
            'scopeOptions' => ProtocolScopeType::options(),
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTemplate($request);
        $validated['types'] = ProtocolType::sanitize($validated['types'] ?? []);
        $validated['vehicle_id'] = $this->deriveVehicleId($validated);
        $validated['include_children'] = $request->boolean('include_children', true);

        $template = ProtocolTemplate::create($validated);

        return redirect()->route('templates.edit', $template)->with('status', 'Modèle créé.');
    }

    public function edit(ProtocolTemplate $template): Response
    {
        $excluded = $template->excluded_material_ids ?? [];

        // Matériels du périmètre courant (exclusions marquées, mais toutes affichées).
        $inScope = ProtocolTemplate::query()->find($template->id);
        $inScope->excluded_material_ids = []; // on veut TOUT le périmètre pour cocher/décocher
        $materials = $this->scope->materials($inScope)->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'reference' => $m->reference,
            'location' => $m->location?->fullPath(),
            'tracking_mode' => $m->tracking_mode,
            'excluded' => in_array($m->id, $excluded, true),
        ])->values();

        return Inertia::render('Templates/Edit', [
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'types' => $template->types ?? [],
                'scope_type' => $template->scope_type->value,
                'scope_id' => $template->scope_id,
                'include_children' => $template->include_children,
                'target' => $this->scope->targetLabel($template),
                'frequency' => $template->frequency->value,
                'custom_days' => $template->custom_days,
                'is_active' => $template->is_active,
                'version' => $template->version,
            ],
            'materials' => $materials,
            'vehicles' => Vehicle::query()->orderBy('name')->get(['id', 'name']),
            'locations' => $this->locationOptions(),
            'frequencies' => ProtocolFrequency::options(),
            'typeOptions' => ProtocolType::options(),
            'scopeOptions' => ProtocolScopeType::options(),
            'status' => session('status'),
        ]);
    }

    public function update(Request $request, ProtocolTemplate $template): RedirectResponse
    {
        $validated = $this->validateTemplate($request);

        $template->update([
            'name' => $validated['name'],
            'types' => ProtocolType::sanitize($validated['types'] ?? []),
            'scope_type' => $validated['scope_type'],
            'scope_id' => $validated['scope_id'],
            'vehicle_id' => $this->deriveVehicleId($validated),
            'include_children' => $request->boolean('include_children', true),
            'frequency' => $validated['frequency'],
            'custom_days' => $validated['custom_days'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);
        $template->increment('version');

        return back()->with('status', 'Modèle mis à jour.');
    }

    /** Exclure / réintégrer des matériels du périmètre. */
    public function exclusions(Request $request, ProtocolTemplate $template): RedirectResponse
    {
        $orgId = $this->tenant->id();

        $validated = $request->validate([
            'excluded_material_ids' => ['array'],
            'excluded_material_ids.*' => [Rule::exists('materials', 'id')->where('organisation_id', $orgId)],
        ]);

        $template->update([
            'excluded_material_ids' => array_values(array_unique(array_map('intval', $validated['excluded_material_ids'] ?? []))),
        ]);
        $template->increment('version');

        return back()->with('status', 'Périmètre mis à jour.');
    }

    public function destroy(ProtocolTemplate $template): RedirectResponse
    {
        $template->delete();

        return redirect()->route('templates.index')->with('status', 'Modèle supprimé.');
    }

    /** Emplacements proposés comme cible (avec chemin complet). */
    private function locationOptions(): Collection
    {
        return Location::query()
            ->where('is_active', true)
            ->with(['vehicle:id,name', 'parent:id,name,parent_id,vehicle_id'])
            ->orderBy('name')
            ->get()
            ->map(fn (Location $l) => ['id' => $l->id, 'name' => $l->fullPath()])
            ->values();
    }

    /** Véhicule de rattachement déduit de la cible (null pour un emplacement fixe). */
    private function deriveVehicleId(array $validated): ?int
    {
        if ($validated['scope_type'] === ProtocolScopeType::VEHICLE->value) {
            return (int) $validated['scope_id'];
        }

        $location = Location::query()->find($validated['scope_id']);

        return $location?->vehicle_id;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTemplate(Request $request): array
    {
        $orgId = $this->tenant->id();
        $scopeType = $request->input('scope_type');

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'types' => ['array'],
            'types.*' => [Rule::enum(ProtocolType::class)],
            'scope_type' => ['required', Rule::enum(ProtocolScopeType::class)],
            'scope_id' => [
                'required',
                $scopeType === ProtocolScopeType::LOCATION->value
                    ? Rule::exists('locations', 'id')->where('organisation_id', $orgId)->whereNull('deleted_at')
                    : Rule::exists('vehicles', 'id')->where('organisation_id', $orgId)->whereNull('deleted_at'),
            ],
            'include_children' => ['boolean'],
            'frequency' => ['required', Rule::enum(ProtocolFrequency::class)],
            'custom_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['boolean'],
        ]);
    }
}
