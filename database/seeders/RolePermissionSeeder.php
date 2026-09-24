<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['module' => 'pacientes', 'actions' => ['view', 'create', 'update', 'delete']],
            ['module' => 'prontuario', 'actions' => ['view', 'create', 'update', 'delete']],
            ['module' => 'anamnese', 'actions' => ['view', 'create', 'update']],
            ['module' => 'alertas', 'actions' => ['view', 'create', 'update']],
            ['module' => 'odontograma', 'actions' => ['view', 'update']],
            ['module' => 'agenda', 'actions' => ['view', 'create', 'update', 'delete']],
            ['module' => 'tratamentos', 'actions' => ['view', 'create', 'update', 'delete']],
            ['module' => 'procedimentos', 'actions' => ['view', 'create', 'update', 'delete']],
            ['module' => 'orcamentos', 'actions' => ['view', 'create', 'update', 'delete']],
            ['module' => 'financeiro', 'actions' => ['view', 'create', 'update', 'delete', 'receive']],
            ['module' => 'convenios', 'actions' => ['view', 'create', 'update', 'delete']],
            ['module' => 'estoque', 'actions' => ['view', 'create', 'update', 'delete']],
            ['module' => 'profissionais', 'actions' => ['view', 'create', 'update', 'delete']],
            ['module' => 'documentos', 'actions' => ['view', 'create', 'delete']],
            ['module' => 'contratos', 'actions' => ['view', 'create', 'update', 'delete']],
            ['module' => 'relatorios', 'actions' => ['view']],
            ['module' => 'auditoria', 'actions' => ['view']],
            ['module' => 'admin', 'actions' => ['view', 'create', 'update', 'delete']],
        ];

        $permissionRecords = [];
        foreach ($permissions as $module) {
            foreach ($module['actions'] as $action) {
                $permissionRecords[] = Permission::firstOrCreate([
                    'module' => $module['module'],
                    'action' => $action,
                    'name' => $module['module'].'.'.$action,
                    'label' => ucfirst($module['module']).' - '.ucfirst($action),
                ]);
            }
        }

        $roles = [
            'admin' => ['label' => 'Administrador', 'permissions' => 'all'],
            'dentista' => [
                'label' => 'Dentista',
                'permissions' => [
                    'pacientes.view', 'pacientes.update',
                    'prontuario.*', 'anamnese.*', 'alertas.*', 'odontograma.*',
                    'agenda.view', 'agenda.create', 'agenda.update',
                    'tratamentos.*', 'procedimentos.view',
                    'orcamentos.view', 'financeiro.view', 'documentos.*',
                    'contratos.view', 'contratos.create',
                    'relatorios.view',
                ],
            ],
            'recepcao' => [
                'label' => 'Recepção',
                'permissions' => [
                    'pacientes.*',
                    'prontuario.view',
                    'agenda.*',
                    'convenios.view', 'convenios.create',
                    'orcamentos.view', 'financeiro.view', 'financeiro.receive',
                    'estoque.view', 'documentos.view', 'documentos.create', 'contratos.*',
                    'relatorios.view',
                ],
            ],
            'financeiro' => [
                'label' => 'Financeiro',
                'permissions' => [
                    'pacientes.view',
                    'agenda.view',
                    'tratamentos.view', 'orcamentos.*', 'financeiro.*', 'documentos.view',
                    'convenios.view', 'estoque.view', 'contratos.view', 'relatorios.view',
                ],
            ],
            'estoque' => [
                'label' => 'Estoque',
                'permissions' => [
                    'estoque.*', 'pacientes.view', 'agenda.view',
                    'financeiro.view', 'relatorios.view',
                ],
            ],
            'auxiliar' => [
                'label' => 'Auxiliar',
                'permissions' => [
                    'pacientes.view', 'agenda.view', 'prontuario.view',
                ],
            ],
        ];

        $roleRecords = [];
        foreach ($roles as $name => $data) {
            $roleRecords[$name] = Role::firstOrCreate(['name' => $name], ['label' => $data['label']]);
        }

        foreach ($roles as $name => $data) {
            $role = $roleRecords[$name];

            if ($data['permissions'] === 'all') {
                $role->permissions()->sync($permissionRecords);

                continue;
            }

            $ids = collect($data['permissions'])
                ->flatMap(function (string $key) use ($permissionRecords) {
                    if (str_ends_with($key, '.*')) {
                        $module = str_replace('.*', '', $key);

                        return collect($permissionRecords)
                            ->where('module', $module)
                            ->pluck('id');
                    }

                    return collect($permissionRecords)
                        ->where('name', $key)
                        ->pluck('id');
                })
                ->unique()
                ->all();

            $role->permissions()->sync($ids);
        }
    }
}
