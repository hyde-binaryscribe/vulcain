<?php

namespace Database\Seeders;

use App\Domain\Billing\Plan;
use App\Domain\Billing\SubscriptionStatus;
use App\Domain\Identity\RoleProvisioner;
use App\Domain\Sectors\Sector;
use App\Models\Organisation;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Données de démonstration : organisations de différents secteurs, avec leurs
     * rôles provisionnés. Les utilisateurs se créent via `vulcain:create-user`.
     */
    public function run(): void
    {
        $provisioner = app(RoleProvisioner::class);

        $organisations = [
            ['slug' => 'demo', 'name' => 'CIS Démonstration', 'sector' => Sector::SDIS],
            ['slug' => 'caserne-nord', 'name' => 'CIS Nord', 'sector' => Sector::SDIS],
            ['slug' => 'ambulance-sud', 'name' => 'Ambulances du Sud', 'sector' => Sector::AMBULANCE_PRIVEE],
            ['slug' => 'protection-civile', 'name' => 'Protection Civile', 'sector' => Sector::AASC],
        ];

        foreach ($organisations as $data) {
            $organisation = Organisation::query()->firstOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'sector' => $data['sector'],
                    'status' => Organisation::STATUS_ACTIVE,
                ],
            );

            $provisioner->provision($organisation);

            // Abonnement par défaut (idempotent) — plan Découverte en essai.
            $organisation->subscription()->firstOrCreate([], [
                'plan' => Plan::DECOUVERTE->value,
                'status' => SubscriptionStatus::TRIAL->value,
                'trial_ends_at' => now()->addDays(30),
                'current_period_end' => now()->addDays(30),
            ]);
        }

        $this->call(DemoDataSeeder::class);
    }
}
