<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * O modelo de linguagem que interpreta a mensagem do paciente.
 *
 * Ele PROPÕE: devolve uma intenção, um texto de resposta e, no máximo, alguns
 * dados soltos. Ele não agenda, não escreve no banco e não chama ferramenta
 * nenhuma — quem decide o que fazer com a proposta é o `AtendimentoIA`, com as
 * mesmas validações que já existem para o fluxo determinístico. Um modelo que
 * pudesse executar ação seria um modelo que, alucinando, cria consulta.
 *
 * Por isso o catálogo de intenções é fechado. Qualquer coisa que o modelo
 * devolva fora dele vira `null`, e `null` manda o atendimento para o caminho
 * determinístico: a IA nunca é a única porta entre o paciente e a clínica.
 *
 * A chamada é feita com a chave do `.env` (`services.atendimento_ia.api_key`).
 * Sem a chave, este serviço se declara não configurado e nem tenta a rede — o
 * agente é opcional por desenho, e um atendimento que quebra por falta de
 * credencial é pior do que um atendimento sem IA.
 */
class ClienteIA
{
    /**
     * Tudo que a IA pode devolver. O que não está aqui é descartado.
     *
     * @var list<string>
     */
    public const INTENCOES = [
        'saudacao',
        'agendar',
        'horarios',
        'profissionais',
        'procedimentos',
        'falar_humano',
        'duvida',
        'desconhecido',
    ];

    /**
     * O agente está pronto para ser chamado?
     *
     * Exige as duas coisas: a chave ligada e a chave presente. `enabled` é o
     * interruptor do operador; `api_key` é a credencial. Faltando qualquer uma,
     * o atendimento segue pelo fluxo sem IA.
     */
    public function configurado(): bool
    {
        if (! config('services.atendimento_ia.enabled', false)) {
            return false;
        }

        return $this->chave() !== null;
    }

    /**
     * Interpreta a mensagem e devolve a proposta do modelo, ou `null`.
     *
     * `null` é a resposta para QUALQUER falha — provedor fora do ar, chave
     * ausente, JSON inválido, intenção desconhecida. O chamador não precisa
     * distinguir os casos: todos levam ao mesmo lugar, o fluxo determinístico.
     *
     * @param  array<string, mixed>  $contexto  Etapa atual e nome já coletado,
     *                                          o mínimo para o modelo não se
     *                                          perder. Nada de histórico clínico.
     * @return array{intencao: string, resposta: string, dados: array<string, mixed>, tokens_in: int, tokens_out: int}|null
     */
    public function extrairIntencao(string $texto, array $contexto = []): ?array
    {
        $chave = $this->chave();

        if ($chave === null) {
            return null;
        }

        try {
            $resposta = Http::timeout((int) config('services.atendimento_ia.timeout', 30))
                ->withToken($chave)
                ->acceptJson()
                ->post($this->url(), $this->payload($texto, $contexto));
        } catch (ConnectionException $exception) {
            Log::warning('Falha ao falar com o provedor de IA do atendimento.', [
                'motivo' => $exception->getMessage(),
            ]);

            return null;
        } catch (\Throwable $exception) {
            // Provedor devolvendo HTML, TLS, DNS: nada disso pode derrubar o
            // atendimento. O log carrega só o tipo, nunca o corpo da mensagem.
            Log::warning('Erro inesperado ao chamar o provedor de IA do atendimento.', [
                'excecao' => $exception::class,
            ]);

            return null;
        }

        if (! $resposta->successful()) {
            Log::warning('Provedor de IA do atendimento respondeu erro.', [
                'status' => $resposta->status(),
            ]);

            return null;
        }

        return $this->lerResposta($resposta->json());
    }

