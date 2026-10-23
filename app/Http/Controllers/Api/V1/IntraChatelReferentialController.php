<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IntraChatel\Referentials;
use App\Http\Controllers\Controller;
use App\Models\IntraChatelRecord;
use App\Models\Location;
use App\Models\OxygenBottle;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * API v1 — référentiels intra-Chatel (docs/LIEN-INTRA-CHATEL.md). intra-Chatel
 * publie la liste complète d'un type ; Vulkain en garde une copie en lecture
 * seule et l'associe à ses fiches. Rien n'est jamais renvoyé vers intra-Chatel
 * pour modifier ses données.
 */
class IntraChatelReferentialController extends Controller
{
    /** Publication complète d'un type (remplace la précédente). */
    public function update(Request $request, string $kind): JsonResponse
    {
        $kind = Referentials::KINDS[$kind];
        $input = $request->json()->all();
        $validator = Validator::make($input, Referentials::rules($kind));
        if ($validator->fails()) {
            return response()->json(['error' => 'Données invalides.', 'code' => 'validation_failed', 'errors' => $validator->errors()->toArray()], 422);
        }

        $items = array_map(fn ($item) => ['id' => (string) $item['id']] + $item, array_values($input['data']));

        return response()->json(['data' => Referentials::sync($kind, $items)]);
    }

    /** Copie détenue par Vulkain, avec l'association de chaque fiche (contrôle côté intra-Chatel). */
    public function index(string $kind): JsonResponse
    {
        $kind = Referentials::KINDS[$kind];
        $records = IntraChatelRecord::query()->where('kind', $kind)->orderBy('label')->get();
        $linked = $this->linkedRefs($kind, $records->pluck('linked_id')->filter()->all());

        return response()->json(['data' => $records->map(fn (IntraChatelRecord $r) => [
            'id' => $r->external_id,
            'label' => $r->label,
            'present' => $r->missing_since === null,
            'synced_at' => $r->synced_at?->toIso8601String(),
            'vulkain' => $r->linked_id ? ($linked[$r->linked_id] ?? null) : null,
            'link_mode' => $r->linked_id ? $r->link_mode : null,
        ])->values()]);
    }

    /** Référence publique de la fiche Vulkain associée (jamais d'id interne). */
    private function linkedRefs(string $kind, array $ids): array
    {
        return match ($kind) {
            IntraChatelRecord::KIND_AGENT => User::query()->whereKey($ids)->get(['id', 'name'])
                ->mapWithKeys(fn (User $u) => [$u->id => ['name' => $u->name]])->all(),
            IntraChatelRecord::KIND_VEHICLE => Vehicle::query()->whereKey($ids)->get(['id', 'uuid', 'name', 'callsign'])
                ->mapWithKeys(fn (Vehicle $v) => [$v->id => ['uuid' => $v->uuid, 'callsign' => $v->callsign, 'name' => $v->name]])->all(),
            IntraChatelRecord::KIND_BAG => Location::query()->whereKey($ids)->get(['id', 'name'])
                ->mapWithKeys(fn (Location $l) => [$l->id => ['name' => $l->name]])->all(),
            IntraChatelRecord::KIND_OXYGEN_BOTTLE => OxygenBottle::query()->whereKey($ids)->get(['id', 'serial_number', 'label'])
                ->mapWithKeys(fn (OxygenBottle $b) => [$b->id => ['serial_number' => $b->serial_number, 'label' => $b->label]])->all(),
        };
    }
}
