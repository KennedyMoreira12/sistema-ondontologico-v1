<?php

namespace App\Services\Fiscal\Providers;

use App\Contracts\FiscalProviderInterface;
use App\Models\NotaFiscal;
use App\Services\Fiscal\ChaveAcesso;
use App\Services\Fiscal\FiscalResultado;
use App\Services\Fiscal\XmlBuilder;
use Illuminate\Support\Carbon;

/**
 * Provedor de desenvolvimento: emite de verdade do ponto de vista do sistema,
 * mas sem falar com prefeitura nenhuma.
 *
 * A chave é gerada no layout local e o XML é o do XmlBuilder. Serve para
 * homologar o fluxo inteiro (emitir, imprimir, cancelar, sincronizar) antes
 * de contratar um provedor. Nunca use em produção com valor fiscal.
 */
class MockFiscalProvider implements FiscalProviderInterface
{
    public function __construct(private readonly XmlBuilder $xml = new XmlBuilder) {}

    public function nome(): string
    {
        return 'mock';
    }

    public function emitir(NotaFiscal $nota, array $payload): FiscalResultado
    {
        $payload['numero'] = $payload['numero'] ?? $nota->numero;
        $payload['serie'] = $payload['serie'] ?? $nota->serie;
        $payload['data_emissao'] = $payload['data_emissao'] ?? Carbon::now()->toIso8601String();

        $cnpj = (string) ($payload['prestador']['cnpj'] ?? '');
        if (strlen($cnpj) !== 14) {
            $cnpj = '00000000000000';
        }

        $chave = ChaveAcesso::gerar(
            (string) ($payload['tipo'] ?? 'nfse'),
            $cnpj,
            (string) ($payload['serie'] ?? '1'),
            (string) ($payload['numero'] ?? '1'),
            Carbon::now(),
            (string) ($payload['prestador']['endereco']['codigo_municipio'] ?? config('fiscal.codigo_municipio', '')),
        );

        $nota->chave = $chave;
        $nota->status = 'autorizada';
        $nota->protocolo = 'MOCK'.Carbon::now()->format('YmdHis');
        $nota->emitida_em = Carbon::now();

        $xml = $this->xml->build($payload, $nota);

        return new FiscalResultado(
            sucesso: true,
            status: 'autorizada',
            mensagem: 'Emitida pelo provedor mock (sem validade fiscal).',
            chave: $chave,
            numero: $payload['numero'],
            serie: $payload['serie'],
            protocolo: $nota->protocolo,
            xml: $xml,
            resposta: ['provider' => 'mock', 'ambiente' => $payload['ambiente'] ?? 'homologacao'],
        );
    }

    public function cancelar(NotaFiscal $nota, string $motivo): FiscalResultado
    {
        return new FiscalResultado(
            sucesso: true,
            status: 'cancelada',
            mensagem: 'Cancelamento simulado pelo provedor mock.',
            chave: $nota->chave,
            protocolo: $nota->protocolo,
            resposta: ['provider' => 'mock', 'motivo' => $motivo],
        );
    }

    public function consultar(NotaFiscal $nota): FiscalResultado
    {
        return new FiscalResultado(
            sucesso: true,
            status: (string) $nota->status,
            mensagem: 'Consulta simulada pelo provedor mock.',
            chave: $nota->chave,
            numero: $nota->numero,
            serie: $nota->serie,
            protocolo: $nota->protocolo,
            resposta: ['provider' => 'mock'],
        );
    }
}
