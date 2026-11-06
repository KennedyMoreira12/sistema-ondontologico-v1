<?php

namespace App\Services\WhatsApp;

use App\Models\Central\Clinic;
use App\Services\Tenant\TenantManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Expediente da clínica e resposta de ausência.
 *
 * O fuso vem de `clinics.timezone`, nunca de `config('app.timezone')` fixo: a
 * mesma instalação atende clínica em Manaus e em São Paulo, e "aberto às 18h"
 * precisa significar 18h no relógio da clínica.
 *
 * O expediente é uma configuração por clínica em `clinic_settings`, no formato
 * `{"1": [["08:00","12:00"],["14:00","18:00"]], ...}` com a chave sendo o dia
 * ISO (1 = segunda, 7 = domingo). Ausência de configuração significa 24/7 — o
 * mesmo comportamento de antes, para a clínica que nunca configurou não parar
 * de responder de uma hora para outra.
 *
 * A resposta de ausência tem cooldown: sem ele, cada mensagem do paciente
 * receberia o mesmo aviso, o que é pior do que o silêncio.
 */
class Expediente
{
    public function __construct(
        protected ConfiguracaoWhatsApp $configuracao,
        protected TenantManager $tenants,
    ) {}

    /**
     * A clínica está aberta agora?
     */
    public function abertoAgora(?Clinic $clinica = null): bool
    {
        $horarios = $this->horarios();

        if ($horarios === null) {
            return (bool) config('whatsapp.business_hours.default_open', true);
        }

        $agora = Carbon::now($this->fuso($clinica));
        $dia = (string) $agora->isoWeekday();

        foreach ($horarios[$dia] ?? [] as $faixa) {
            [$inicio, $fim] = $faixa;

            if ($this->dentroDaFaixa($agora, $inicio, $fim)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Próximo horário em que a clínica abre, no fuso dela.
     *
     * Serve de prazo para a pausa por ausência: o bot volta sozinho quando a
     * clínica abre, sem depender de alguém clicar em "retomar". Devolve `null`
     * quando não há expediente configurado (24/7) ou quando nenhuma faixa dos
     * próximos dias é válida — nesse caso quem chama deve pausar sem prazo.
     */
    public function proximaAbertura(?Clinic $clinica = null): ?Carbon
    {
        $horarios = $this->horarios();

        if ($horarios === null) {
            return null;
        }

        $agora = Carbon::now($this->fuso($clinica));

        for ($dia = 0; $dia < 8; $dia++) {
            $data = $agora->copy()->addDays($dia);
            $faixas = $horarios[(string) $data->isoWeekday()] ?? [];

            foreach ($faixas as [$inicio, $fim]) {
                $abertura = $data->copy()->setTimeFromTimeString($inicio);

                if ($abertura->greaterThan($agora)) {
                    return $abertura;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, list<array{0: string, 1: string}>>|null
     */
    public function horarios(): ?array
    {
        $bruto = $this->configuracao->json(ConfiguracaoWhatsApp::EXPEDIENTE);

        if ($bruto === null) {
            return null;
        }

        $horarios = [];

        foreach ($bruto as $dia => $faixas) {
            if (! is_numeric($dia) || (int) $dia < 1 || (int) $dia > 7 || ! is_array($faixas)) {
                continue;
            }

            $validas = [];

            foreach ($faixas as $faixa) {
                if (! is_array($faixa) || count($faixa) < 2) {
                    continue;
                }

                [$inicio, $fim] = array_values($faixa);

                if ($this->horaValida($inicio) && $this->horaValida($fim) && $inicio < $fim) {
                    $validas[] = [(string) $inicio, (string) $fim];
                }
            }

            $horarios[(string) (int) $dia] = $validas;
        }

        return $horarios;
    }

    public function mensagemAusencia(): string
    {
        return (string) $this->configuracao->texto(
            ConfiguracaoWhatsApp::MENSAGEM_AUSENCIA,
            (string) config('whatsapp.business_hours.absence_message'),
        );
    }

    /**
     * Deve responder o aviso de ausência para esta conversa agora?
     *
     * `Cache::add` é atômico: devolve `true` só para quem criou a chave, então
     * duas mensagens simultâneas não geram dois avisos.
     */
    public function deveAvisarAusencia(string $remoteJid): bool
    {
        $minutos = max(1, (int) config('whatsapp.business_hours.absence_reply_cooldown_minutes', 60));

        return Cache::add(
            "whatsapp:ausencia:{$this->tenants->id()}:{$remoteJid}",
            true,
            now()->addMinutes($minutos),
        );
    }

    /**
     * Limpa o cooldown, usado quando a clínica volta a abrir e o paciente
     * escreve de novo.
     */
    public function limparAviso(string $remoteJid): void
    {
        Cache::forget("whatsapp:ausencia:{$this->tenants->id()}:{$remoteJid}");
    }

    protected function fuso(?Clinic $clinica): string
    {
        $fuso = $clinica?->timezone ?? $this->tenants->current()?->timezone;

        return is_string($fuso) && $fuso !== '' ? $fuso : (string) config('app.timezone', 'UTC');
    }

    protected function dentroDaFaixa(Carbon $agora, string $inicio, string $fim): bool
    {
        $hora = $agora->format('H:i');

        return $hora >= $inicio && $hora < $fim;
    }

    protected function horaValida(mixed $hora): bool
    {
        return is_string($hora) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora) === 1;
    }
}
