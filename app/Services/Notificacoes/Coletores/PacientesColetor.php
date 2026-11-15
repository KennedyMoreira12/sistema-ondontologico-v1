<?php

namespace App\Services\Notificacoes\Coletores;

use App\Models\Notification;
use App\Models\Paciente;
use App\Services\Notificacoes\Alerta;
use App\Services\Notificacoes\Coletor;

/**
 * Pendências de cadastro de pacientes.
 *
 * A janela de 90 dias é o que separa "cadastro novo para completar" de "base
 * histórica importada". Alertar os 6 mil pacientes antigos sem CPF encheria a
 * central com um problema que ninguém vai resolver no dia.
 *
 * Documentos não entram aqui: `patient_documents` não tem estado pendente. A
 * pendência de documento que existe de verdade é a assinatura, tratada pelo
 * `AssinaturasColetor`.
 */
class PacientesColetor implements Coletor
{
    private const LIMITE = 40;

    private const DIAS_RECENTES = 90;

    public function categoria(): string
    {
        return Notification::CATEGORIA_PACIENTES;
    }

    public function coletar(): array
    {
        return Paciente::query()
            ->where('status', 'ativo')
            ->where('created_at', '>=', now()->subDays(self::DIAS_RECENTES))
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->filter(fn (Paciente $paciente): bool => $this->faltando($paciente) !== [])
            ->take(self::LIMITE)
            ->map(fn (Paciente $paciente): Alerta => new Alerta(
                categoria: $this->categoria(),
                tipo: 'cadastro_incompleto',
                prioridade: Notification::PRIORIDADE_AVISO,
                titulo: 'Cadastro de paciente incompleto',
                mensagem: sprintf(
                    '%s está sem %s.',
                    $paciente->nome,
                    implode(' e ', $this->faltando($paciente)),
                ),
                url: route('pacientes.show', $paciente, false),
                permissao: 'pacientes.view',
                referenciaType: 'paciente',
                referenciaId: $paciente->id,
                dedupeKey: 'pacientes.cadastro:'.$paciente->id,
            ))
            ->values()
            ->all();
    }

    /**
     * Campos que impedem o cadastro de ser útil no dia a dia.
     *
     * @return list<string>
     */
    private function faltando(Paciente $paciente): array
    {
        $faltando = [];

        if ($paciente->cpf === null || $paciente->cpf === '') {
            $faltando[] = 'CPF';
        }

        if (($paciente->celular === null || $paciente->celular === '')
            && ($paciente->phone === null || $paciente->phone === '')) {
            $faltando[] = 'telefone';
        }

        return $faltando;
    }
}
