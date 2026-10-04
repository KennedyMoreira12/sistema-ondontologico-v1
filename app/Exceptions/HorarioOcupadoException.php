<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * O horário pedido já está ocupado por outra consulta.
 *
 * Existe como exceção, e não como retorno `false`, porque a checagem de conflito
 * precisa acontecer DENTRO da transação que grava a consulta: fora dela, dois
 * "não há conflito" simultâneos — o n8n repetindo a entrega, ou o paciente
 * falando com dois atendentes ao mesmo tempo — ambos leem a agenda livre e os
 * dois gravam. O `AppointmentController` do painel faz a mesma checagem e não
 * usa esta classe: lá o conflito é raro o bastante para não justificar.
 */
class HorarioOcupadoException extends RuntimeException {}
