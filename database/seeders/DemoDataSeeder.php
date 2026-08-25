<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialType;
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

            // Catégories (regroupement large).
            $imm = MaterialCategory::create(['name' => 'Immobilisation']);
            $oxy = MaterialCategory::create(['name' => 'Oxygénothérapie']);
            $sap = MaterialCategory::create(['name' => 'Secours à personne']);
            $pharma = MaterialCategory::create(['name' => 'Pharmacie']);

            // Types de matériel (Thermomètre, Défibrillateur…) — chaque type fixe
            // son mode de suivi ; les modèles (marque + modèle) s'y rattachent.
            $tCollier = MaterialType::create(['name' => 'Collier cervical', 'category_id' => $imm->id, 'tracking_mode' => 'quantity', 'display_order' => 10]);
            $tCouv = MaterialType::create(['name' => 'Couverture de survie', 'category_id' => $sap->id, 'tracking_mode' => 'quantity', 'display_order' => 20]);
            $tDsa = MaterialType::create(['name' => 'Défibrillateur', 'category_id' => $oxy->id, 'tracking_mode' => 'serial', 'display_order' => 30]);
            $tSerum = MaterialType::create(['name' => 'Sérum physiologique', 'category_id' => $pharma->id, 'tracking_mode' => 'lot', 'display_order' => 40]);

            // Modèles (marque + modèle) + exemplaires / lots à leur emplacement.
            // QUANTITÉ
            Material::create(['name' => 'Perfit ACE', 'brand' => 'Ambu', 'reference' => 'IMM-COL-AD', 'material_type_id' => $tCollier->id, 'category_id' => $imm->id, 'location_id' => $cellule->id, 'tracking_mode' => 'quantity', 'theoretical_qty' => 4, 'minimum_qty' => 2, 'current_qty' => 4, 'status' => 'conforme']);
            Material::create(['name' => 'Couverture isothermique', 'brand' => 'Blizzard', 'reference' => 'SAP-COUV-SURV', 'material_type_id' => $tCouv->id, 'category_id' => $sap->id, 'location_id' => $coffre->id, 'tracking_mode' => 'quantity', 'theoretical_qty' => 5, 'minimum_qty' => 2, 'current_qty' => 5, 'status' => 'conforme']);

            // UNITAIRE (n° de série)
            $dsa = Material::create(['name' => 'AED Plus', 'brand' => 'Zoll', 'reference' => 'OXY-DSA', 'material_type_id' => $tDsa->id, 'category_id' => $oxy->id, 'location_id' => $cellule->id, 'tracking_mode' => 'serial', 'status' => 'conforme']);
            $dsa->items()->create(['serial_number' => 'DSA-00123', 'location_id' => $cellule->id, 'status' => 'conforme', 'next_check_date' => now()->addMonths(6)->toDateString()]);

            // CONSOMMABLE (lot / péremption)
            $serum = Material::create(['name' => 'Sérum physiologique 500 ml', 'brand' => 'B. Braun', 'reference' => 'PHA-SERUM-500', 'material_type_id' => $tSerum->id, 'category_id' => $pharma->id, 'location_id' => $sac->id, 'tracking_mode' => 'lot', 'minimum_qty' => 5, 'status' => 'conforme']);
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
