<?php

namespace App\Http\Controllers;

use App\Models\MaterialCategory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaterialCategoryController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

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
