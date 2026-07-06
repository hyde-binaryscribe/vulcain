<?php

namespace Database\Seeders;

use App\Models\Organisation;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Données de démonstration.
     *
     * Deux organisations clientes cloisonnées. Les utilisateurs, rôles et
     * données métier seront ajoutés au fil des phases suivantes (auth, RBAC…).
     */
    public function run(): void
    {
        Organisation::query()->firstOrCreate(
            ['slug' => 'demo'],
            ['name' => 'CIS Démonstration', 'status' => Organisation::STATUS_ACTIVE]
        );

        Organisation::query()->firstOrCreate(
            ['slug' => 'caserne-nord'],
            ['name' => 'CIS Nord', 'status' => Organisation::STATUS_ACTIVE]
        );
    }
}
