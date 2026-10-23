<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Les matériels non liés à un véhicule ne portent plus le segment véhicule dans
 * leur identifiant : « CODE_X_NN » devient « CODE_NN ». On ne touche qu'aux
 * matériels dont l'emplacement n'est rattaché à aucun véhicule (ou sans
 * emplacement), le numéro d'ordre est conservé.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('materials')
            ->leftJoin('locations', 'materials.location_id', '=', 'locations.id')
            ->whereNull('materials.deleted_at')
            ->whereNotNull('materials.identifier')
            ->where(function ($q) {
                $q->whereNull('materials.location_id')->orWhereNull('locations.vehicle_id');
            })
            ->get(['materials.id', 'materials.identifier']);

        foreach ($rows as $m) {
            $new = preg_replace('/^(.+)_X_(\d+)$/', '$1_$2', (string) $m->identifier);
            if ($new !== null && $new !== $m->identifier) {
                DB::table('materials')->where('id', $m->id)->update(['identifier' => $new]);
            }
        }
    }

    public function down(): void
    {
        // Transformation non réversible de façon fiable : on ne reconstruit pas
        // le segment véhicule « _X » retiré.
    }
};
