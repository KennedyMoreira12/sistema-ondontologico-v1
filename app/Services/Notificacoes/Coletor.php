<?php

namespace App\Services\Notificacoes;

/**
 * Um módulo que sabe transformar o estado atual em alertas.
 *
 * A varredura pergunta a cada coletor "o que está acontecendo agora?" e
 * compara com o que já foi gravado. Um coletor novo entra na lista do
 * `ColetorNotificacoes` e a deduplicação/resolução vem de graça.
 */
interface Coletor
{
    /**
     * Categoria do alerta, uma das constantes de `App\Models\Notification`.
     */
    public function categoria(): string;

    /**
     * @return list<Alerta>
     */
    public function coletar(): array;
}
