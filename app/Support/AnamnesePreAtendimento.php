<?php

namespace App\Support;

/**
 * Catálogo do questionário de pré-atendimento odontológico.
 *
 * As respostas continuam persistidas no JSON `anamneses.respostas`, que já
 * comporta as anamneses antigas em texto livre. Manter aqui as opções e as
 * chaves evita que formulário, validação e exibição discordem.
 */
class AnamnesePreAtendimento
{
    /** @var array<string, string> */
    public const DOENCAS = [
        'hipertensao' => 'Hipertensão arterial (pressão alta)',
        'cardiopatia' => 'Cardiopatia',
        'diabetes' => 'Diabetes',
        'renal' => 'Doença renal',
        'coagulacao' => 'Alterações de coagulação',
        'outras' => 'Outras doenças',
    ];

    public const CHAVE_DOENCAS = 'Doenças sistêmicas';

    public const CHAVE_MEDICAMENTOS = 'Faz uso de algum medicamento atualmente?';

    public const CHAVE_DORES = 'Costuma sentir dores de cabeça ou tonturas?';

    public const CHAVE_ALERGIAS = 'Possui alergia a medicamentos ou materiais odontológicos?';

    public const CHAVE_TRATAMENTO = 'Está realizando algum tratamento médico atualmente?';

    public const SIM = 'sim';

    public const NAO = 'nao';

    /**
     * @param  array<string, mixed>  $respostas
     * @return array<string, array<int, string>|string>
     */
    public static function normalizar(array $respostas): array
    {
        $selecionadas = $respostas['doencas'] ?? [];
        $selecionadas = is_array($selecionadas) ? $selecionadas : [];

        $outras = trim((string) ($respostas['outras_doencas'] ?? ''));

        $doencas = collect($selecionadas)
            ->filter(fn (mixed $valor): bool => is_string($valor) && array_key_exists($valor, self::DOENCAS))
            ->reject(fn (string $valor): bool => $valor === 'outras' && $outras !== '')
            ->map(fn (string $valor): string => self::DOENCAS[$valor])
            ->values()
            ->all();

        if ($outras !== '') {
            $doencas[] = 'Outras: '.$outras;
        }

        return [
            self::CHAVE_DOENCAS => $doencas !== [] ? $doencas : 'Nenhuma informada',
            self::CHAVE_MEDICAMENTOS => self::respostaComDetalhes($respostas, 'usa_medicamentos', 'medicamentos'),
            self::CHAVE_DORES => self::respostaComDetalhes($respostas, 'dores_tonturas', 'dores_tonturas_detalhes'),
            self::CHAVE_ALERGIAS => self::respostaComDetalhes($respostas, 'alergias', 'alergias_detalhes'),
            self::CHAVE_TRATAMENTO => self::respostaComDetalhes($respostas, 'tratamento_medico', 'tratamento_medico_detalhes'),
        ];
    }

    /**
     * @param  array<string, mixed>  $respostas
     */
    private static function respostaComDetalhes(array $respostas, string $campo, string $detalhes): string
    {
        $resposta = $respostas[$campo] ?? null;
        $detalhe = trim((string) ($respostas[$detalhes] ?? ''));

        if ($resposta === self::NAO) {
            return 'Não';
        }

        if ($resposta !== self::SIM) {
            return 'Não informado';
        }

        return $detalhe !== '' ? "Sim: {$detalhe}" : 'Sim';
    }
}
