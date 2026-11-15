<?php

namespace App\Jobs;

use App\Services\Notificacoes\GeradorNotificacoes;
use App\Services\Tenant\TenantRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gera as notificações de UMA clínica.
 *
 * O job carrega apenas o id: um worker atende clínicas diferentes em
 * sequência, e o `TenantRunner` é quem transforma o id na conexão certa e
 * restaura o estado no fim. Carregar model de tenant serializado faria o job
 * seguinte escrever no banco da clínica anterior.
 *
 * `$tries = 1`: a próxima execução agendada já refaz a varredura. Repetir o
 * job não acelera nada e, no caso de erro de banco, só multiplica a falha.
 */
class GerarNotificacoesDaClinica implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $clinicId) {}

    public function handle(TenantRunner $runner, GeradorNotificacoes $gerador): void
    {
        $runner->run($this->clinicId, function () use ($gerador): void {
            $gerador->gerar();
        });
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Falha ao gerar as notificações da clínica.', [
            'clinic_id' => $this->clinicId,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
