<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/**
 * Base de todo model que vive no banco da plataforma.
 *
 * O espelho exato do `TenantModel`. Sem esta trava, o Laravel faz o model
 * herdar a conexão do model PAI ao montar uma relação: em
 * `HasRelationships::newRelatedInstance` a conexão do pai só não é copiada
 * quando a relação já tem nome. E como o pai de `AuditLog::user()`,
 * `Contract::user()`, `Payment::user()` e companhia é um model de clínica, a
 * relação ia consultar `users` no banco da clínica em atendimento - tabela que
 * não existe ali, e que passaria a existir com o conteúdo errado se alguma
 * clínica tivesse uma.
 *
 * Ter o nome fixo é o que impede a relation de inverter as bases: a conexão
 * `tenant` só existe porque alguém a preencheu, e a `central` é a única que
 * todo model da plataforma pode tocar. Sessão, cache e fila seguem na default,
 * que aponta para cá.
 */
abstract class CentralModel extends Model
{
    protected $connection = 'central';
}
