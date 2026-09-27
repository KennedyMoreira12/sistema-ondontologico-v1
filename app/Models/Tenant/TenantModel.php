<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/**
 * Base de todo model que vive no banco de uma clínica.
 *
 * `$connection = 'tenant'` é o que impede o model de ler a base errada: ela
 * só está preenchida depois que o ResolveTenant roda, então qualquer query
 * fora do fluxo de tenant estoura em vez de vazar dado de outra clínica.
 *
 * Sobre a coluna `clinica_id` que existe em algumas tabelas de clínica
 * (pacientes, profissionais, rooms, appointments): ela é redundante, porque o
 * banco já É a clínica, e mesmo assim é mantida de propósito, como trilha de
 * auditoria — "esta linha foi criada enquanto a clínica X estava no comando".
 * O valor vem sempre do usuário autenticado ou do TenantManager, nunca do body
 * da requisição, então ele não pode ser usado para mover dado entre clínicas.
 * Ela não entra em filtro de listagem nem em `scope` de tenant: filtro de
 * isolamento é a conexão, e um `where('clinica_id', ...)` esquecido seria
 * justamente o tipo de bug que o projeto existe para evitar.
 */
abstract class TenantModel extends Model
{
    protected $connection = 'tenant';
}
