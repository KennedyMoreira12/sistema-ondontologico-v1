<?php

namespace Database\Seeders;

use App\Models\Central\Clinic;
use Illuminate\Database\Seeder;

/**
 * Popula os campos de branding da Clínica Malheiro (clinic_id = 1) no banco central.
 * Deve rodar após a criação da clínica no banco central.
 */
class MalheiroClinicBrandingSeeder extends Seeder
{
    public function run(): void
    {
        // Só roda para clinic_id = 1
        $clinica = Clinic::find(1);

        if (! $clinica) {
            $this->command->warn('Clínica ID 1 não encontrada. Pulando seed de branding Malheiro.');

            return;
        }

        // Só atualiza se for a Clínica Malheiro (verifica pelo nome ou vertical)
        if ($clinica->name !== 'Malheiro' && $clinica->vertical !== 'odontologia') {
            $this->command->info('Clínica ID 1 não é a Malheiro. Pulando seed de branding.');

            return;
        }

        $atualizou = false;

        // Frase institucional
        if (empty($clinica->frase_institucional)) {
            $clinica->frase_institucional = 'Porque ninguém resiste a um belo sorriso...';
            $atualizou = true;
        }

        // Cores da identidade Malheiro (teal/turquesa)
        if (empty($clinica->primary_color) || $clinica->primary_color === '#2563eb') {
            $clinica->primary_color = '#0ca678'; // teal
            $atualizou = true;
        }

        if (empty($clinica->secondary_color) || $clinica->secondary_color === '#1e40af') {
            $clinica->secondary_color = '#0b7285'; // azul-teal escuro
            $atualizou = true;
        }

        if ($atualizou) {
            $clinica->save();
            $this->command->info('Branding Malheiro aplicado na clínica ID 1:');
            $this->command->info("  - Frase: {$clinica->frase_institucional}");
            $this->command->info("  - Cor primária: {$clinica->primary_color}");
            $this->command->info("  - Cor secundária: {$clinica->secondary_color}");
        } else {
            $this->command->info('Branding Malheiro já configurado na clínica ID 1.');
        }
    }
}
