<?php

namespace Database\Seeders\tenant;

use Illuminate\Database\Seeder;

/**
 * Seed do BANCO DE UMA CLÍNICA: papéis, permissões e catálogos locais.
 *
 * Este arquivo roda na conexão `tenant` — todo model chamado aqui tem
 * $connection = 'tenant', então o Seeder do Laravel precisa rodar apontando
 * para ela.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            CatalogSeeder::class,
        ]);
    }
}
