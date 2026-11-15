<?php

namespace App\Services\Notificacoes\Coletores;

use App\Models\Notification;
use App\Models\Product;
use App\Services\Notificacoes\Alerta;
use App\Services\Notificacoes\Coletor;

/**
 * Estoque baixo, zerado e validade.
 *
 * Só entram produtos com controle de estoque ligado: um serviço ou item sem
 * controle nunca "zera" e não deve virar alerta.
 */
class EstoqueColetor implements Coletor
{
    private const LIMITE = 60;

    private const DIAS_VALIDADE = 30;

    public function categoria(): string
    {
        return Notification::CATEGORIA_ESTOQUE;
    }

    public function coletar(): array
    {
        return [
            ...$this->zerados(),
            ...$this->baixos(),
            ...$this->vencidos(),
            ...$this->vencendo(),
        ];
    }

    /**
     * @return list<Alerta>
     */
    private function zerados(): array
    {
        return Product::query()
            ->where('controlar_estoque', true)
            ->where('is_active', true)
            ->where('estoque', '<=', 0)
            ->orderBy('nome')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (Product $produto): Alerta => new Alerta(
                categoria: $this->categoria(),
                tipo: 'estoque_zerado',
                prioridade: Notification::PRIORIDADE_URGENTE,
                titulo: 'Produto sem estoque',
                mensagem: sprintf('%s está zerado.', $produto->nome),
                url: route('estoque.index', ['busca' => $produto->nome], false),
                permissao: 'estoque.view',
                referenciaType: 'product',
                referenciaId: $produto->id,
                dedupeKey: 'estoque.zerado:'.$produto->id,
            ))
            ->all();
    }

    /**
     * @return list<Alerta>
     */
    private function baixos(): array
    {
        return Product::query()
            ->where('controlar_estoque', true)
            ->where('is_active', true)
            ->where('estoque', '>', 0)
            ->whereColumn('estoque', '<=', 'estoque_minimo')
            ->orderBy('nome')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (Product $produto): Alerta => new Alerta(
                categoria: $this->categoria(),
                tipo: 'estoque_baixo',
                prioridade: Notification::PRIORIDADE_AVISO,
                titulo: 'Estoque abaixo do mínimo',
                mensagem: sprintf(
                    '%s tem %s %s e o mínimo é %s.',
                    $produto->nome,
                    $this->quantidade((float) $produto->estoque),
                    $produto->unidade,
                    $this->quantidade((float) $produto->estoque_minimo),
                ),
                url: route('estoque.index', ['busca' => $produto->nome], false),
                permissao: 'estoque.view',
                referenciaType: 'product',
                referenciaId: $produto->id,
                dedupeKey: 'estoque.baixo:'.$produto->id,
            ))
            ->all();
    }

    /**
     * @return list<Alerta>
     */
    private function vencidos(): array
    {
        return Product::query()
            ->where('is_active', true)
            ->whereNotNull('validade')
            ->whereDate('validade', '<', today())
            ->orderBy('validade')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (Product $produto): Alerta => new Alerta(
                categoria: $this->categoria(),
                tipo: 'estoque_vencido',
                prioridade: Notification::PRIORIDADE_URGENTE,
                titulo: 'Produto vencido',
                mensagem: sprintf(
                    '%s venceu em %s%s.',
                    $produto->nome,
                    $produto->validade->format('d/m/Y'),
                    $produto->lote ? ' (lote '.$produto->lote.')' : '',
                ),
                url: route('estoque.index', ['busca' => $produto->nome], false),
                permissao: 'estoque.view',
                referenciaType: 'product',
                referenciaId: $produto->id,
                dedupeKey: 'estoque.vencido:'.$produto->id,
            ))
            ->all();
    }

    /**
     * @return list<Alerta>
     */
    private function vencendo(): array
    {
        return Product::query()
            ->where('is_active', true)
            ->whereNotNull('validade')
            ->whereDate('validade', '>=', today())
            ->whereDate('validade', '<=', today()->addDays(self::DIAS_VALIDADE))
            ->orderBy('validade')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (Product $produto): Alerta => new Alerta(
                categoria: $this->categoria(),
                tipo: 'estoque_vencendo',
                prioridade: Notification::PRIORIDADE_AVISO,
                titulo: 'Produto próximo do vencimento',
                mensagem: sprintf(
                    '%s vence em %s%s.',
                    $produto->nome,
                    $produto->validade->format('d/m/Y'),
                    $produto->lote ? ' (lote '.$produto->lote.')' : '',
                ),
                url: route('estoque.index', ['busca' => $produto->nome], false),
                permissao: 'estoque.view',
                referenciaType: 'product',
                referenciaId: $produto->id,
                dedupeKey: 'estoque.vencendo:'.$produto->id,
            ))
            ->all();
    }

    private function quantidade(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 3, ',', '.'), '0'), ',');
    }
}
