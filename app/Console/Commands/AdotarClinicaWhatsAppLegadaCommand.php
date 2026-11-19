<?php

namespace App\Console\Commands;

use App\Models\Central\Clinic;
use App\Services\WhatsApp\ClinicWhatsAppProvisioner;
use Illuminate\Console\Command;

/**
 * Migra UMA clínica legada para o contrato de provisionamento, sem tocar no
 * WhatsApp que já existe.
 *
 * A operação real apenas grava marcadores internos. Instância, token, número,
 * webhook da Evolution e workflow do n8n nunca são alterados. Por isso o comando
 * exige `--force`: uma migração de produção precisa ser uma decisão explícita.
 */
class AdotarClinicaWhatsAppLegadaCommand extends Command
{
    protected $signature = 'whatsapp:clinicas:adotar-legada
        {--clinic= : ID da clínica legada}
        {--dry-run : Apenas mostra o que seria feito}
        {--reverter : Reverte somente os marcadores da adoção}
        {--force : Confirma a gravação (obrigatório sem --dry-run)}
        {--json : Imprime o relatório em JSON}
        {--sem-verificar-evolution : Não consulta a Evolution na prévia}';

    protected $description = 'Adota ou reverte, individualmente, uma clínica WhatsApp legada';

    public function handle(ClinicWhatsAppProvisioner $provisioner): int
    {
        $id = $this->option('clinic');

        if (! is_scalar($id) || trim((string) $id) === '') {
            $this->listarCandidatas();

            return self::FAILURE;
        }

        $clinica = Clinic::query()->find((int) $id);

        if ($clinica === null) {
            $this->components->error("Clínica [{$id}] não encontrada.");

            return self::FAILURE;
        }

        if ($this->option('reverter')) {
            return $this->reverter($clinica, $provisioner);
        }

        $resumo = $provisioner->resumoLegado(
            $clinica,
            ! (bool) $this->option('sem-verificar-evolution')
        );

        $resumo['workflow_path'] = $this->caminhoWorkflow($resumo['n8n_webhook_url'] ?? null);
        $resumo = $this->mascarar($resumo);

        if ($this->option('json')) {
            $this->line((string) json_encode($resumo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->imprimirResumo($resumo);
        }

        if ($this->option('dry-run')) {
            $this->components->warn('Simulação: nenhum dado foi gravado.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->components->error('Use --force para gravar a adoção, ou --dry-run para apenas simular.');

            return self::FAILURE;
        }

        $resultado = $provisioner->adotarLegado($clinica);

        if (! $resultado['ok']) {
            $this->components->error('Adoção recusada: '.(string) $resultado['motivo']);

            return self::FAILURE;
        }

        if (! $resultado['alterado']) {
            $this->components->info('Nada a fazer: a clínica já não é legada.');

            return self::SUCCESS;
        }

        $this->components->info('Clínica legada adotada. Instância, token, número e workflow foram preservados.');
        $this->components->info('Rollback: php artisan whatsapp:clinicas:adotar-legada --clinic='.$clinica->id.' --reverter --force');

        return self::SUCCESS;
    }

    protected function reverter(Clinic $clinica, ClinicWhatsAppProvisioner $provisioner): int
    {
        if (! $this->option('force')) {
            $this->components->error('Use --force para confirmar o rollback da adoção.');

            return self::FAILURE;
        }

        $resultado = $provisioner->reverterAdocaoLegado($clinica);

        $this->components->info($resultado['alterado']
            ? 'Marcadores da adoção removidos. Instância, token, número e workflow intactos.'
            : 'Nada a fazer: esta clínica não estava marcada como legada adotada.');

        return self::SUCCESS;
    }

    protected function listarCandidatas(): void
    {
        $candidatas = Clinic::query()
            ->whereNull('whatsapp_provisioned_at')
            ->whereNotNull('evolution_instance')
            ->orderBy('id')
            ->get(['id', 'name', 'evolution_instance']);

        $this->components->error('Informe --clinic=ID.');
        $this->components->warn('Candidatas a adoção:');

        foreach ($candidatas as $candidata) {
            $this->line($candidata->id.' — '.$candidata->name.' — '.$candidata->evolution_instance);
        }
    }

    /**
     * @param  array<string, mixed>  $resumo
     */
    protected function imprimirResumo(array $resumo): void
    {
        $this->components->info('Clínica: '.(string) $resumo['clinic'].' [id='.(string) $resumo['clinic_id'].']');
        $this->components->info('Instância: '.(string) ($resumo['instance'] ?? '—'));
        $this->components->info('Workflow n8n: '.(string) ($resumo['workflow_path'] ?? '—'));
        $this->components->info('Estado atual no banco: '.(string) ($resumo['estado_atual'] ?? 'não registrado'));
        $this->components->info('Estado informado pela Evolution: '.(string) ($resumo['estado_evolution'] ?? 'não consultado'));
        $this->components->info('Webhook: '.($resumo['webhook']['enabled'] ?? 'não consultado'));

        if ($resumo['erro_verificacao'] !== null) {
            $this->components->warn('Verificação parcial: '.(string) $resumo['erro_verificacao']);
        }

        $this->components->warn('Alterações previstas:');
        foreach ($resumo['alteracoes_previstas'] as $alteracao) {
            $this->line('  - '.$alteracao);
        }

        $this->components->warn('Preservados:');
        foreach ($resumo['preservados'] as $item) {
            $this->line('  - '.$item);
        }

        $this->components->warn('Riscos: nenhum sobre a instância, o número, o token ou o workflow. Se o webhook não estiver ativo, o atendimento continua bloqueado até verificação.');
        $this->components->warn('Rollback: --reverter --force remove só os marcadores gravados por esta adoção.');
    }

    protected function caminhoWorkflow(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $caminho = trim((string) (parse_url($url, PHP_URL_PATH) ?: ''), '/');

        return $caminho === '' ? null : $caminho;
    }

    /**
     * @param  array<string, mixed>  $resumo
     * @return array<string, mixed>
     */
    protected function mascarar(array $resumo): array
    {
        foreach (['n8n_webhook_url'] as $chave) {
            if (isset($resumo[$chave]) && is_string($resumo[$chave])) {
                $resumo[$chave] = $this->mascararUrl($resumo[$chave]);
            }
        }

        if (isset($resumo['webhook']) && is_array($resumo['webhook'])
            && isset($resumo['webhook']['url']) && is_string($resumo['webhook']['url'])) {
            $resumo['webhook']['url'] = $this->mascararUrl($resumo['webhook']['url']);
        }

        return $resumo;
    }

    protected function mascararUrl(string $url): string
    {
        return (string) preg_replace('#://[^/]+#', '://<host>', $url);
    }
}
