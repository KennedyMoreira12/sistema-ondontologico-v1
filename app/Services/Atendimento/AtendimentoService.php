<?php

namespace App\Services\Atendimento;

use App\Exceptions\AtendimentoInvalido;
use App\Models\Appointment;
use App\Models\ConsumoSessao;
use App\Models\Paciente;
use App\Models\PacoteSessao;
use App\Services\Agenda\AgendaService;
use App\Services\Fisioterapia\PacoteSessaoService;
use Illuminate\Support\Facades\DB;

/**
 * Fluxo de atendimento do dia.
 *
 * Presença/falta é uma transição de status do appointment. O consumo de
 * pacote só acontece depois de "presente" e é idempotente por atendimento:
 * marcar presença duas vezes não debita duas sessões. Faltou não consome e
 * não devolve automaticamente — a reposição é um ato explícito, porque a
 * regra comercial da cliente ainda não foi confirmada.
 */
class AtendimentoService
{
    public function __construct(
        private AgendaService $agenda,
        private PacoteSessaoService $pacotes,
    ) {}

    public function marcarPresente(Appointment $consulta): Appointment
    {
        return $this->marcarStatus($consulta, 'atendido');
    }

    public function marcarFalta(Appointment $consulta): Appointment
    {
        return $this->marcarStatus($consulta, 'faltou');
    }

    public function marcarStatus(Appointment $consulta, string $status): Appointment
    {
        $statusId = $this->agenda->statusId($status);

        if ($statusId === null) {
            throw new AtendimentoInvalido("O status [{$status}] não está cadastrado nesta clínica.");
        }

        $consulta->update(['appointment_status_id' => $statusId]);

        return $consulta->refresh();
    }

    /**
     * Consome uma sessão do pacote do paciente para este atendimento.
     *
     * Se o atendimento já consumiu uma sessão, devolve o consumo existente
     * em vez de debitar de novo.
     */
    public function consumirSessao(
        Appointment $consulta,
        ?PacoteSessao $pacote = null,
        bool $reposicao = false,
    ): ConsumoSessao {
        $consulta->refresh();

        if (! $consulta->foiAtendido()) {
            throw AtendimentoInvalido::precisaEstarPresente();
        }

        $paciente = Paciente::query()->findOrFail($consulta->paciente_id);

        $pacote ??= PacoteSessao::query()
            ->where('paciente_id', $paciente->id)
            ->where('status', PacoteSessao::ATIVO)
            ->whereColumn('sessoes_usadas', '<', 'total_sessoes')
            ->when($consulta->servico !== null, fn ($query) => $query->where(
                fn ($sub) => $sub->where('servico', $consulta->servico)->orWhereNull('servico')
            ))
            ->orderByRaw('validade_em IS NULL')
            ->orderBy('validade_em')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($pacote === null) {
            throw AtendimentoInvalido::pacienteSemPacote($paciente->nome);
        }

        if ((int) $pacote->paciente_id !== (int) $paciente->id) {
            throw AtendimentoInvalido::pacoteDeOutroPaciente();
        }

        return DB::connection($consulta->getConnectionName())->transaction(function () use ($consulta, $pacote, $reposicao): ConsumoSessao {
            return $this->pacotes->consumir($pacote, [
                'paciente_id' => $consulta->paciente_id,
                'profissional_id' => $consulta->profissional_id,
                'appointment_id' => $consulta->id,
                'reposicao' => $reposicao,
                'data' => $consulta->inicio->toDateString(),
                'valor' => $consulta->valor,
                'observacoes' => $consulta->titulo,
            ]);
        });
    }
}
