<?php

namespace Database\Seeders;

use App\Models\Central\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@suaempresa.com.br');
        $password = (string) env('ADMIN_PASSWORD', 'admin123');

        $admin = AdminUser::withTrashed()->firstOrNew(['email' => $email]);

        if (! $admin->exists) {
            $admin->fill([
                'name' => env('ADMIN_NAME', 'Administrador'),
                'password' => Hash::make($password),
                'role' => 'super_admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ])->save();
        }

        if (env('APP_ENV') === 'production' && $password === 'admin123') {
            $this->command?->warn('ATENÇÃO: super admin criado com a senha padrão. Troque antes de ir para produção.');
        }
    }
}
