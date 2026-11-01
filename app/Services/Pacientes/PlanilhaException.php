<?php

namespace App\Services\Pacientes;

use RuntimeException;

/**
 * Erro previsto na leitura de uma planilha de pacientes.
 *
 * A mensagem é escrita para aparecer direto na tela de importação: é falha do
 * arquivo (formato, tamanho, codificação), não bug do sistema.
 */
class PlanilhaException extends RuntimeException
{
}
