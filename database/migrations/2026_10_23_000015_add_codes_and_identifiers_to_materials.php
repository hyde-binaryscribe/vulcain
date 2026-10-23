<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Identifiants matériel : chaque TYPE de matériel porte un code court unique
 * (ex. BRCD), et chaque matériel reçoit un identifiant auto « CODE_VEHICULE_NN »
 * (ex. BRCD_A09_01). Rétro-remplit les données existantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_types', function (Blueprint $table) {
            if (! Schema::hasColumn('material_types', 'code')) {
                $table->string('code', 8)->nullable()->after('name');
            }
        });
        Schema::table('materials', function (Blueprint $table) {
            if (! Schema::hasColumn('materials', 'identifier')) {
                $table->string('identifier', 40)->nullable()->after('name');
                $table->index(['organisation_id', 'identifier'], 'materials_org_identifier_idx');
            }
        });

        $makeCode = function (string $name, array $taken): string {
            $letters = preg_replace('/[^A-Z]/', '', strtoupper(Str::ascii($name)));
            $cons = preg_replace('/[AEIOUY]/', '', $letters);
            $base = substr(($cons !== '' ? $cons : $letters), 0, 3);
            if ($base === '') {
                $base = 'MAT';
            }
            $code = $base;
            $i = 1;
            while (in_array($code, $taken, true)) {
                $code = $base.$i;
                $i++;
            }

            return $code;
        };

        // Codes des types de matériel (uniques par organisation).
        $takenByOrg = [];
        foreach (DB::table('material_types')->orderBy('id')->get(['id', 'organisation_id', 'name', 'code']) as $t) {
            if (! isset($takenByOrg[$t->organisation_id])) {
                $takenByOrg[$t->organisation_id] = DB::table('material_types')
                    ->where('organisation_id', $t->organisation_id)->whereNotNull('code')
                    ->pluck('code')->map(fn ($c) => strtoupper($c))->all();
            }
            if (! empty($t->code)) {
                continue;
            }
            $code = $makeCode($t->name, $takenByOrg[$t->organisation_id]);
            DB::table('material_types')->where('id', $t->id)->update(['code' => $code]);
            $takenByOrg[$t->organisation_id][] = $code;
        }

        // Identifiants des matériels existants.
        $seq = [];
        foreach (DB::table('materials')->whereNull('deleted_at')->orderBy('id')
            ->get(['id', 'material_type_id', 'location_id', 'name', 'identifier']) as $m) {
            if (! empty($m->identifier)) {
                continue;
            }
            $code = $m->material_type_id
                ? strtoupper((string) DB::table('material_types')->where('id', $m->material_type_id)->value('code'))
                : '';
            if ($code === '') {
                $code = $makeCode($m->name, []);
            }

            $vehicleId = $m->location_id ? DB::table('locations')->where('id', $m->location_id)->value('vehicle_id') : null;
            $veh = 'X';
            if ($vehicleId) {
                $v = DB::table('vehicles')->where('id', $vehicleId)->first(['callsign', 'name']);
                $veh = strtoupper(preg_replace('/[^A-Z0-9]/', '', Str::ascii($v->callsign ?: ($v->name ?? '')))) ?: 'X';
            }

            $key = $vehicleId.'|'.($m->material_type_id ?: 'n:'.$m->name);
            $seq[$key] = ($seq[$key] ?? 0) + 1;
            $ident = $code.'_'.$veh.'_'.str_pad((string) $seq[$key], 2, '0', STR_PAD_LEFT);
            DB::table('materials')->where('id', $m->id)->update(['identifier' => $ident]);
        }
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            if (Schema::hasColumn('materials', 'identifier')) {
                $table->dropIndex('materials_org_identifier_idx');
                $table->dropColumn('identifier');
            }
        });
        Schema::table('material_types', function (Blueprint $table) {
            if (Schema::hasColumn('material_types', 'code')) {
                $table->dropColumn('code');
            }
        });
    }
};
