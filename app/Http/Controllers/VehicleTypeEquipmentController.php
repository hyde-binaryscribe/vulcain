<?php

namespace App\Http\Controllers;

use App\Domain\Fleet\ArmamentLevel;
use App\Domain\Fleet\ArmamentReference;
use App\Models\MaterialType;
use App\Models\OxygenBottleType;
use App\Models\VehicleType;
use App\Models\VehicleTypeEquipment;
use App\Models\VehicleTypeOxygen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        OxygenBottleType::ensureSeeded();

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
            'oxygenBottleTypes' => OxygenBottleType::query()->where('is_active', true)
                ->orderBy('display_order')->orderBy('name')->get()
                ->map(fn (OxygenBottleType $t) => ['id' => $t->id, 'name' => $t->name, 'volume_l' => $t->volume_l])->values(),
            'oxygenDotation' => $vehicleType->oxygenDotation()->get()
                ->map(fn (VehicleTypeOxygen $d) => [
                    'oxygen_bottle_type_id' => $d->oxygen_bottle_type_id,
                    'quantity' => $d->quantity,
                    'label' => $d->label,
                ])->values(),
            'templates' => ArmamentReference::templates(),
            'status' => session('status'),
        ]);
    }

    /**
     * Crée les types de matériel manquants depuis les désignations de l'armement
     * et relie chaque ligne, en réutilisant un type existant au nom équivalent
     * (anti-doublon). Les lignes « volume » (oxygène) et sections sont ignorées.
     */
    public function linkMaterials(Request $request, VehicleType $vehicleType): RedirectResponse
    {
        $request->validate(['items' => ['array']]);
        $validMaterialTypeIds = MaterialType::query()->pluck('id')->all();
        $items = $this->sanitize($request->input('items', []), $validMaterialTypeIds);

        // Index des types existants par nom normalisé (réutilisation = anti-doublon).
        $byName = [];
        foreach (MaterialType::query()->get(['id', 'name']) as $mt) {
            $byName[$this->normalize($mt->name)] = $mt->id;
        }

        $created = 0;
        $linked = 0;
        $nextOrder = (int) MaterialType::query()->max('display_order');

        foreach ($items as $k => $item) {
            if ($item['material_type_id'] !== null || $item['volume_l'] !== null) {
                continue; // déjà relié, ou ligne volume (oxygène)
            }
            $key = $this->normalize($item['designation']);
            if ($key === '') {
                continue;
            }

            if (isset($byName[$key])) {
                $items[$k]['material_type_id'] = $byName[$key];
            } else {
                $mt = MaterialType::create([
                    'name' => $item['designation'],
                    'tracking_mode' => 'quantity',
                    'display_order' => ++$nextOrder,
                    'is_active' => true,
                ]);
                $byName[$key] = $mt->id;
                $items[$k]['material_type_id'] = $mt->id;
                $created++;
            }
            $linked++;
        }

        DB::transaction(function () use ($vehicleType, $items) {
            $vehicleType->equipment()->delete();
            foreach ($items as $order => $item) {
                $vehicleType->equipment()->create($item + ['display_order' => $order]);
            }
        });

        return back()->with('status', "{$created} type(s) de matériel créé(s), {$linked} ligne(s) reliée(s).");
    }

    /** Normalise un libellé pour détecter les doublons (sans accents, minuscules). */
    private function normalize(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower($s)))));
    }

    /** Enregistre la dotation d'oxygène de référence (répartition en bouteilles). */
    public function saveOxygen(Request $request, VehicleType $vehicleType): RedirectResponse
    {
        $request->validate(['items' => ['array']]);
        $validTypeIds = OxygenBottleType::query()->pluck('id')->all();

        DB::transaction(function () use ($request, $vehicleType, $validTypeIds) {
            $vehicleType->oxygenDotation()->delete();

            foreach ((array) $request->input('items', []) as $order => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $typeId = isset($item['oxygen_bottle_type_id']) && in_array((int) $item['oxygen_bottle_type_id'], $validTypeIds, true)
                    ? (int) $item['oxygen_bottle_type_id'] : null;
                if ($typeId === null) {
                    continue;
                }
                $vehicleType->oxygenDotation()->create([
                    'oxygen_bottle_type_id' => $typeId,
                    'quantity' => max(1, min(99, (int) ($item['quantity'] ?? 1))),
                    'label' => ($l = trim((string) ($item['label'] ?? ''))) !== '' ? mb_substr($l, 0, 80) : null,
                    'display_order' => $order,
                ]);
            }
        });

        return back()->with('status', 'Dotation oxygène enregistrée.');
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
