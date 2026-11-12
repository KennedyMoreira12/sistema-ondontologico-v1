<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Central\Clinic;
use App\Models\Paciente;
use App\Models\WhatsAppOutboxMessage;
use App\Services\Tenant\TenantRunner;
use App\Services\WhatsApp\Outbox;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Envia lembretes de consulta com 24h de antecedência.
 *
 * Roda por clínica, com a conexão trocada pelo `TenantRunner`, porque a outbox
 * vive no banco da clínica e o comando não tem tenant no processo.
 *
 * Para cada consulta agendada para amanhã (status agendado ou confirmado),
 * cria uma linha na outbox com o lembrete. O texto inclui um idempotency key
 * baseada na consulta, então reexecutar o comando não duplica lembretes.
 *
 * O paciente pode responder "confirmar" ao lembrete para marcar a consulta
 * como confirmada (tratado na etapa `confirmar_presenca` do fluxo).
 */
class LembreteConsultaCommand extends Command
{
    protected $signature = 'whatsapp:lembretes
        {--clinic= : ID de uma clínica específica (padrão: todas)}
        {--data= : Data alvo no formato YYYY-MM-DD (padrão: amanhã)}';

    protected $description = 'Envia lembretes de consulta para amanhã pelo WhatsApp';

    public function handle(TenantRunner $runner, Outbox $outbox): int
    {
        $alvo = $this->option('data')
            ? Carbon::parse($this->option('data'))->startOfDay()
            : Carbon::tomorrow()->startOfDay();

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
            try {
                $quantidade = $runner->run($clinica, function (Clinic $clinic) use ($outbox, $alvo): int {
                    $statusIds = AppointmentStatus::query()
                        ->whereIn('name', [AppointmentStatus::AGENDADO, AppointmentStatus::CONFIRMADO])
                        ->pluck('id');

                    $consultas = Appointment::query()
                        ->whereIn('appointment_status_id', $statusIds)
                        ->whereDate('inicio', $alvo)
                        ->whereHas('paciente', fn ($query) => $query->whereNotNull('celular'))
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
                        $hora = $consulta->inicio->format('H:i');
                        $dataFormatada = $consulta->inicio->format('d/m');
                        $profissional = $consulta->profissional?->nome ?? 'profissional';
                        $procedimento = $consulta->observacao ?? 'consulta';

                        $texto = "👋 Olá, {$paciente->nome}! Lembrete da sua consulta na {$clinic->name}:\n\n📅 {$dataFormatada} às {$hora}\n🦷 {$procedimento}\n👤 {$profissional}\n\nResponda \"confirmar\" para confirmar presença ou \"menu\" para outras opções.";

                        $outbox->enqueue(
                            $clinic,
                            $paciente->celular,
                            $texto,
                            $chaveIdempotencia,
                            $chaveIdempotencia,
                            'lembrete_24h',
                        );

                        $enviados++;
                    }

                    return $enviados;
                });

                $total += $quantidade;

                if ($quantidade > 0) {
                    $this->components->info("[{$clinica->name}] {$quantidade} lembrete(s) enviado(s).");
                }
            } catch (Throwable $exception) {
                $falhas[] = $clinica->name;

                $this->components->error("[{$clinica->name}] {$exception->getMessage()}");
            }
        }

        $this->components->info("Total de lembretes: {$total}.");

        return $falhas === [] ? self::SUCCESS : self::FAILURE;
    }
}
