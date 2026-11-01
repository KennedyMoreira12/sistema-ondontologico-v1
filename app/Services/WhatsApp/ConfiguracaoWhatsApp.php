<?php

namespace App\Services\WhatsApp;

use App\Models\ClinicSetting;

/**
 * Acesso às configurações do atendimento no banco da clínica.
 *
 * Existe para que o resto do código não repita `ClinicSetting::valor('whatsapp.'.…)`
 * nem espalhe o formato JSON de cada chave. O prefixo `whatsapp.` também evita
 * colisão com configuração de outro módulo na mesma tabela.
 *
 * Nada aqui é cacheado de propósito: são poucas linhas indexadas por chave,
 * lidas uma vez por mensagem, e cache por clínica em processo de worker é
 * justamente onde uma configuração antiga sobrevive ao `save()` e o operador
 * jura que salvou.
 */
class ConfiguracaoWhatsApp
{
    public const EXPEDIENTE = 'expediente';

    public const MENSAGEM_AUSENCIA = 'mensagem_ausencia';

    public const MENSAGEM_OPT_OUT = 'mensagem_opt_out';

    public const OPT_OUT_PALAVRAS = 'opt_out_palavras';

    public function texto(string $chave, ?string $padrao = null): ?string
    {
        $valor = ClinicSetting::valor($this->prefixo($chave));

        return ($valor === null || trim($valor) === '') ? $padrao : $valor;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function json(string $chave): ?array
    {
        $valor = ClinicSetting::valor($this->prefixo($chave));

        if ($valor === null || trim($valor) === '') {
            return null;
        }

        $decodificado = json_decode($valor, true);

        return is_array($decodificado) ? $decodificado : null;
    }

    public function definirTexto(string $chave, ?string $valor): void
    {
        ClinicSetting::definir($this->prefixo($chave), $valor, 'whatsapp');
    }

    /**
     * @param  array<string, mixed>  $valor
     */
    public function definirJson(string $chave, array $valor): void
    {
        ClinicSetting::definir(
            $this->prefixo($chave),
            json_encode($valor, JSON_UNESCAPED_UNICODE) ?: null,
            'whatsapp',
        );
    }

    /**
     * Palavras que pedem para parar de receber mensagem automática.
     *
     * Lista configurável porque cada clínica fala de um jeito; o padrão cobre o
     * que o paciente realmente digita.
     *
     * @return list<string>
     */
    public function palavrasDeOptOut(): array
    {
        $configuradas = $this->json(self::OPT_OUT_PALAVRAS);

        $palavras = is_array($configuradas['palavras'] ?? null)
            ? $configuradas['palavras']
            : config('whatsapp.opt_out.palavras', []);

        return array_values(array_filter(
            array_map(static fn (mixed $p): string => mb_strtolower(trim((string) $p)), (array) $palavras),
            static fn (string $p): bool => $p !== '',
        ));
    }

    protected function prefixo(string $chave): string
    {
        return str_starts_with($chave, 'whatsapp.') ? $chave : 'whatsapp.'.$chave;
    }
}
