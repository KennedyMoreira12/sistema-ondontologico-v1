<?php

namespace Database\Seeders;

use App\Models\Clinica;
use App\Models\Professional;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ClinicaSeeder extends Seeder
{
    public function run(): void
    {
        $clinica = Clinica::firstOrCreate(
            ['nome' => 'Clínica Sorriso'],
            [
                'razao_social' => 'Clínica Sorriso Odontologia LTDA',
                'documento' => '00.000.000/0001-00',
                'email' => 'contato@clinicasorriso.com.br',
                'phone' => '(11) 4000-0000',
                'whatsapp' => '(11) 99999-0000',
                'cep' => '00000-000',
                'endereco' => 'Rua Exemplo, 123',
                'bairro' => 'Centro',
                'cidade' => 'São Paulo',
                'estado' => 'SP',
            ]
        );

        $adminRole = Role::where('name', 'admin')->firstOrFail();

        $admin = User::firstOrCreate(
            ['email' => 'admin@sco.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
                'role_id' => $adminRole->id,
                'clinica_id' => $clinica->id,
                'is_active' => true,
            ]
        );

        $profissional = Professional::firstOrCreate(
            ['nome' => 'Dr. João Silva'],
            [
                'clinica_id' => $clinica->id,
                'user_id' => $admin->id,
                'cro' => 'SP-12345',
                'tipo' => 'dentista',
                'is_active' => true,
            ]
        );
    }
}
