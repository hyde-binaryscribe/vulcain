<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;

/**
 * API v1 — véhicules de l'organisation du jeton, en lecture (lien Itineris).
 * Seul l'uuid public identifie un véhicule ; l'id interne ne sort jamais.
 */
class VehicleController extends Controller
{
    public function index(): JsonResponse
    {
        $vehicles = Vehicle::query()
            ->with(['vehicleModel:id,name', 'site:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Vehicle $v) => $this->present($v));

        return response()->json(['data' => $vehicles]);
    }

    public function show(string $uuid): JsonResponse
    {
        $vehicle = Vehicle::query()->withTrashed()
            ->with(['vehicleModel:id,name', 'site:id,name'])
            ->where('uuid', $uuid)
            ->first();

        if (! $vehicle) {
            return response()->json(['error' => 'Véhicule introuvable.', 'code' => 'not_found'], 404);
        }

        return response()->json(['data' => $this->present($vehicle)]);
    }

    /** Champs exposés (contrat : docs/LIEN-ITINERIS.md). */
    private function present(Vehicle $v): array
    {
        return [
            'uuid' => $v->uuid,
            'version' => (int) $v->version,
            'name' => $v->name,
            'callsign' => $v->callsign,
            'type' => $v->type,
            'registration' => $v->registration,
            'vin' => $v->vin,
            'model' => $v->vehicleModel?->name,
            'site' => $v->site?->name,
            'commissioned_at' => $v->commissioned_at?->toDateString(),
            'mileage' => $v->mileage,
            'telematics_imei' => $v->telematics_imei,
            'status' => $v->status?->value,
            'deleted' => $v->trashed(),
            'updated_at' => $v->updated_at?->toIso8601String(),
        ];
    }
}
