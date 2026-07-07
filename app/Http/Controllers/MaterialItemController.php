<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\MaterialStatus;
use App\Models\Material;
use App\Models\MaterialItem;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaterialItemController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function store(Request $request, Material $material): RedirectResponse
    {
        $material->items()->create($this->validated($request));

        return back()->with('status', 'Exemplaire ajouté.');
    }

    public function update(Request $request, MaterialItem $item): RedirectResponse
    {
        $item->update($this->validated($request));

        return back()->with('status', 'Exemplaire mis à jour.');
    }

    public function destroy(MaterialItem $item): RedirectResponse
    {
        $item->delete();

        return back()->with('status', 'Exemplaire supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'serial_number' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::enum(MaterialStatus::class)],
            'location_id' => ['nullable', Rule::exists('locations', 'id')->where('organisation_id', $this->tenant->id())->whereNull('deleted_at')],
            'next_check_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
