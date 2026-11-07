<?php

namespace App\Services\Agenda;

use App\Exceptions\HorarioOcupadoException;
use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Room;
use App\Services\AuditLogger;
use App\Services\Tenant\TenantManager;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Criação de consulta com a trava de conflito.
 *
 * Virou serviço porque a máquina de estados do WhatsApp agenda pelo mesmo caminho
 * do endpoint HTTP — as duas precisam da mesma trava, do mesmo tratamento de
 * idempotência e do mesmo registro de auditoria. Uma segunda implementação
 * dessa trava num lugar só do bot seria uma agenda que aceita consulta dupla
 * justamente na hora de maior movimento.
 */
class AgendamentoService
{
    public function __construct(
        protected TenantManager $tenants,
    ) {}

    /**
     * @param  array{paciente_id: int, profissional_id: int, dia: string, hora_inicio: string, duracao?: int, tipo?: string, observacao?: string|null, idempotency_key?: string|null}  $dados
     *
     * @throws HorarioOcupadoException
     * @throws LockTimeoutException
     */
    public function criar(array $dados): Appointment
    {
        $chave = $dados['idempotency_key'] ?? null;

        if ($chave !== null) {
            $existente = Appointment::query()
                ->where('idempotency_key', $chave)
                ->first();

            if ($existente !== null) {
                return $existente;
            }
        }

        $duracao = $dados['duracao'] ?? 30;
        $statusId = AppointmentStatus::idFor($dados['tipo'] ?? AppointmentStatus::AGENDADO);

        $inicio = Carbon::parse($dados['dia'])->setTimeFromTimeString($dados['hora_inicio']);
        $fim = $inicio->copy()->addMinutes($duracao);

        /**
         * O mutex vai por CLÍNICA, PROFISSIONAL E DIA, não pelo horário pedido:
         * dois agendamentos que se sobrepõem sem coincidir de início (09:00 e
         * 09:15) precisam cair no mesmo lock, e travar pelo horário exato deixaria
         * os dois passarem.
         *
         * A chave inclui o tenant (clinica_id) para impedir que locks sejam
         * compartilhados entre clínicas quando o cache é compartilhado (banco
         * de dados). É um lock, e não `lockForUpdate()` na consulta de conflito:
         * um `SELECT EXISTS` não trava a linha nem a faixa onde a linha ainda
         * não existe, então os dois "não há conflito" aconteciam mesmo assim.
         * O lock é o que segura; a transação mantém checagem e gravação no mesmo
         * passo, e o índice único da chave de idempotência é a rede final se
         * tudo acima falhar.
         */
        $consulta = Cache::lock(
            $this->chaveDoLock((int) $dados['profissional_id'], $inicio->toDateString()),
            10,
        )->block(5, fn (): Appointment => DB::transaction(function () use ($dados, $chave, $inicio, $fim, $statusId): Appointment {
            $conflito = Appointment::query()
                ->where('profissional_id', $dados['profissional_id'])
                ->whereHas('status', fn ($query) => $query->where('name', '!=', AppointmentStatus::CANCELADO))
                ->where('fim', '>', $inicio)
                ->where('inicio', '<', $fim)
                ->exists();

            if ($conflito) {
                throw new HorarioOcupadoException;
            }

            return Appointment::create([
                'clinica_id' => $this->tenants->id(),
                'paciente_id' => $dados['paciente_id'],
                'profissional_id' => $dados['profissional_id'],
                'room_id' => Room::where('is_active', true)->value('id'),
                'appointment_status_id' => $statusId,
                'inicio' => $inicio,
                'fim' => $fim,
                'titulo' => 'Agendamento via WhatsApp',
                'observacao' => $dados['observacao'] ?? null,
                'idempotency_key' => $chave,
            ]);
        }));

        AuditLogger::log('criar', 'appointments', $consulta->id, depois: $consulta->only(['paciente_id', 'profissional_id', 'inicio', 'fim', 'appointment_status_id']));

        return $consulta;
    }

    /**
     * Reenvio da mesma requisição devolve a consulta já criada, em vez de
     * duplicar. Sem a chave, o bot fica sem idempotência.
     */
    public function existentePorChave(string $chave): ?Appointment
    {
        return Appointment::query()
            ->where('idempotency_key', $chave)
            ->first();
    }

    /**
     * Chave do lock de agenda: clínica, profissional e dia.
     *
     * A clínica entra na chave pelo helper de tenant, e não à mão: a chave é
     * o que impede que o lock de uma clínica trave a agenda de outra quando o
     * cache é compartilhado (banco de dados). Fica num método só para que a
     * garantia possa ser conferida por teste, em vez de espalhada em string
     * montada dentro da transação.
     */
    public function chaveDoLock(int $profissionalId, string $dia): string
    {
        return tenant_cache_key("agenda:prof:{$profissionalId}:{$dia}");
    }
}
