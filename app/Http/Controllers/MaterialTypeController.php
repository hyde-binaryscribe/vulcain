<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialType;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MaterialTypeController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(): Response
    {
        $types = MaterialType::query()
            ->with('category:id,name')
            ->withCount('models')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(fn (MaterialType $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'category' => $t->category?->name,
                'category_id' => $t->category_id,
                'tracking_mode' => $t->tracking_mode,
                'tracking_label' => Material::TRACKING_MODES[$t->tracking_mode] ?? $t->tracking_mode,
                'display_order' => $t->display_order,
                'is_active' => $t->is_active,
                'models_count' => $t->models_count,
            ]);

        return Inertia::render('MaterialTypes/Index', [
            'types' => $types,
            'categories' => MaterialCategory::query()->orderBy('name')->get(['id', 'name']),
            'trackingModes' => collect(Material::TRACKING_MODES)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        MaterialType::create($this->validated($request));

        return back()->with('status', 'Type de matériel créé.');
    }

    public function update(Request $request, MaterialType $materialType): RedirectResponse
    {
        $materialType->update($this->validated($request, $materialType));

        return back()->with('status', 'Type de matériel mis à jour.');
    }

    public function toggle(MaterialType $materialType): RedirectResponse
    {
        $materialType->is_active = ! $materialType->is_active;
        $materialType->save();

        return back()->with('status', 'Statut du type mis à jour.');
    }

    public function destroy(MaterialType $materialType): RedirectResponse
    {
        $materialType->delete();

        return back()->with('status', 'Type de matériel supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?MaterialType $current = null): array
    {
        $orgId = $this->tenant->id();

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('material_types', 'name')
                    ->where('organisation_id', $orgId)
                    ->whereNull('deleted_at')
                    ->ignore($current?->id),
            ],
            'category_id' => ['nullable', Rule::exists('material_categories', 'id')->where('organisation_id', $orgId)],
            'tracking_mode' => ['required', Rule::in(array_keys(Material::TRACKING_MODES))],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['display_order'] ??= 0;
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
