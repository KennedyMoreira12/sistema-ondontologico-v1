<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Central\Clinic;
use App\Models\WhatsAppOutboxMessage;
use App\Services\Tenant\TenantRunner;
use App\Services\WhatsApp\ClinicFeatures;
use App\Services\WhatsApp\Outbox;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Enfileira lembretes de consulta com 24h de antecedência.
 *
 * Roda por clínica, com a conexão trocada pelo `TenantRunner`, porque a outbox
 * vive no banco da clínica e o comando não tem tenant no processo.
 *
 * O comando NÃO envia nada: ele cria linhas na outbox. Quem entrega é o
 * `whatsapp:outbox:recover`, que já roda a cada minuto. Essa separação é o que
 * permite rodar `--dry-run` em produção sem risco de mandar mensagem.
 *
 * Para cada consulta agendada para o dia alvo (padrão: amanhã), com status
 * agendado ou confirmado, cria uma linha com idempotência por consulta. Rodar
 * o comando de novo não duplica o lembrete.
 *
 * O recurso é opt-in: a clínica precisa ter a flag `reminders` ligada (via
 * `ClinicFeatures`). Sem isso o comando não faz nada, mesmo com Evolution
 * configurada.
 *
 * O paciente responde "confirmar" para marcar a consulta como confirmada
 * (tratado no `FluxoAtendimento`).
 */
class LembreteConsultaCommand extends Command
{
    protected $signature = 'whatsapp:lembretes
        {--clinic= : ID de uma clínica específica (padrão: todas)}
        {--data= : Data alvo no formato YYYY-MM-DD (padrão: amanhã)}
        {--dry-run : Mostra o que seria enfileirado, sem gravar nada}';

    protected $description = 'Enfileira lembretes de consulta para amanhã pelo WhatsApp';

    public function handle(TenantRunner $runner, Outbox $outbox, ClinicFeatures $features): int
    {
        $alvo = $this->option('data')
            ? Carbon::parse($this->option('data'))->startOfDay()
            : Carbon::tomorrow()->startOfDay();

        $simulacao = (bool) $this->option('dry-run');

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

        $total = 0;
        $falhas = [];

        foreach ($clinicas as $clinica) {
            // O recurso é opt-in por clínica: a flag nasce desligada e só quem
            // decide ligar recebe lembrete. Sem esta checagem, o comando novo
            // passaria a mandar mensagem em produção sem ninguém ter ligado.
            if (! $features->enabled($clinica, 'reminders')) {
                $this->components->warn("[{$clinica->name}] recurso de lembretes desligado; ignorado.");

                continue;
            }

            // Sem instância/token da Evolution a outbox não tem como entregar.
            // Enfileirar aqui só encheria a fila de mensagens mortas.
            if (! $clinica->hasEvolutionIntegration()) {
                $this->components->warn("[{$clinica->name}] sem integração com a Evolution; lembretes ignorados.");

                continue;
            }

            try {
                $quantidade = $runner->run($clinica, function (Clinic $clinic) use ($outbox, $alvo, $simulacao): int {
                    $statusIds = AppointmentStatus::query()
                        ->whereIn('name', [AppointmentStatus::AGENDADO, AppointmentStatus::CONFIRMADO])
                        ->pluck('id');

                    $consultas = Appointment::with(['paciente', 'profissional'])
                        ->whereIn('appointment_status_id', $statusIds)
                        ->whereDate('inicio', $alvo)
                        ->whereHas('paciente', fn ($query) => $query
                            ->whereNotNull('celular')
                            ->where('celular', '!=', ''))
                        ->orderBy('inicio')
                        ->get();

                    $enviados = 0;

                    foreach ($consultas as $consulta) {
                        $chaveIdempotencia = "lembrete_24h_{$consulta->id}";

                        $jaEnviado = WhatsAppOutboxMessage::query()
                            ->where('correlation_id', $chaveIdempotencia)
                            ->exists();

                        if ($jaEnviado) {
                            continue;
                        }

                        $paciente = $consulta->paciente;

                        if ($paciente === null) {
                            continue;
                        }

                        $hora = $consulta->inicio->format('H:i');
                        $dataFormatada = $consulta->inicio->format('d/m');
                        $profissional = $consulta->profissional?->nome ?? 'profissional';
                        $assunto = $consulta->titulo ?: ($consulta->observacao ?: 'consulta');

                        $texto = "Olá, {$paciente->nome}! Lembrete da sua consulta na {$clinic->name}:\n\n"
                            ."📅 {$dataFormatada} às {$hora}\n"
                            ."📋 {$assunto}\n"
                            ."👤 {$profissional}\n\n"
                            .'Responda "confirmar" para confirmar presença ou "menu" para outras opções.';

                        if (! $simulacao) {
                            $outbox->enqueue(
                                $clinic,
                                $paciente->celular,
                                $texto,
                                $chaveIdempotencia,
                                $chaveIdempotencia,
                                'lembrete_24h',
                            );
                        }

                        $enviados++;
                    }

                    return $enviados;
                });

                $total += $quantidade;

                if ($quantidade > 0) {
                    $rotulo = $simulacao ? 'lembrete(s) a enfileirar' : 'lembrete(s) enfileirado(s)';

                    $this->components->info("[{$clinica->name}] {$quantidade} {$rotulo}.");
                }
            } catch (Throwable $exception) {
                $falhas[] = $clinica->name;

                $this->components->error("[{$clinica->name}] {$exception->getMessage()}");
            }
        }

        $rotuloTotal = $simulacao ? 'Total de lembretes a enfileirar' : 'Total de lembretes enfileirados';

        $this->components->info("{$rotuloTotal}: {$total}.");

        return $falhas === [] ? self::SUCCESS : self::FAILURE;
    }
}
