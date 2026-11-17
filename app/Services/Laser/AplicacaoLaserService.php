<?php

namespace App\Services\Laser;

use App\Models\AplicacaoLaser;

/**
 * Registro de uma aplicação de laser.
 *
 * O número da sessão é sequencial por paciente e região, calculado dentro da
 * mesma transação que cria a linha. Duas pessoas registrando ao mesmo tempo
 * não podem receber o mesmo número: a segunda leitura enxerga a primeira
 * depois do commit, e o `lockForUpdate` serializa a disputa onde o banco
 * suporta.
 */
class AplicacaoLaserService
{
    /**
     * @param  array{
     *     paciente_id: int,
     *     regiao_id: int,
     *     pacote_id?: int|null,
     *     profissional_id?: int|null,
     *     data: string,
     *     observacoes?: string|null
     * }  $dados
     */
    public function registrar(array $dados): AplicacaoLaser
    {
        $conexao = (new AplicacaoLaser)->getConnection();

        return $conexao->transaction(function () use ($dados): AplicacaoLaser {
            $ultimoNumero = AplicacaoLaser::query()
                ->where('paciente_id', $dados['paciente_id'])
                ->where('regiao_id', $dados['regiao_id'])
                ->lockForUpdate()
                ->max('numero_sessao');

            return AplicacaoLaser::create([
                'paciente_id' => $dados['paciente_id'],
                'regiao_id' => $dados['regiao_id'],
                'pacote_id' => $dados['pacote_id'] ?? null,
                'profissional_id' => $dados['profissional_id'] ?? null,
                'data' => $dados['data'],
                'numero_sessao' => ((int) $ultimoNumero) + 1,
                'observacoes' => $dados['observacoes'] ?? null,
            ]);
        });
    }
}
