<?php

namespace App\Services\Notificacoes\Coletores;

use App\Models\AccountsPayable;
use App\Models\AccountsReceivable;
use App\Models\Notification;
use App\Services\Notificacoes\Alerta;
use App\Services\Notificacoes\Coletor;

/**
 * Contas a pagar e a receber.
 *
 * O "atrasado" não é status gravado — é data de vencimento no passado e saldo
 * devedor. A varredura recalcula isso toda vez, assim a conta sai da lista
 * sozinha quando é paga ou cancelada.
 */
class FinanceiroColetor implements Coletor
{
    private const LIMITE = 60;

    private const DIAS_AVISO = 3;

    public function categoria(): string
    {
        return Notification::CATEGORIA_FINANCEIRO;
    }

    public function coletar(): array
    {
        return [
            ...$this->pagarVencidas(),
            ...$this->pagarVencendo(),
            ...$this->receberVencidas(),
            ...$this->receberVencendo(),
        ];
    }

    /**
     * @return list<Alerta>
     */
    private function pagarVencidas(): array
    {
        return AccountsPayable::query()
            ->emAberto()
            ->whereRaw(AccountsPayable::expressaoSaldo().' > 0')
            ->whereDate('data_vencimento', '<', today())
            ->orderBy('data_vencimento')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (AccountsPayable $conta): Alerta => new Alerta(
                categoria: $this->categoria(),
                tipo: 'conta_pagar_vencida',
                prioridade: Notification::PRIORIDADE_URGENTE,
                titulo: 'Conta a pagar vencida',
                mensagem: sprintf(
                    '%s venceu em %s. Saldo devedor: R$ %s.',
                    $conta->nome_conta,
                    $conta->data_vencimento->format('d/m/Y'),
                    $this->dinheiro($conta->saldo),
                ),
                url: route('financeiro.contas-pagar.show', $conta, false),
                permissao: 'financeiro.view',
                referenciaType: 'accounts_payable',
                referenciaId: $conta->id,
                dedupeKey: 'financeiro.pagar.vencida:'.$conta->id,
            ))
            ->all();
    }

    /**
     * @return list<Alerta>
     */
    private function pagarVencendo(): array
    {
        return AccountsPayable::query()
            ->emAberto()
            ->whereRaw(AccountsPayable::expressaoSaldo().' > 0')
            ->whereDate('data_vencimento', '>=', today())
            ->whereDate('data_vencimento', '<=', today()->addDays(self::DIAS_AVISO))
            ->orderBy('data_vencimento')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (AccountsPayable $conta): Alerta => new Alerta(
                categoria: $this->categoria(),
                tipo: 'conta_pagar_vencendo',
                prioridade: Notification::PRIORIDADE_AVISO,
                titulo: 'Conta a pagar vencendo',
                mensagem: sprintf(
                    '%s vence em %s. Saldo devedor: R$ %s.',
                    $conta->nome_conta,
                    $conta->data_vencimento->format('d/m/Y'),
                    $this->dinheiro($conta->saldo),
                ),
                url: route('financeiro.contas-pagar.show', $conta, false),
                permissao: 'financeiro.view',
                referenciaType: 'accounts_payable',
                referenciaId: $conta->id,
                dedupeKey: 'financeiro.pagar.vencendo:'.$conta->id,
            ))
            ->all();
    }

    /**
     * @return list<Alerta>
     */
    private function receberVencidas(): array
    {
        return AccountsReceivable::query()
            ->with('paciente:id,nome')
            ->whereIn('status', ['aberta', 'parcial'])
            ->whereColumn('valor_pago', '<', 'valor')
            ->whereDate('vencimento', '<', today())
            ->orderBy('vencimento')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (AccountsReceivable $conta): Alerta => new Alerta(
                categoria: $this->categoria(),
                tipo: 'conta_receber_vencida',
                prioridade: Notification::PRIORIDADE_URGENTE,
                titulo: 'Conta a receber vencida',
                mensagem: sprintf(
                    '%s — venceu em %s. Saldo a receber: R$ %s.',
                    $conta->paciente?->nome ?? 'Paciente não informado',
                    $conta->vencimento?->format('d/m/Y') ?? 'sem data',
                    $this->dinheiro($conta->saldo),
                ),
                url: route('financeiro.index', ['conta' => $conta->id], false),
                permissao: 'financeiro.view',
                referenciaType: 'accounts_receivable',
                referenciaId: $conta->id,
                dedupeKey: 'financeiro.receber.vencida:'.$conta->id,
            ))
            ->all();
    }

    /**
     * @return list<Alerta>
     */
    private function receberVencendo(): array
    {
        return AccountsReceivable::query()
            ->with('paciente:id,nome')
            ->whereIn('status', ['aberta', 'parcial'])
            ->whereColumn('valor_pago', '<', 'valor')
            ->whereDate('vencimento', '>=', today())
            ->whereDate('vencimento', '<=', today()->addDays(self::DIAS_AVISO))
            ->orderBy('vencimento')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (AccountsReceivable $conta): Alerta => new Alerta(
                categoria: $this->categoria(),
                tipo: 'conta_receber_vencendo',
                prioridade: Notification::PRIORIDADE_AVISO,
                titulo: 'Conta a receber vencendo',
                mensagem: sprintf(
                    '%s — vence em %s. Saldo a receber: R$ %s.',
                    $conta->paciente?->nome ?? 'Paciente não informado',
                    $conta->vencimento?->format('d/m/Y') ?? 'sem data',
                    $this->dinheiro($conta->saldo),
                ),
                url: route('financeiro.index', ['conta' => $conta->id], false),
                permissao: 'financeiro.view',
                referenciaType: 'accounts_receivable',
                referenciaId: $conta->id,
                dedupeKey: 'financeiro.receber.vencendo:'.$conta->id,
            ))
            ->all();
    }

    private function dinheiro(float $valor): string
    {
        return number_format($valor, 2, ',', '.');
    }
}
