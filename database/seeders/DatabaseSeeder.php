<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seed do BANCO CENTRAL: planos, limites de recurso e o super admin da
 * plataforma. Nada de dado clínico aqui.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
