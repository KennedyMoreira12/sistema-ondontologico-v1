<?php

namespace App\Services\Notificacoes\Coletores;

use App\Models\Contract;
use App\Models\EvolucaoSessao;
use App\Models\Notification;
use App\Services\Notificacoes\Alerta;
use App\Services\Notificacoes\Coletor;

/**
 * Documentos que esperam assinatura.
 *
 * Dois casos reais hoje: o termo de responsabilidade do paciente e a
 * confirmação de presença de uma sessão finalizada. Os dois são resolvidos na
 * hora em que a assinatura é registrada, pelo `AssinaturaService`.
 */
class AssinaturasColetor implements Coletor
{
    private const LIMITE = 50;

    private const DIAS_SESSAO = 30;

    public function categoria(): string
    {
        return Notification::CATEGORIA_ASSINATURAS;
    }

    public function coletar(): array
    {
        return [
            ...$this->contratosPendentes(),
            ...$this->sessoesSemAssinatura(),
        ];
    }

    /**
     * @return list<Alerta>
     */
    private function contratosPendentes(): array
    {
        return Contract::query()
            ->with('paciente:id,nome')
            ->where('status', 'pendente')
            ->whereNotNull('paciente_id')
            ->orderBy('created_at')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (Contract $contrato): Alerta => new Alerta(
                categoria: $this->categoria(),
                tipo: 'contrato_pendente',
                prioridade: Notification::PRIORIDADE_AVISO,
                titulo: 'Termo aguardando assinatura',
                mensagem: sprintf(
                    'O termo de %s aguarda assinatura desde %s.',
                    $contrato->paciente?->nome ?? 'paciente não informado',
                    $contrato->created_at?->format('d/m/Y') ?? '—',
                ),
                url: route('pacientes.assinaturas.index', $contrato->paciente_id, false),
                permissao: 'contratos.view',
                referenciaType: 'contract',
                referenciaId: $contrato->id,
                dedupeKey: 'assinaturas.contrato:'.$contrato->id,
            ))
            ->all();
    }

    /**
     * @return list<Alerta>
     */
    private function sessoesSemAssinatura(): array
    {
        return EvolucaoSessao::query()
            ->with('paciente:id,nome')
            ->where('status', EvolucaoSessao::FINALIZADA)
            ->whereDate('data', '>=', today()->subDays(self::DIAS_SESSAO))
            ->whereDoesntHave('assinaturas', fn ($query) => $query->whereNull('superseded_at'))
            ->orderByDesc('data')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (EvolucaoSessao $sessao): Alerta => new Alerta(
                categoria: $this->categoria(),
                tipo: 'sessao_sem_assinatura',
                prioridade: Notification::PRIORIDADE_AVISO,
                titulo: 'Presença aguardando assinatura',
                mensagem: sprintf(
                    'A sessão de %s em %s ainda não foi assinada.',
                    $sessao->paciente?->nome ?? 'paciente não informado',
                    $sessao->data?->format('d/m/Y') ?? '—',
                ),
                url: route('pacientes.assinaturas.index', $sessao->paciente_id, false),
                permissao: 'pacientes.view',
                referenciaType: 'evolucao_sessao',
                referenciaId: $sessao->id,
                dedupeKey: 'assinaturas.sessao:'.$sessao->id,
            ))
            ->all();
    }
}
