<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'label', 'cor'])]
class AppointmentStatus extends TenantModel
{
    /**
     * Nomes canônicos de status, usados para decidir o que conta como consulta
     * ocupada e o que é cancelamento.
     *
     * Eles vivem aqui, e não repetidos como literais em cada controller, porque
     * o filtro "não mostrar horário cancelado" é regra de negócio escrita como
     * comparação de texto: renomear o status no banco sem mexer aqui troca o
     * comportamento da agenda em silêncio, e o sintoma é um horário que aparece
     * como livre para o paciente.
     *
     * A constante é o ponto de partida para um `slug` próprio na tabela, que é a
     * correção de raiz: hoje o nome ainda é o identificador.
     */
    public const CANCELADO = 'cancelado';

    public const CONFIRMADO = 'confirmado';

    public const AGENDADO = 'agendado';

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Id do status pelo nome canônico, para a API do WhatsApp — que precisa do
     * id e trata "status inexistente" como 1, o primeiro da tabela.
     */
    public static function idFor(string $name, int $padrao = 1): int
    {
        return (int) (static::query()->where('name', $name)->value('id') ?? $padrao);
    }
}
