<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'remote_jid', 'etapa', 'dados', 'expires_at',
    'bot_status', 'pausado_ate', 'humano_iniciado_em', 'ia_mensagens', 'ia_ultimo_erro',
])]
class WhatsAppConversation extends TenantModel
{
    protected $table = 'whatsapp_conversations';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dados' => 'array',
            'expires_at' => 'datetime',
            'pausado_ate' => 'datetime',
            'humano_iniciado_em' => 'datetime',
            'ia_mensagens' => 'integer',
        ];
    }

    /**
     * Abre (ou recupera) a conversa deste contato, resetando o ESTADO quando
     * expirada.
     *
     * O reset não toca em `bot_status`: uma conversa sob atendimento humano que
     * fica parada continua sendo do humano, e zerar o status faria o bot voltar
     * a responder no meio de um atendimento que ninguém encerrou.
     */
    public static function abrirPara(string $remoteJid, int $validadeHoras = 4): self
    {
        $conversa = static::firstOrNew(['remote_jid' => $remoteJid]);

        if (! $conversa->exists || $conversa->expires_at?->isPast()) {
            $conversa->etapa = 'consentimento';
            $conversa->dados = null;
            $conversa->ia_mensagens = 0;

            if (! $conversa->exists) {
                $conversa->bot_status = 'ativo';
            }

            $conversa->expires_at = now()->addHours($validadeHoras);
            $conversa->save();
        }

        return $conversa;
    }

    /**
     * Se o bot pode responder automaticamente agora.
     *
     * `pausado` com prazo vencido volta a responder: a pausa por tempo é o que
     * a recepção usa quando sai para o almoço, e é justamente por ter prazo que
     * não precisa de alguém lembrando de retomá-la manualmente. Pausa sem prazo
     * (`pausado_ate` nulo) fica parada até alguém retomar de propósito.
     *
     * A regra anterior respondia `true` para `pausado` sem prazo, contrariando o
     * próprio comentário: uma pausa "até segunda" que perdesse a data voltava a
     * falar com o paciente sozinha.
     */
    public function botAtivo(): bool
    {
        $estado = $this->bot_status ?? 'ativo';

        if (! in_array($estado, config('tenancy.bot_states_ativos', ['ativo']), true)) {
            return false;
        }

        if ($estado === 'pausado') {
            return $this->pausado_ate !== null && ! $this->pausado_ate->isFuture();
        }

        return true;
    }

    /**
     * Devolve ao estado ativo quando a pausa por prazo venceu.
     *
     * `botAtivo()` já responde `true` nesse caso, mas o `bot_status` gravado
     * continuaria `pausado` — e a recepção veria "pausado" numa conversa em que
     * o bot está respondendo. Sincronizar mantém banco e comportamento iguais.
     */
    public function sincronizarPausa(): void
    {
        if ($this->bot_status !== 'pausado') {
            return;
        }

        if ($this->pausado_ate === null || $this->pausado_ate->isFuture()) {
            return;
        }

        $this->retomarBot();
    }

    /**
     * Pausa o bot até um horário. Sem prazo, a pausa é indeterminada.
     */
    public function pausarAte(?CarbonInterface $ate): void
    {
        if ($ate === null) {
            $this->pausarSemPrazo();

            return;
        }

        $this->bot_status = 'pausado';
        $this->pausado_ate = $ate;
        $this->save();
    }

    /**
     * Pausa sem prazo: só uma retomada explícita devolve o bot.
     *
     * É o estado do opt-out — quem pediu para parar não pode voltar a receber
     * mensagem automática porque um prazo venceu.
     */
    public function pausarSemPrazo(): void
    {
        $this->bot_status = 'pausado';
        $this->pausado_ate = null;
        $this->save();
    }

    public function estaPausado(): bool
    {
        return ($this->bot_status ?? 'ativo') === 'pausado';
    }

    public function estaAguardandoAtendente(): bool
    {
        return ($this->bot_status ?? 'ativo') === 'aguardando_atendente';
    }

    /**
     * Marca esta conversa como atendida por um humano e devolve o bot ao silêncio.
     */
    public function assumirPorHumano(): void
    {
        $this->bot_status = 'humano';
        $this->humano_iniciado_em = now();
        $this->pausado_ate = null;
        $this->save();
    }

    /**
     * Devolve a conversa ao bot.
     */
    public function retomarBot(): void
    {
        $this->bot_status = 'ativo';
        $this->humano_iniciado_em = null;
        $this->pausado_ate = null;
        $this->save();
    }
}
