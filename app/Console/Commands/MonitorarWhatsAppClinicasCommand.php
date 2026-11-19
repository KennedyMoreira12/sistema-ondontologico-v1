<?php

namespace App\Console\Commands;

use App\Models\Central\Clinic;
use App\Services\WhatsApp\ClinicWhatsAppProvisioner;
use Illuminate\Console\Command;

/**
 * Monitoramento da conexão do WhatsApp de cada clínica.
 *
 * Responde "quem caiu?" sem mandar mensagem: consulta `connectionState` de cada
 * instância e grava o resultado em `clinics.whatsapp_status`. É o mesmo estado
 * que o webhook `CONNECTION_UPDATE` mantém em tempo real — o comando existe para
 * o caso em que o evento não chega (Evolution reiniciada, webhook removido,
 * instância recriada), que é justamente quando ninguém percebe.
 *
 * Não cria, não desconecta e não gera QR: só lê. Uma clínica desconectada
 * continua desconectada, com a diferença de que agora o painel mostra isso.
 */
class MonitorarWhatsAppClinicasCommand extends Command
{
    protected $signature = 'whatsapp:clinicas:monitorar
        {--clinic= : ID de uma clínica específica (padrão: todas as ativas)}
        {--json : Imprimir o resultado em JSON}';

    protected $description = 'Consulta a conexão do WhatsApp de cada clínica e registra queda e erros';

    public function handle(ClinicWhatsAppProvisioner $provisioner): int
    {
        $clinicas = Clinic::query()
            ->where('is_active', true)
            ->when($this->option('clinic'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get();

        if ($clinicas->isEmpty()) {
            $this->components->warn('Nenhuma clínica ativa para monitorar.');

            return self::SUCCESS;
        }

        $resultado = [];
        $falhas = 0;

        foreach ($clinicas as $clinica) {
            $estado = $provisioner->refresh($clinica);
            $clinica->refresh();

            $resultado[] = [
                'clinic_id' => $clinica->id,
                'instance' => $clinica->evolution_instance,
                'estado' => $estado['estado'],
                'estado_atual' => $clinica->whatsapp_current_state,
                'status' => $clinica->whatsapp_status,
                'pronto' => $clinica->whatsappPronto(),
                'ultima_conexao' => $clinica->whatsapp_last_connected_at?->toIso8601String(),
                'confirmado_em' => $clinica->whatsapp_current_state_at?->toIso8601String(),
                'pendencias' => $provisioner->pendencias($clinica),
                'erro' => $estado['erro'],
            ];

            if ($estado['erro'] !== null) {
                $falhas++;
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '[]');

            return $falhas === 0 ? self::SUCCESS : self::FAILURE;
        }

        $this->table(
            ['Clínica', 'Instância', 'Estado atual', 'Status', 'Última conexão', 'Atende', 'Pendências', 'Erro'],
            array_map(fn (array $linha): array => [
                $linha['clinic_id'],
                $linha['instance'] ?? '—',
                $linha['estado_atual'] ?? '—',
                $linha['status'] ?? '—',
                $linha['ultima_conexao'] ?? '—',
                $linha['pronto'] ? 'sim' : 'não',
                $linha['pendencias'] === [] ? '—' : implode(', ', $linha['pendencias']),
                $linha['erro'] ?? '—',
            ], $resultado),
        );

        return $falhas === 0 ? self::SUCCESS : self::FAILURE;
    }
}
