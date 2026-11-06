<?php

namespace App\Console\Commands;

use App\Models\Central\Clinic;
use App\Services\Tenant\TenantRunner;
use App\Services\WhatsApp\Outbox;
use Illuminate\Console\Command;
use Throwable;

/**
 * Devolve à fila o que ficou pendente na outbox.
 *
 * Cobre dois casos que o envio inline não cobre:
 *
 * 1. job perdido (worker reiniciou, fila foi limpa, job falhou antes de rodar);
 * 2. linha presa em `processing` porque o worker morreu no meio do envio.
 *
 * O comando NÃO envia nada por conta própria: ele reenfileira. Com fila `sync`
 * o job roda na hora; com worker, é o worker que envia.
 *
 * Roda por clínica, com a conexão trocada pelo `TenantRunner`, porque a outbox
 * vive no banco da clínica e o comando não tem tenant no processo.
 */
class RecoverWhatsAppOutboxCommand extends Command
{
    protected $signature = 'whatsapp:outbox:recover
        {--clinic= : ID de uma clínica específica (padrão: todas)}
        {--limit=200 : Máximo de linhas por clínica nesta execução}';

    protected $description = 'Reenfileira mensagens pendentes/órfãs da outbox do WhatsApp';

    public function handle(TenantRunner $runner, Outbox $outbox): int
    {
        $clinicas = Clinic::query()
            ->where('is_active', true)
            ->whereHas('tenantConnection', fn ($query) => $query->where('is_active', true))
            ->when($this->option('clinic'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get();

        if ($clinicas->isEmpty()) {
            $this->components->warn('Nenhuma clínica ativa com banco configurado.');

            return self::SUCCESS;
        }

        $limite = max(1, (int) $this->option('limit'));
        $total = 0;
        $falhas = [];

        foreach ($clinicas as $clinica) {
            try {
                $quantidade = $runner->run($clinica, function (Clinic $clinic) use ($outbox, $limite): int {
                    $linhas = $outbox->recover($limite);

                    foreach ($linhas as $linha) {
                        $outbox->dispatch($clinic, $linha);
                    }

                    return count($linhas);
                });

                $total += $quantidade;

                if ($quantidade > 0) {
                    $this->components->info("[{$clinica->name}] {$quantidade} mensagem(ns) reenfileirada(s).");
                }
            } catch (Throwable $exception) {
                $falhas[] = $clinica->name;

                $this->components->error("[{$clinica->name}] {$exception->getMessage()}");
            }
        }

        $this->components->info("Total reenfileirado: {$total}.");

        return $falhas === [] ? self::SUCCESS : self::FAILURE;
    }
}
