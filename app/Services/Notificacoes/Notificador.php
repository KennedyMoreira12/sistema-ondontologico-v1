<?php

namespace App\Services\Notificacoes;

use App\Models\Notification;

/**
 * Grava alertas e mantém a lista coerente com o estado atual.
 *
 * Duas origens, duas regras:
 * - `varredura` descreve uma CONDIÇÃO que pode deixar de existir. A cada
 *   execução, o que não apareceu mais é resolvido.
 * - `evento` descreve um FATO pontual (pagamento recebido, sessão cancelada).
 *   Ele nunca é resolvido pela varredura, porque não há condição para reavaliar.
 *
 * `updateOrCreate` pela `dedupe_key` é o coração do controle de duplicidade:
 * rodar a varredura dez vezes seguidas deixa uma linha, não dez.
 */
class Notificador
{
    public const ORIGEM_VARREDURA = 'varredura';

    public const ORIGEM_EVENTO = 'evento';

    public function emitir(Alerta $alerta, string $origem = self::ORIGEM_EVENTO): Notification
    {
        return Notification::query()->updateOrCreate(
            ['dedupe_key' => $alerta->chave()],
            [
                'categoria' => $alerta->categoria,
                'tipo' => $alerta->tipo,
                'prioridade' => $alerta->prioridade,
                'titulo' => $alerta->titulo,
                'mensagem' => $alerta->mensagem,
                'url' => $alerta->url,
                'permissao' => $alerta->permissao,
                'referencia_type' => $alerta->referenciaType,
                'referencia_id' => $alerta->referenciaId,
                'origem' => $origem,
                'expira_em' => $alerta->expiraEm,
                'resolved_at' => null,
            ],
        );
    }

    /**
     * Sincroniza uma categoria inteira e devolve quantos alertas existem agora.
     *
     * @param  list<Alerta>  $alertas
     */
    public function sincronizarCategoria(string $categoria, array $alertas): int
    {
        $chaves = [];

        foreach ($alertas as $alerta) {
            $chaves[] = $alerta->chave();

            $this->emitir($alerta, self::ORIGEM_VARREDURA);
        }

        $pendentes = Notification::query()
            ->where('origem', self::ORIGEM_VARREDURA)
            ->where('categoria', $categoria)
            ->whereNull('resolved_at');

        if ($chaves !== []) {
            $pendentes->whereNotIn('dedupe_key', $chaves);
        }

        $pendentes->update(['resolved_at' => now()]);

        return count($chaves);
    }

    /**
     * Fecha um alerta específico, usado quando um evento prova que a condição
     * acabou (ex.: o paciente assinou o termo que estava pendente).
     */
    public function resolver(string $dedupeKey): int
    {
        return Notification::query()
            ->where('dedupe_key', $dedupeKey)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }
}
