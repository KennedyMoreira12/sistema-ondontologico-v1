<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * O atendimento não permite a operação pedida (por exemplo, consumir sessão
 * de um paciente que faltou). Regra de negócio, resposta 422.
 */
class AtendimentoInvalido extends RuntimeException
{
    public static function precisaEstarPresente(): self
    {
        return new self('Marque o atendimento como presente antes de consumir a sessão do pacote.');
    }

    public static function pacienteSemPacote(string $nome): self
    {
        return new self("O paciente [{$nome}] não possui pacote de sessões ativo.");
    }

    public static function pacoteDeOutroPaciente(): self
    {
        return new self('O pacote selecionado pertence a outro paciente.');
    }

    public static function faltaComSessaoConsumida(): self
    {
        return new self('Este atendimento já consumiu uma sessão do pacote. Estorne a sessão antes de registrar a falta.');
    }

    public static function atendimentoInexistente(): self
    {
        return new self('O atendimento informado não existe mais.');
    }
}
