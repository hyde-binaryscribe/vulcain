<?php

namespace App\Http\Controllers;

use App\Domain\Protocol\ProtocolFrequency;
use App\Domain\Protocol\ProtocolType;
use App\Models\ProtocolTemplate;
use App\Models\Location;
use App\Models\Material;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProtocolTemplateController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(): Response
    {
        $templates = ProtocolTemplate::query()
            ->with('vehicle:id,name')
            ->withCount('items')
            ->orderBy('name')
            ->get()
            ->map(fn (ProtocolTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'vehicle' => $t->vehicle?->name,
                'types' => $t->types ?? [],
                'type_labels' => $t->typeLabels(),
                'frequency' => $t->frequency->value,
                'frequency_label' => $t->frequency->label(),
                'items_count' => $t->items_count,
                'is_active' => $t->is_active,
                'version' => $t->version,
            ]);

        return Inertia::render('Templates/Index', [
            'templates' => $templates,
            'vehicles' => Vehicle::query()->orderBy('name')->get(['id', 'name']),
            'frequencies' => ProtocolFrequency::options(),
            'typeOptions' => ProtocolType::options(),
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTemplate($request);
        $validated['types'] = ProtocolType::sanitize($validated['types'] ?? []);

        $template = ProtocolTemplate::create($validated);

        return redirect()->route('templates.edit', $template)->with('status', 'Modèle créé.');
    }

    public function edit(ProtocolTemplate $template): Response
    {
        $template->load(['vehicle:id,name', 'items.material:id,name,reference', 'items.location:id,name']);

        $usedMaterialIds = $template->items->pluck('material_id');

        return Inertia::render('Templates/Edit', [
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'vehicle' => $template->vehicle?->name,
                'types' => $template->types ?? [],
                'frequency' => $template->frequency->value,
                'custom_days' => $template->custom_days,
                'is_active' => $template->is_active,
                'version' => $template->version,
            ],
            'items' => $template->items->map(fn ($i) => [
                'id' => $i->id,
                'material' => $i->material?->name,
                'reference' => $i->material?->reference,
                'location' => $i->location?->name,
                'location_id' => $i->location_id,
                'expected_qty' => $i->expected_qty,
                'display_order' => $i->display_order,
                'photo_required' => $i->photo_required,
            ]),
            'availableMaterials' => Material::query()
                ->whereNotIn('id', $usedMaterialIds)
                ->orderBy('name')
                ->get(['id', 'name', 'reference', 'location_id', 'theoretical_qty']),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'frequencies' => ProtocolFrequency::options(),
            'typeOptions' => ProtocolType::options(),
            'status' => session('status'),
        ]);
    }

    public function update(Request $request, ProtocolTemplate $template): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'types' => ['array'],
            'types.*' => [Rule::enum(ProtocolType::class)],
            'frequency' => ['required', Rule::enum(ProtocolFrequency::class)],
            'custom_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['boolean'],
        ]);

        $template->update([
            'name' => $validated['name'],
            'types' => ProtocolType::sanitize($validated['types'] ?? []),
            'frequency' => $validated['frequency'],
            'custom_days' => $validated['custom_days'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);
        $template->increment('version');

        return back()->with('status', 'Modèle mis à jour.');
    }

    public function destroy(ProtocolTemplate $template): RedirectResponse
    {
        $template->delete();

        return redirect()->route('templates.index')->with('status', 'Modèle supprimé.');
    }

    public function addItem(Request $request, ProtocolTemplate $template): RedirectResponse
    {
        $orgId = $this->tenant->id();

        $validated = $request->validate([
            'material_id' => ['required', Rule::exists('materials', 'id')->where('organisation_id', $orgId)->whereNull('deleted_at')],
            'location_id' => ['nullable', Rule::exists('locations', 'id')->where('organisation_id', $orgId)->whereNull('deleted_at')],
            'expected_qty' => ['nullable', 'integer', 'min:0'],
            'photo_required' => ['boolean'],
        ]);

        if ($template->items()->where('material_id', $validated['material_id'])->exists()) {
            return back()->with('status', 'Ce matériel est déjà dans le modèle.');
        }

        $template->items()->create([
            'material_id' => $validated['material_id'],
            'location_id' => $validated['location_id'] ?? null,
            'expected_qty' => $validated['expected_qty'] ?? 0,
            'photo_required' => $request->boolean('photo_required'),
            'display_order' => (int) $template->items()->max('display_order') + 10,
        ]);

        $template->increment('version');

        return back()->with('status', 'Matériel ajouté au modèle.');
    }

    public function updateItem(Request $request, ProtocolTemplate $template, int $item): RedirectResponse
    {
        $templateItem = $template->items()->findOrFail($item);

        $validated = $request->validate([
            'expected_qty' => ['required', 'integer', 'min:0'],
            'photo_required' => ['boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $templateItem->update([
            'expected_qty' => $validated['expected_qty'],
            'photo_required' => $request->boolean('photo_required'),
            'display_order' => $validated['display_order'] ?? $templateItem->display_order,
        ]);

        return back()->with('status', 'Élément mis à jour.');
    }

    public function removeItem(ProtocolTemplate $template, int $item): RedirectResponse
    {
        $template->items()->where('id', $item)->delete();
        $template->increment('version');

        return back()->with('status', 'Élément retiré.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTemplate(Request $request): array
    {
        $orgId = $this->tenant->id();

        return $request->validate([
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')->where('organisation_id', $orgId)->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:150'],
            'types' => ['array'],
            'types.*' => [Rule::enum(ProtocolType::class)],
            'frequency' => ['required', Rule::enum(ProtocolFrequency::class)],
            'custom_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['boolean'],
        ]);
    }
}
