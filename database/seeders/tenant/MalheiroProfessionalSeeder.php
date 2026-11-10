<?php

namespace Database\Seeders\tenant;

use App\Models\Professional;
use App\Models\Specialty;
use Illuminate\Database\Seeder;

/**
 * Cria o profissional Dr. Alessandro Agostinho Malheiro no tenant 1.
 * Deve rodar no banco da clínica 1 (tenant database).
 */
class MalheiroProfessionalSeeder extends Seeder
{
    public function run(): void
    {
        // SÓ roda para clinic_id = 1 (Clínica Malheiro real)
        // Em testes e outras clínicas, não cria o profissional
        if (tenant_id() !== 1) {
            return;
        }

        // Verifica se já existe
        $existente = Professional::where('cro', '20.545')
            ->where('nome', 'Dr. Alessandro Agostinho Malheiro')
            ->first();

        if ($existente) {
            $this->command->info('Profissional Malheiro já existe.');

            return;
        }

        // Busca ou cria especialidades
        $especialidades = [
            'ESPECIALISTA EM IMPLANTES',
            'DENTÍSTICA RESTAURADORA E ESTÉTICA',
        ];

        $especialidadeModels = [];
        foreach ($especialidades as $nome) {
            $esp = Specialty::firstOrCreate(['name' => $nome]);
            $especialidadeModels[] = $esp;
        }

        // Cria o profissional SEM user_id (o admin user é criado depois do seed)
        // O user_id pode ser preenchido depois manualmente ou via atualização
        $profissional = Professional::create([
            'clinica_id' => 1,
            'user_id' => null,
            'nome' => 'Dr. Alessandro Agostinho Malheiro',
            'cro' => '20.545',
            'registro_profissional' => '20.545',
            'conselho_regional' => 'CRO-MG',
            'tipo' => 'dentista',
            'comissao' => 0,
            'is_active' => true,
        ]);

        // Associa especialidades
        $profissional->specialties()->sync(
            Specialty::whereIn('name', [
                'ESPECIALISTA EM IMPLANTES',
                'DENTÍSTICA RESTAURADORA E ESTÉTICA',
            ])->pluck('id')
        );

        $this->command->info('Profissional Malheiro criado: Dr. Alessandro Agostinho Malheiro');
        $this->command->info('CRO: CRO-MG 20.545');
        $this->command->info('Especialidades: ESPECIALISTA EM IMPLANTES, DENTÍSTICA RESTAURADORA E ESTÉTICA');
    }
}
