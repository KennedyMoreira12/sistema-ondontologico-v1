<?php

namespace Database\Seeders\tenant;

use Database\Seeders\DocumentoPermissionSeeder;
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

        // A identidade Malheiro existe apenas na clínica 1. Os seeders abaixo
        // dependem do TenantManager estar apontando para essa clínica, o que
        // acontece quando rodamos via `tenants:seed --clinic=1`.
        if (tenant_id() === 1) {
            $this->call([
                DocumentoPermissionSeeder::class,
                MalheiroProfessionalSeeder::class,
            ]);
        }
    }
}
