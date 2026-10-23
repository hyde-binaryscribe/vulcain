<?php

namespace App\Http\Controllers;

use App\Domain\Fleet\ArmamentLevel;
use App\Domain\Fleet\ArmamentReference;
use App\Models\MaterialType;
use App\Models\VehicleType;
use App\Models\VehicleTypeEquipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Armement de référence d'un type de véhicule : la liste des équipements
 * attendus (quantité + niveau d'obligation), amorçable depuis les modèles
 * légaux (NF EN 1789 / arrêté 2017) puis éditable.
 */
class VehicleTypeEquipmentController extends Controller
{
    public function index(VehicleType $vehicleType): Response
    {
        $equipment = $vehicleType->equipment()
            ->orderBy('display_order')->orderBy('id')->get()
            ->map(fn (VehicleTypeEquipment $e) => [
                'category' => $e->category,
                'designation' => $e->designation,
                'material_type_id' => $e->material_type_id,
                'quantity' => $e->quantity,
                'volume_l' => $e->volume_l,
                'level' => $e->level->value,
                'note' => $e->note,
            ])->values();

        return Inertia::render('VehicleTypes/Armament', [
            'type' => ['id' => $vehicleType->id, 'name' => $vehicleType->name],
            'equipment' => $equipment,
            'levels' => ArmamentLevel::options(),
            'materialTypes' => MaterialType::query()->where('is_active', true)
                ->orderBy('display_order')->orderBy('name')->get(['id', 'name'])
                ->map(fn (MaterialType $t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'templates' => ArmamentReference::templates(),
            'status' => session('status'),
        ]);
    }

    public function save(Request $request, VehicleType $vehicleType): RedirectResponse
    {
        $request->validate(['items' => ['array']]);

        $validMaterialTypeIds = MaterialType::query()->pluck('id')->all();

        DB::transaction(function () use ($request, $vehicleType, $validMaterialTypeIds) {
            $vehicleType->equipment()->delete();

            foreach ($this->sanitize($request->input('items', []), $validMaterialTypeIds) as $order => $item) {
                $vehicleType->equipment()->create($item + ['display_order' => $order]);
            }
        });

        return back()->with('status', 'Armement enregistré.');
    }

    /**
     * @param  array<int, mixed>  $raw
     * @param  list<int>  $validMaterialTypeIds
     * @return list<array<string, mixed>>
     */
    private function sanitize(array $raw, array $validMaterialTypeIds = []): array
    {
        $levels = ArmamentLevel::values();
        $out = [];

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $designation = trim((string) ($item['designation'] ?? ''));
            if ($designation === '') {
                continue;
            }

            $quantity = (int) ($item['quantity'] ?? 1);
            $level = in_array($item['level'] ?? null, $levels, true) ? $item['level'] : ArmamentLevel::OBLIGATOIRE->value;
            $volume = isset($item['volume_l']) && is_numeric($item['volume_l']) ? max(0, (int) $item['volume_l']) : null;
            $materialTypeId = isset($item['material_type_id']) && in_array((int) $item['material_type_id'], $validMaterialTypeIds, true)
                ? (int) $item['material_type_id'] : null;

            $out[] = [
                'category' => mb_substr(trim((string) ($item['category'] ?? '')) ?: 'Divers', 0, 80),
                'designation' => mb_substr($designation, 0, 160),
                'material_type_id' => $materialTypeId,
                'quantity' => max(1, min(999, $quantity)),
                'volume_l' => $volume ?: null,
                'level' => $level,
                'note' => ($note = trim((string) ($item['note'] ?? ''))) !== '' ? mb_substr($note, 0, 200) : null,
            ];
        }

        return $out;
    }
}
