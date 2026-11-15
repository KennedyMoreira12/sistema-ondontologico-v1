<?php

namespace App\Services\Notificacoes\Coletores;

use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Notification;
use App\Services\Notificacoes\Alerta;
use App\Services\Notificacoes\Coletor;

/**
 * Sessões que vão começar.
 *
 * A janela é curta (duas horas) de propósito: a recepção não precisa de um
 * alerta para a agenda do mês inteiro. A prioridade sobe para urgente quando
 * faltam 30 minutos, que é o ponto em que ainda dá tempo de confirmar.
 *
 * A chave inclui a data e a hora de início: reagendar a consulta cria um
 * alerta novo e o antigo é resolvido pela varredura, em vez de apontar para
 * um horário que não existe mais.
 */
class AgendaColetor implements Coletor
{
    private const LIMITE = 60;

    private const JANELA_HORAS = 2;

    private const MINUTOS_URGENTE = 30;

    public function categoria(): string
    {
        return Notification::CATEGORIA_AGENDA;
    }

    public function coletar(): array
    {
        $agora = now();

        return Appointment::query()
            ->with(['paciente:id,nome', 'profissional:id,nome'])
            ->whereBetween('inicio', [$agora, $agora->copy()->addHours(self::JANELA_HORAS)])
            ->whereDoesntHave('status', fn ($query) => $query->where('name', AppointmentStatus::CANCELADO))
            ->orderBy('inicio')
            ->limit(self::LIMITE)
            ->get()
            ->map(function (Appointment $consulta) use ($agora): Alerta {
                $minutos = (int) $agora->diffInMinutes($consulta->inicio);
                $urgente = $minutos <= self::MINUTOS_URGENTE;

                return new Alerta(
                    categoria: $this->categoria(),
                    tipo: 'sessao_proxima',
                    prioridade: $urgente
                        ? Notification::PRIORIDADE_URGENTE
                        : Notification::PRIORIDADE_AVISO,
                    titulo: $urgente ? 'Sessão começando' : 'Sessão próxima',
                    mensagem: sprintf(
                        '%s às %s%s.',
                        $consulta->paciente?->nome ?? 'Paciente não informado',
                        $consulta->inicio->format('H:i'),
                        $consulta->profissional ? ' com '.$consulta->profissional->nome : '',
                    ),
                    url: route('agenda.index', ['data' => $consulta->inicio->toDateString()], false),
                    permissao: 'agenda.view',
                    referenciaType: 'appointment',
                    referenciaId: $consulta->id,
                    dedupeKey: 'agenda.sessao:'.$consulta->id.':'.$consulta->inicio->format('YmdHi'),
                    expiraEm: $consulta->inicio,
                );
            })
            ->all();
    }
}
