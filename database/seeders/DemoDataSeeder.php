<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Organisation;
use App\Models\ProtocolTemplate;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Données métier de démonstration pour l'organisation « demo » (véhicule,
 * emplacements, catégories, matériels). Idempotent : ne s'exécute qu'une fois.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organisation::query()->where('slug', 'demo')->first();

        if ($org === null) {
            return;
        }

        app(TenantContext::class)->runFor($org, function () {
            if (Vehicle::query()->exists()) {
                return; // déjà peuplé
            }

            $vsav = Vehicle::create([
                'name' => 'VSAV 01', 'type' => 'VSAV', 'callsign' => 'VSAV-01',
                'registration' => 'AA-000-AA', 'status' => 'disponible',
            ]);

            $cellule = Location::create(['vehicle_id' => $vsav->id, 'name' => 'Cellule sanitaire', 'display_order' => 10]);
            $sac = Location::create(['vehicle_id' => $vsav->id, 'name' => 'Sac rouge', 'display_order' => 20]);
            $coffre = Location::create(['vehicle_id' => $vsav->id, 'name' => 'Coffre gauche', 'display_order' => 30]);

            $imm = MaterialCategory::create(['name' => 'Immobilisation']);
            $oxy = MaterialCategory::create(['name' => 'Oxygénothérapie']);
            $sap = MaterialCategory::create(['name' => 'Secours à personne']);
            $pharma = MaterialCategory::create(['name' => 'Pharmacie']);

            // Mode QUANTITÉ
            $collier = Material::create(['name' => 'Collier cervical adulte', 'reference' => 'IMM-COL-AD', 'category_id' => $imm->id, 'location_id' => $cellule->id, 'tracking_mode' => 'quantity', 'theoretical_qty' => 4, 'minimum_qty' => 2, 'current_qty' => 4, 'status' => 'conforme']);
            $couverture = Material::create(['name' => 'Couverture de survie', 'reference' => 'SAP-COUV-SURV', 'category_id' => $sap->id, 'location_id' => $coffre->id, 'tracking_mode' => 'quantity', 'theoretical_qty' => 5, 'minimum_qty' => 2, 'current_qty' => 5, 'status' => 'conforme']);

            // Mode UNITAIRE (n° de série)
            $dsa = Material::create(['name' => 'Défibrillateur DSA', 'reference' => 'OXY-DSA', 'category_id' => $oxy->id, 'location_id' => $cellule->id, 'tracking_mode' => 'serial', 'status' => 'conforme']);
            $dsa->items()->create(['serial_number' => 'DSA-00123', 'location_id' => $cellule->id, 'status' => 'conforme', 'next_check_date' => now()->addMonths(6)->toDateString()]);

            // Mode CONSOMMABLE (lot / péremption)
            $serum = Material::create(['name' => 'Sérum physiologique 500ml', 'reference' => 'PHA-SERUM-500', 'category_id' => $pharma->id, 'location_id' => $sac->id, 'tracking_mode' => 'lot', 'minimum_qty' => 5, 'status' => 'conforme']);
            $serum->lots()->create(['lot_number' => 'LOT-A231', 'quantity' => 8, 'expiry_date' => now()->addDays(20)->toDateString(), 'location_id' => $sac->id, 'status' => 'conforme']);
            $serum->lots()->create(['lot_number' => 'LOT-B118', 'quantity' => 12, 'expiry_date' => now()->addMonths(10)->toDateString(), 'location_id' => $sac->id, 'status' => 'conforme']);

            // Modèle de protocole hebdomadaire du VSAV (inventaire + contrôle véhicule).
            // Cible = tout le matériel embarqué du VSAV (déduit du périmètre).
            ProtocolTemplate::create([
                'vehicle_id' => $vsav->id,
                'name' => 'Protocole hebdomadaire VSAV',
                'types' => ['inventaire', 'controle_vehicule'],
                'scope_type' => 'vehicle',
                'scope_id' => $vsav->id,
                'include_children' => true,
                'frequency' => 'weekly',
            ]);
        });
    }
}
