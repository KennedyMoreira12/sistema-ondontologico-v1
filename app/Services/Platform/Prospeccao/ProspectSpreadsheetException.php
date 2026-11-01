<?php

namespace App\Services\Platform\Prospeccao;

use RuntimeException;

/**
 * Erro de leitura de uma planilha de prospecção.
 *
 * A mensagem é escrita para aparecer direto na tela de importação, então deve
 * dizer em português o que o operador precisa corrigir no arquivo.
 */
class ProspectSpreadsheetException extends RuntimeException {}
