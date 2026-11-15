<?php

namespace App\Services\Notificacoes;

/**
 * Roda a varredura completa da clínica atual.
 *
 * Quem chama precisa ter a conexão `tenant` apontada para a clínica certa —
 * o job faz isso pelo `TenantRunner`, e o comando com `--sync` também.
 */
class GeradorNotificacoes
{
    public function __construct(
        private ColetorNotificacoes $coletores,
        private Notificador $notificador,
    ) {}

    /**
     * @return array<string, int> quantidade de alertas ativos por categoria
     */
    public function gerar(): array
    {
        $resultado = [];

        foreach ($this->coletores->coletar() as $categoria => $alertas) {
            $resultado[$categoria] = $this->notificador->sincronizarCategoria($categoria, $alertas);
        }

        return $resultado;
    }
}
