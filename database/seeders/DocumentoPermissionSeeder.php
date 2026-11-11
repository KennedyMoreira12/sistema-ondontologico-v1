<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class DocumentoPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Permissões do módulo de documentos
        $permissoes = [
            ['name' => 'documentos.view',   'label' => 'Visualizar documentos',   'module' => 'documentos', 'action' => 'view'],
            ['name' => 'documentos.generate', 'label' => 'Gerar documentos (PDF)', 'module' => 'documentos', 'action' => 'generate'],
        ];

        $permissionRecords = [];
        foreach ($permissoes as $perm) {
            $permissionRecords[$perm['name']] = Permission::firstOrCreate(
                ['name' => $perm['name']],
                ['label' => $perm['label'], 'module' => $perm['module'], 'action' => $perm['action']]
            );
        }

        // Associa aos roles que devem ter acesso (admin e dentista pelo menos)
        $roles = Role::whereIn('name', ['admin', 'dentista'])->get();

        foreach ($roles as $role) {
            // Admin tem todas as permissões
            if ($role->name === 'admin') {
                $role->permissions()->syncWithoutDetaching(Permission::all());

                continue;
            }

            // Dentista: view e generate de documentos
            // `syncWithoutDetaching` preserva as permissões que o papel já tem.
            $ids = Permission::where('module', 'documentos')->pluck('id')->all();
            $role->permissions()->syncWithoutDetaching($ids);
        }

        $this->command->info('Permissões de documentos criadas e associadas aos roles.');
    }
}
