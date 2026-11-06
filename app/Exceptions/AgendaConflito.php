<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Conflito de agenda: o horário escolhido já está ocupado.
 *
 * A mensagem é a que aparece na tela; a lista de conflitos permite que o
 * formulário marque o campo certo (profissional, paciente ou sala).
 */
class AgendaConflito extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $conflitos
     */
    public function __construct(public readonly array $conflitos)
    {
        $mensagens = collect($conflitos)->flatten()->implode(' ');

        parent::__construct($mensagens !== '' ? $mensagens : 'Horário indisponível.');
    }

    /**
     * @return array<string, list<string>>
     */
    public function erros(): array
    {
        return $this->conflitos;
    }
}
