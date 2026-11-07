<?php

namespace Database\Seeders\central;

use App\Models\Central\Clinic;
use Illuminate\Database\Seeder;

/**
 * Define as verticais habilitadas para a Clínica Daiane (tenant clinic_000005).
 *
 * Este seeder NÃO é executado automaticamente pelo DatabaseSeeder central.
 * Deve ser chamado explicitamente, após a aplicação das migrações, com
 * autorização específica:
 *
 *   php artisan db:seed --class=Database\Seeders\central\ClinicVerticalsSeeder
 *
 * A clínica Malheiro e demais clínicas continuam com a vertical primária
 * `odontologia` (valor default da coluna `vertical`), sem `verticals` preenchido.
 */
class ClinicVerticalsSeeder extends Seeder
{
    public function run(): void
    {
        $daiane = Clinic::where('name', 'CLINICA DAIANE TEIXEIRA')->first();

        if ($daiane === null) {
            $this->command->warn('Clínica CLINICA DAIANE TEIXEIRA não encontrada. Vertical não configurada.');

            return;
        }

        // Múltiplas verticais simultâneas: fisioterapia, pilates e depilação a laser.
        $daiane->update([
            'verticals' => [
                'fisioterapia',
                'pilates',
                'laser',
            ],
        ]);

        $this->command->info('Verticals configuradas para a Clínica Daiane: fisioterapia, pilates, laser.');
    }
}
