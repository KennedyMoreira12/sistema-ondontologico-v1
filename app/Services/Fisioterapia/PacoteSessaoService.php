<?php

namespace App\Services\Fisioterapia;

use App\Exceptions\PacoteSessaoIndisponivel;
use App\Exceptions\SaldoDeSessoesInsuficiente;
use App\Models\ConsumoSessao;
use App\Models\PacoteSessao;
use Illuminate\Support\Facades\DB;

/**
 * Consumo de sessão de pacote de fisioterapia.
 *
 * O saldo do pacote é dinheiro já recebido, então a regra é dura: nenhuma
 * sessão é consumida sem saldo, e o saldo nunca fica negativo nem passa do
 * total contratado. Três camadas seguram isso:
 *
 * 1. a transação, que não deixa a linha do consumo existir sem o débito;
 * 2. o bloqueio de linha (`lockForUpdate`), que serializa duas atendentes
 *    marcando sessão no mesmo pacote ao mesmo tempo;
 * 3. o UPDATE condicional, que só debita se ainda houver saldo — e é o que
 *    funciona no SQLite, onde o bloqueio de linha não existe.
 *
 * A camada 3 é a que fecha: se duas transações chegarem juntas, uma só
 * consegue satisfazer a condição `sessoes_usadas < total_sessoes`, e a outra
 * recebe zero linhas afetadas e recusa o consumo.
 */
class PacoteSessaoService
{
    /**
     * Consome uma sessão do pacote e devolve o registro do extrato.
     *
     * @param  array{
     *     paciente_id: int,
     *     profissional_id?: int|null,
     *     evolucao_sessao_id?: int|null,
     *     data?: string|null,
     *     valor?: float|null,
     *     observacoes?: string|null
     * }  $dados
     *
     * @throws SaldoDeSessoesInsuficiente
     * @throws PacoteSessaoIndisponivel
     */
    public function consumir(PacoteSessao $pacote, array $dados): ConsumoSessao
    {
        $conexao = $pacote->getConnection();

        return $conexao->transaction(function () use ($pacote, $dados): ConsumoSessao {
            /**
             * Trava a linha do pacote enquanto a transação estiver aberta. Duas
             * atendentes marcando sessão no mesmo pacote entram em fila aqui,
             * e a segunda vê o saldo já atualizado pela primeira.
             */
            $pacoteTravado = PacoteSessao::whereKey($pacote->getKey())->lockForUpdate()->first();

            if ($pacoteTravado === null) {
                throw PacoteSessaoIndisponivel::paraPacote('#'.$pacote->getKey(), 'inexistente');
            }

            if ($pacoteTravado->status !== PacoteSessao::ATIVO) {
                throw PacoteSessaoIndisponivel::paraPacote($pacoteTravado->nome, $pacoteTravado->status);
            }

            if ($pacoteTravado->sessoes_usadas >= $pacoteTravado->total_sessoes) {
                throw SaldoDeSessoesInsuficiente::paraPacote($pacoteTravado->nome, $pacoteTravado->saldo());
            }

            $consumo = ConsumoSessao::create([
                'pacote_id' => $pacoteTravado->id,
                'paciente_id' => $dados['paciente_id'],
                'profissional_id' => $dados['profissional_id'] ?? null,
                'evolucao_sessao_id' => $dados['evolucao_sessao_id'] ?? null,
                'data' => $dados['data'] ?? now()->toDateString(),
                'valor' => $dados['valor'] ?? $pacoteTravado->valor_sessao,
                'observacoes' => $dados['observacoes'] ?? null,
            ]);

            /**
             * Débito condicional: a condição `sessoes_usadas < total_sessoes`
             * faz parte do próprio UPDATE, então o banco decide quem passa. Se
             * ninguém passar, zero linhas são afetadas e o consumo é recusado
             * — a transação inteira desfaz, e o extrato criado acima some
             * junto.
             */
            $debitadas = PacoteSessao::whereKey($pacoteTravado->getKey())
                ->whereColumn('sessoes_usadas', '<', 'total_sessoes')
                ->update(['sessoes_usadas' => DB::raw('sessoes_usadas + 1')]);

            if ($debitadas === 0) {
                throw SaldoDeSessoesInsuficiente::paraPacote($pacoteTravado->nome, $pacoteTravado->saldo());
            }

            $pacoteTravado->refresh();

            if ($pacoteTravado->sessoes_usadas >= $pacoteTravado->total_sessoes) {
                $pacoteTravado->update(['status' => PacoteSessao::CONCLUIDO]);
            }

            return $consumo;
        });
    }
}