    /**
     * URL do endpoint de chat completions, tolerante à base já terminar em
     * `/` ou já incluir a rota completa.
     */
    protected function url(): string
    {
        $base = rtrim((string) config('services.atendimento_ia.base_url'), '/');

        return str_ends_with($base, '/chat/completions') ? $base : $base.'/chat/completions';
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @return array<string, mixed>
     */
    protected function payload(string $texto, array $contexto): array
    {
        return [
            'model' => config('services.atendimento_ia.model'),
            'temperature' => (float) config('services.atendimento_ia.temperature', 0.3),
            'max_tokens' => (int) config('services.atendimento_ia.max_tokens', 500),
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $this->promptSistema($contexto)],
                ['role' => 'user', 'content' => $texto],
            ],
        ];
    }

    /**
     * O prompt carrega as regras de negócio que valem para o TEXTO de resposta.
     *
     * As regras aqui não são a fronteira de segurança — a fronteira é o Laravel
     * não aceitar dado solto do modelo. São para o modelo não prometer o que não
     * pode: preço que não conhece, diagnóstico que não é dele para dar, ou uma
     * "confirmação" de agenda que ninguém validou.
     */
    protected function promptSistema(array $contexto): string
    {
        $etapa = is_string($contexto['etapa'] ?? null) ? $contexto['etapa'] : 'menu';
        $nome = is_string($contexto['nome'] ?? null) ? $contexto['nome'] : '';
        $clinica = is_string($contexto['clinica'] ?? null) ? $contexto['clinica'] : 'clínica odontológica';

        $saudacao = $nome !== '' ? " O paciente já disse que se chama {$nome}." : '';

        return <<<PROMPT
        Você é a recepcionista virtual de uma {$clinica} no WhatsApp.{$saudacao}

        Sua função é entender o que o paciente quer e devolver uma proposta em JSON.
        Etapa atual da conversa: {$etapa}.

        Regras:
        - Nunca dê diagnóstico, prescrição ou orientação clínica. Não substitua o dentista.
        - Nunca invente preços, convênios, endereços ou horários. Se não souber, diga que vai verificar com a equipe.
        - Nunca confirme um agendamento: quem confirma é o sistema.
        - Se o paciente pedir para falar com uma pessoa, use a intenção "falar_humano".
        - Se não entender, use "desconhecido".

        Responda SOMENTE com um JSON, sem texto fora dele, neste formato:
        {"intencao": "uma_das_intencoes", "resposta": "texto curto e cordial para o paciente", "dados": {}}

        Intenções válidas: saudacao, agendar, horarios, profissionais, procedimentos, falar_humano, duvida, desconhecido.
        Em "dados", inclua só o que o paciente mencionou de forma explícita (ex.: {"profissional": "Dra. Ana"}). Não invente chaves.
        PROMPT;
    }

    /**
     * @return array{intencao: string, resposta: string, dados: array<string, mixed>, tokens_in: int, tokens_out: int}|null
     */
    protected function lerResposta(mixed $json): ?array
    {
        if (! is_array($json)) {
            return null;
        }

        $conteudo = $json['choices'][0]['message']['content'] ?? null;

        if (! is_string($conteudo) || trim($conteudo) === '') {
            return null;
        }

        $proposta = $this->decodificar($conteudo);

        if ($proposta === null) {
            return null;
        }

        $intencao = $proposta['intencao'] ?? null;

        if (! is_string($intencao) || ! in_array($intencao, self::INTENCOES, true)) {
            Log::info('IA do atendimento devolveu intenção fora do catálogo.', [
                'intencao' => is_scalar($intencao) ? (string) $intencao : gettype($intencao),
            ]);

            return null;
        }

        $resposta = $proposta['resposta'] ?? '';

        return [
            'intencao' => $intencao,
            'resposta' => is_string($resposta) ? trim($resposta) : '',
            'dados' => is_array($proposta['dados'] ?? null) ? $proposta['dados'] : [],
            'tokens_in' => (int) ($json['usage']['prompt_tokens'] ?? 0),
            'tokens_out' => (int) ($json['usage']['completion_tokens'] ?? 0),
        ];
    }

    /**
     * Decodifica o JSON tolerando cercas de código e texto em volta.
     *
     * Mesmo com `response_format`, modelos locais e alguns proxies embrulham o
     * objeto em ```json ... ``` ou acrescentam uma frase. Recortar a primeira
     * chave e o último `}` é o que permite ler a proposta em vez de descartá-la
     * por causa de formatação.
     *
     * @return array<string, mixed>|null
     */
    protected function decodificar(string $conteudo): ?array
    {
        $conteudo = trim($conteudo);

        $inicio = strpos($conteudo, '{');
        $fim = strrpos($conteudo, '}');

        if ($inicio === false || $fim === false || $fim < $inicio) {
            return null;
        }

        $obj = json_decode(substr($conteudo, $inicio, $fim - $inicio + 1), true);

        return is_array($obj) ? $obj : null;
    }

    protected function chave(): ?string
    {
        $chave = config('services.atendimento_ia.api_key');

        return is_string($chave) && trim($chave) !== '' ? trim($chave) : null;
    }
}
