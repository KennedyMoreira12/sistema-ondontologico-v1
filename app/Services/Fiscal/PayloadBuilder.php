<?php

namespace App\Services\Fiscal;

use App\Models\ClinicProfile;
use App\Models\NotaFiscal;
use Illuminate\Support\Carbon;

/**
 * Traduz o modelo NotaFiscal + perfil da clínica para o payload neutro que
 * todos os provedores entendem.
 *
 * Nenhuma chamada HTTP acontece aqui, então a montagem pode ser testada
 * isoladamente e reaproveitada por qualquer driver.
 */
class PayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(NotaFiscal $nota): array
    {
        $nota->loadMissing(['paciente']);

        $perfil = ClinicProfile::current();
        $paciente = $nota->paciente;

        $valorServico = (float) ($nota->valor_total ?? 0);
        $desconto = (float) ($nota->valor_desconto ?? 0);
        $liquido = $nota->valor_liquido !== null
            ? (float) $nota->valor_liquido
            : max(0, $valorServico - $desconto);

        $aliquota = $nota->aliquota_iss !== null
            ? (float) $nota->aliquota_iss
            : (float) config('fiscal.aliquota_iss', 5.0);

        $iss = $nota->valor_iss !== null
            ? (float) $nota->valor_iss
            : round($liquido * ($aliquota / 100), 2);

        return [
            'ambiente' => $nota->ambiente ?: config('fiscal.ambiente', 'homologacao'),
            'tipo' => $nota->tipo,
            'serie' => (string) ($nota->serie ?: config('fiscal.serie', '1')),
            'numero' => (string) $nota->numero,
            'data_emissao' => ($nota->emitida_em ?? Carbon::now())->toIso8601String(),

            'prestador' => [
                'cnpj' => ChaveAcesso::somenteDigitos($perfil?->document),
                'razao_social' => $perfil?->legal_name,
                'nome_fantasia' => $perfil?->legal_name,
                'inscricao_municipal' => $perfil?->municipal_registration,
                'inscricao_estadual' => $perfil?->state_registration,
                'email' => $perfil?->email,
                'telefone' => ChaveAcesso::somenteDigitos($perfil?->phone),
                'cnae' => $perfil?->cnae,
                'regime_tributario' => $perfil?->tax_regime ?: config('fiscal.regime_tributario'),
                'endereco' => [
                    'logradouro' => $perfil?->address,
                    'numero' => $perfil?->number,
                    'complemento' => $perfil?->complement,
                    'bairro' => $perfil?->district,
                    'codigo_municipio' => $perfil?->city_ibge_code ?: config('fiscal.codigo_municipio'),
                    'cidade' => $perfil?->city,
                    'uf' => $perfil?->state,
                    'cep' => ChaveAcesso::somenteDigitos($perfil?->postal_code),
                ],
            ],

            'tomador' => [
                'nome' => $nota->tomador_nome ?: $paciente?->nome,
                'documento' => ChaveAcesso::somenteDigitos($nota->tomador_documento ?: $paciente?->cpf),
                'email' => $nota->tomador_email ?: $paciente?->email,
                'endereco' => [
                    'logradouro' => $nota->tomador_endereco ?: $paciente?->endereco,
                    'numero' => $paciente?->numero,
                    'complemento' => $paciente?->complemento,
                    'bairro' => $paciente?->bairro,
                    'cidade' => $nota->tomador_cidade ?: $paciente?->cidade,
                    'uf' => $nota->tomador_uf ?: $paciente?->estado,
                    'cep' => ChaveAcesso::somenteDigitos($nota->tomador_cep ?: $paciente?->cep),
                ],
            ],

            'servico' => [
                'codigo' => $nota->codigo_servico ?: config('fiscal.codigo_servico'),
                'descricao' => $nota->descricao_servico ?: config('fiscal.descricao_servico'),
                'aliquota_iss' => $aliquota,
            ],

            'valores' => [
                'servico' => round($valorServico, 2),
                'desconto' => round($desconto, 2),
                'liquido' => round($liquido, 2),
                'iss' => round($iss, 2),
            ],
        ];
    }
}
