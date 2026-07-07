<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\MaterialStatus;
use App\Models\Material;
use App\Models\StockLot;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockLotController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function store(Request $request, Material $material): RedirectResponse
    {
        $material->lots()->create($this->validated($request));

        return back()->with('status', 'Lot ajouté.');
    }

    public function update(Request $request, StockLot $lot): RedirectResponse
    {
        $lot->update($this->validated($request));

        return back()->with('status', 'Lot mis à jour.');
    }

    public function destroy(StockLot $lot): RedirectResponse
    {
        $lot->delete();

        return back()->with('status', 'Lot supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'lot_number' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:0'],
            'received_at' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(MaterialStatus::class)],
            'location_id' => ['nullable', Rule::exists('locations', 'id')->where('organisation_id', $this->tenant->id())->whereNull('deleted_at')],
        ]);

        return $data;
    }
}
