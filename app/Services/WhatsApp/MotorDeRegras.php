<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppRule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Avalia as regras automáticas da clínica.
 *
 * A ordem é prioridade e, no empate, id — nunca a ordem que o banco devolveu.
 * A primeira regra que casa responde; não há acumulação de respostas, porque
 * duas respostas para a mesma mensagem confundem mais do que ajudam.
 *
 * Escopo é o que impede a regra de atropelar um passo com contrato: no meio do
 * cadastro ou da escolha de horário o texto do paciente é DADO do passo, não
 * uma pergunta livre. Regra de escopo `menu` só vale na etapa de menu; a de
 * escopo `livre` também não entra nesses passos.
 *
 * Regex é conteúdo digitado por operador. Por isso o padrão é validado antes de
 * rodar (delimitador, comprimento, `preg_match` sem warning) e um padrão
 * quebrado é ignorado com log — um erro de digitação não pode derrubar o
 * atendimento de todos os pacientes.
 */
class MotorDeRegras
{
    /**
     * Etapas com contrato próprio: o texto ali é resposta de um passo, e uma
     * regra não pode sequestrá-la.
     *
     * @var list<string>
     */
    public const ETAPAS_COM_CONTRATO = [
        'consentimento',
        'cadastro',
        'profissional',
        'procedimento',
        'data',
        'horario',
    ];

    /**
     * @return array{regra: WhatsAppRule, resposta: string}|null
     */
    public function procurar(string $texto, string $etapa = 'menu'): ?array
    {
        $texto = trim($texto);

        if ($texto === '' || ! $this->podeAvaliar($etapa)) {
            return null;
        }

        foreach ($this->candidatas($etapa) as $regra) {
            if ($this->casa($regra, $texto)) {
                return ['regra' => $regra, 'resposta' => $regra->resposta];
            }
        }

        return null;
    }

    /**
     * A regra está bem formada? Usado pelo cadastro e pelos testes, para que um
     * padrão inválido seja recusado na origem em vez de virar log em produção.
     */
    public static function padraoValido(string $tipo, string $gatilho): bool
    {
        $gatilho = trim($gatilho);

        if ($gatilho === '') {
            return false;
        }

        if (! in_array($tipo, WhatsAppRule::TIPOS, true)) {
            return false;
        }

        if ($tipo !== WhatsAppRule::REGEX) {
            return mb_strlen($gatilho) <= 500;
        }

        if (mb_strlen($gatilho) > (int) config('whatsapp.rules.max_regex_length', 300)) {
            return false;
        }

        return @preg_match($gatilho, 'teste') !== false;
    }

    /**
     * @return list<WhatsAppRule>
     */
    protected function candidatas(string $etapa): array
    {
        $regras = WhatsAppRule::query()
            ->ativas()
            ->naOrdem()
            ->get()
            ->all();

        $etapaDeMenu = $etapa === 'menu';

        return array_values(array_filter(
            $regras,
            static fn (WhatsAppRule $regra): bool => $regra->escopo === WhatsAppRule::ESCOPO_LIVRE || $etapaDeMenu,
        ));
    }

    protected function podeAvaliar(string $etapa): bool
    {
        return ! in_array($etapa, self::ETAPAS_COM_CONTRATO, true);
    }

    protected function casa(WhatsAppRule $regra, string $texto): bool
    {
        $gatilho = trim((string) $regra->gatilho);

        if ($gatilho === '') {
            return false;
        }

        return match ($regra->tipo) {
            WhatsAppRule::EXACT => $this->normalizar($texto) === $this->normalizar($gatilho),
            WhatsAppRule::CONTAINS => mb_stripos($this->normalizar($texto), $this->normalizar($gatilho)) !== false,
            WhatsAppRule::REGEX => $this->casaRegex($regra, $texto),
            default => false,
        };
    }

    protected function casaRegex(WhatsAppRule $regra, string $texto): bool
    {
        if (! self::padraoValido(WhatsAppRule::REGEX, (string) $regra->gatilho)) {
            Log::warning('Regra de WhatsApp com expressão regular inválida foi ignorada.', [
                'regra_id' => $regra->id,
            ]);

            return false;
        }

        return @preg_match((string) $regra->gatilho, $texto) === 1;
    }

    /**
     * Caixa, acento e espaços não mudam o que o paciente perguntou.
     *
     * `Str::ascii` é o que faz "endereço" casar com "endereco": quem cadastra a
     * regra digita com acento, quem pergunta pelo celular costuma digitar sem.
     */
    protected function normalizar(string $texto): string
    {
        $texto = Str::ascii(trim($texto));

        return mb_strtolower(preg_replace('/\s+/u', ' ', $texto) ?? $texto);
    }
}
