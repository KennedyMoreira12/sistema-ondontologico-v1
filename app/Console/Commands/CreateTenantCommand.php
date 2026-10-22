<?php

namespace App\Console\Commands;

use App\Models\Central\Clinic;
use App\Models\Central\Plan;
use App\Services\Tenant\TenantProvisioner;
use App\Support\EvolutionInstance;
use Illuminate\Console\Command;
use Throwable;

class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create
        {name : Nome da clínica}
        {--email= : E-mail do administrador da clínica}
        {--admin= : Nome do administrador}
        {--plan= : Slug do plano (padrão: o primeiro plano ativo)}
        {--password= : Senha do administrador (padrão: gerada)}
        {--vertical= : Vertical da clínica (odontologia ou fisioterapia; padrão: odontologia)}
        {--instance= : Instância da Evolution API desta clínica}';

    protected $description = 'Cria uma clínica com banco de dados próprio, papéis, admin e assinatura';

    public function handle(TenantProvisioner $provisioner): int
    {
        $name = (string) $this->argument('name');
        $email = (string) ($this->option('email') ?? $this->ask('E-mail do administrador'));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->components->error('E-mail inválido.');

            return self::FAILURE;
        }

        if (Clinic::withoutGlobalScopes()->where('email', $email)->exists()) {
            $this->components->error("Já existe uma clínica com o e-mail {$email}.");

            return self::FAILURE;
        }

        $plan = $this->resolvePlan();

        if ($plan === null) {
            $this->components->error('Nenhum plano ativo. Rode `php artisan db:seed --class=PlanSeeder` primeiro.');

            return self::FAILURE;
        }

        $instancia = EvolutionInstance::normalize($this->option('instance'));

        if ($this->option('instance') !== null && $instancia === null) {
            $this->components->error('A instância da Evolution ficou vazia depois de normalizada.');

            return self::FAILURE;
        }

        if ($instancia !== null && Clinic::withoutGlobalScopes()->where('evolution_instance', $instancia)->exists()) {
            $this->components->error("A instância [{$instancia}] já pertence a outra clínica.");

            return self::FAILURE;
        }

        $this->components->info("Criando a clínica [{$name}] no plano [{$plan->name}]...");

        try {
            $result = $provisioner->provision([
                'name' => $name,
                'email' => $email,
                'admin_name' => $this->option('admin') ?: $name,
                'password' => $this->option('password'),
                'evolution_instance' => $instancia,
                'vertical' => $this->option('vertical') ?? 'odontologia',
                'plan' => $plan,
            ]);
        } catch (Throwable $exception) {
            $this->components->error('Falha ao criar a clínica: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Clínica criada com sucesso.');
        $this->components->twoColumnDetail('Banco', $result['connection']->database_name);
        $this->components->twoColumnDetail('Vertical', $result['clinic']->vertical ?? 'odontologia');
        $this->components->twoColumnDetail('Plano', $plan->name.' ('.$plan->monthlyPrice().')');
        $this->components->twoColumnDetail('Instância', $result['clinic']->evolution_instance ?? '(nenhuma)');
        $this->components->twoColumnDetail('Administrador', $result['user']->email);
        $this->components->twoColumnDetail('Senha', $result['password']);
        $this->newLine();
        $this->components->warn('A senha aparece só agora. Guarde-a com segurança.');

        return self::SUCCESS;
    }

    protected function resolvePlan(): ?Plan
    {
        $slug = $this->option('plan');

        if ($slug) {
            return Plan::query()->where('slug', $slug)->where('is_active', true)->first();
        }

        return Plan::query()->where('is_active', true)->orderBy('sort_order')->first();
    }
}
