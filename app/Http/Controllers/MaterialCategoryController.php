<?php

namespace App\Http\Controllers;

use App\Models\MaterialCategory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MaterialCategoryController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(): Response
    {
        return Inertia::render('MaterialCategories/Index', [
            'categories' => MaterialCategory::query()
                ->withCount('types')
                ->orderBy('name')
                ->get()
                ->map(fn (MaterialCategory $c) => ['id' => $c->id, 'name' => $c->name, 'types_count' => $c->types_count]),
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('material_categories', 'name')->where('organisation_id', $this->tenant->id()),
            ],
        ]);

        MaterialCategory::create(['name' => $request->input('name')]);

        return back()->with('status', 'Catégorie créée.');
    }

    public function destroy(MaterialCategory $category): RedirectResponse
    {
        $category->delete();

        return back()->with('status', 'Catégorie supprimée.');
    }
}
