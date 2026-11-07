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
        {--instance= : Instância da Evolution API desta clínica}
        {--id= : Reserva um ID específico (somente em local/demo/testing; ex.: 5 para clinic_000005)}';

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

        $idReservado = $this->resolveForcedId();

        if ($idReservado === false) {
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
                'id' => $idReservado,
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

    /**
     * O ID fixo existe para espelhar uma clínica real em ambiente controlado
     * (demo/restauração), onde o nome do banco precisa ser o mesmo, por exemplo
     * `clinic_000005`. Em produção o ID continua sendo gerado pelo banco.
     *
     * @return int|false|null null quando nenhum ID foi pedido
     */
    protected function resolveForcedId(): int|false|null
    {
        $id = $this->option('id');

        if ($id === null || $id === '') {
            return null;
        }

        if (! app()->environment(['local', 'demo', 'testing'])) {
            $this->components->error('A opção --id só pode ser usada em ambientes local, demo ou testing.');

            return false;
        }

        if (filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $this->components->error('O ID da clínica deve ser um número inteiro maior que zero.');

            return false;
        }

        $id = (int) $id;

        if (Clinic::withoutGlobalScopes()->whereKey($id)->exists()) {
            $this->components->error("Já existe uma clínica com o ID {$id}.");

            return false;
        }

        return $id;
    }
}
