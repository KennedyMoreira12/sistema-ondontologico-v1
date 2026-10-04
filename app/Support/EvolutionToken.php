<?php

namespace App\Support;

/**
 * Regras do token da instância da Evolution no cadastro da clínica.
 *
 * Vive junto do `EvolutionInstance` e pelo mesmo motivo: as duas colunas
 * descrevem a mesma coisa — a instância — e a regra delas precisa estar escrita
 * uma vez só. Um nome de instância que a Evolution rejeita é silencioso (ela cria
 * com outro nome e o webhook volta com o que ela escolheu), e um token inválido
 * também: a Evolution responde 401 em toda chamada de envio, sem dizer qual.
 *
 * O campo é `password` no formulário e nunca volta preenchido: a Evolution só
 * mostra o token na criação da instância, então quem edita a clínica não tem o
 * valor original para redigitá-lo, e um input pré-preenchido colocaria o segredo
 * no HTML de quem abre a tela de edição.
 */
final class EvolutionToken
{
    /**
     * Sem regex: o token é uma string opaca que a Evolution gera, e qualquer
     * formato aceito aqui seria recusado por uma Evolution mais nova.
     *
     * @return array<int, mixed>
     */
    public static function rules(): array
    {
        return ['nullable', 'string', 'max:255'];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'evolution_token.max' => 'O token da instância é no máximo 255 caracteres.',
        ];
    }

    /**
     * String vazia vira `null`, para o cast `encrypted` não gravar um texto
     * cifrado de valor vazio que depois conta como token cadastrado.
     */
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Como o token aparece na tela de edição e no log de auditoria.
     *
     * Os últimos quatro caracteres são o suficiente para o operador distinguir
     * "é este mesmo" de "troquei por outro", que é tudo que a tela precisa — e
     * não o bastante para servir de credencial. Auditoria que grava segredo em
     * claro é o jeito mais fácil de vazar um: `platform_audit_logs` é lido por
     * mais gente que a Evolution.
     */
    public static function mask(?string $token): ?string
    {
        if ($token === null || $token === '') {
            return null;
        }

        return mb_strlen($token) <= 8
            ? '••••'
            : str_repeat('•', 8).mb_substr($token, -4);
    }
}
