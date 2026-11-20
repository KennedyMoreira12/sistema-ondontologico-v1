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

    /**
     * Texto de boas-vindas/consentimento. Se vazio, o fluxo usa o padrão.
     */
    public const MENSAGEM_BOAS_VINDAS = 'mensagem_boas_vindas';

    /**
     * Menu principal (texto). Se vazio, o fluxo usa o padrão.
     */
    public const MENSAGEM_MENU = 'mensagem_menu';

    /**
     * Prompt de sistema do agente de IA. Se vazio, usa o prompt padrão do código.
     */
    public const PROMPT_IA = 'prompt_ia';

    /**
     * Endereço exibido em confirmações. Se vazio, tenta o perfil da clínica.
     */
    public const ENDERECO_CONFIRMACAO = 'endereco_confirmacao';

    /**
     * Configuração JSON do fluxo odontológico (opções, FAQ, urgência, etc).
     */
    public const FLUXO_ODONTOLOGICO = 'fluxo_odontologico';

    /**
     * Preço exibido em respostas; se vazio/nulo, encaminha para a equipe.
     */
    public const PRECO_NAO_INFORMADO = 'preco_nao_informado';

    /**
     * Valor usado em `whatsapp.fluxo_odontologico.modo` para clínicas com
     * atendimento odontológico completo configurado.
     */
    public const FLUXO_MODO_ODONTOLOGICO = 'odontologico';

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

    /**
     * Configuração estruturada do fluxo odontológico da clínica.
     *
     * @return array<string, mixed>|null
     */
    public function fluxoOdontologico(): ?array
    {
        $fluxo = $this->json(self::FLUXO_ODONTOLOGICO);

        return is_array($fluxo) ? $fluxo : null;
    }

    public function modoOdontologico(): bool
    {
        $fluxo = $this->fluxoOdontologico();

        if ($fluxo === null) {
            return false;
        }

        $modo = $fluxo['modo'] ?? null;

        return $modo === null || $modo === '' || $modo === self::FLUXO_MODO_ODONTOLOGICO;
    }

    protected function prefixo(string $chave): string
    {
        return str_starts_with($chave, 'whatsapp.') ? $chave : 'whatsapp.'.$chave;
    }
}
