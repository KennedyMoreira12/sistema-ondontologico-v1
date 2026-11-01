<?php

namespace App\Services\WhatsApp;

use App\Exceptions\EvolutionIndisponivel;
use App\Models\Central\Clinic;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppOutboxMessage;
use App\Services\EvolutionService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Retrato operacional do WhatsApp de uma clínica.
 *
 * Responde "está tudo de pé?" sem mandar mensagem nenhuma. O painel e o comando
 * `whatsapp:health` usam o mesmo snapshot para não terem duas definições de
 * saudável.
 *
 * A chamada à Evolution é OPCIONAL e só acontece quando quem pergunta pede:
 * `connectionState` é GET, mas ainda é rede — um healthcheck que depende de
 * terceiro fora do ar não pode derrubar o endpoint.
 */
class WhatsAppHealth
{
    public function __construct(protected EvolutionService $evolution) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Clinic $clinic, bool $verificarEvolution = false): array
    {
        $integracao = $clinic->hasEvolutionIntegration();

        $snapshot = [
            'clinic_id' => $clinic->id,
            'instance' => $clinic->evolution_instance,
            'integration_ready' => $integracao,
            'checked_at' => now()->toIso8601String(),
            'outbox' => $this->outboxResumo(),
            'messages' => $this->mensagensResumo(),
            'connection' => null,
        ];

        if ($verificarEvolution) {
            $snapshot['connection'] = $this->estadoDaConexao($clinic, $integracao);
        }

        return $snapshot;
    }

    /**
     * @return array<string, int|string|null>
     */
    protected function outboxResumo(): array
    {
        try {
            $pendentes = WhatsAppOutboxMessage::query()
                ->where('status', WhatsAppOutboxMessage::PENDING)
                ->count();

            $processando = WhatsAppOutboxMessage::query()
                ->where('status', WhatsAppOutboxMessage::PROCESSING)
                ->count();

            $mortas = WhatsAppOutboxMessage::query()
                ->where('status', WhatsAppOutboxMessage::DEAD)
                ->count();

            $enviadas24h = WhatsAppOutboxMessage::query()
                ->where('status', WhatsAppOutboxMessage::SENT)
                ->where('sent_at', '>=', now()->subDay())
                ->count();

            $maisAntiga = WhatsAppOutboxMessage::query()
                ->where('status', WhatsAppOutboxMessage::PENDING)
                ->min('available_at');

            return [
                'pending' => $pendentes,
                'processing' => $processando,
                'dead' => $mortas,
                'sent_last_24h' => $enviadas24h,
                'oldest_pending_seconds' => $maisAntiga === null
                    ? null
                    : max(0, now()->diffInSeconds($maisAntiga, true)),
                'stale_processing' => WhatsAppOutboxMessage::query()
                    ->stale(max(1, (int) config('whatsapp.outbox.stale_minutes', 10)))
                    ->count(),
            ];
        } catch (Throwable $exception) {
            // Banco de clínica ainda sem a migration da outbox: o healthcheck
            // precisa dizer isso, não estourar 500.
            return ['error' => $exception->getMessage()];
        }
    }

    /**
     * @return array<string, int>
     */
    protected function mensagensResumo(): array
    {
        try {
            return [
                'failed_delivery' => WhatsAppMessage::query()
                    ->where('delivery_status', 'failed')
                    ->count(),
                'pending_delivery' => WhatsAppMessage::query()
                    ->where('delivery_status', 'pending')
                    ->count(),
            ];
        } catch (Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function estadoDaConexao(Clinic $clinic, bool $integracao): array
    {
        if (! $integracao) {
            return [
                'state' => null,
                'error' => 'A clínica não tem instância/token da Evolution configurados.',
            ];
        }

        try {
            $resposta = $this->evolution->connectionState($clinic);

            return [
                'state' => $resposta['instance']['state'] ?? $resposta['state'] ?? null,
                'error' => null,
            ];
        } catch (EvolutionIndisponivel $exception) {
            return ['state' => null, 'error' => $exception->getMessage()];
        } catch (Throwable $exception) {
            Log::warning('Healthcheck não conseguiu consultar a Evolution.', [
                'clinic_id' => $clinic->id,
                'exception' => $exception::class,
            ]);

            return ['state' => null, 'error' => 'A Evolution não respondeu.'];
        }
    }
}
