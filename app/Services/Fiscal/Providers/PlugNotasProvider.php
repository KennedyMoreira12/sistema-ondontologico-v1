<?php

namespace App\Services\Fiscal\Providers;

use App\Models\NotaFiscal;
use App\Services\Fiscal\FiscalException;
use App\Services\Fiscal\FiscalResultado;

/**
 * Provedor PlugNotas (NFS-e).
 *
 * Autenticação pelo header `x-api-key`. A emissão é assíncrona: a resposta
 * pode vir como `processando` e o comando `fiscal:sincronizar` atualiza o
 * status depois.
 *
 * Endpoints: POST /nfse, GET /nfse/{id}, POST /nfse/{id}/cancelamento.
 */
class PlugNotasProvider extends HttpFiscalProvider
{
    public function nome(): string
    {
        return 'plugnotas';
    }

    /**
     * @return array<string, string>
     */
    protected function headers(): array
    {
        return ['x-api-key' => $this->credencial];
    }

    public function emitir(NotaFiscal $nota, array $payload): FiscalResultado
    {
        $corpo = [
            'prestador' => [
                'cpfCnpj' => $payload['prestador']['cnpj'] ?? null,
                'inscricaoMunicipal' => $payload['prestador']['inscricao_municipal'] ?? null,
                'razaoSocial' => $payload['prestador']['razao_social'] ?? null,
                'email' => $payload['prestador']['email'] ?? null,
                'endereco' => $this->endereco($payload['prestador']['endereco'] ?? []),
            ],
            'tomador' => [
                'cpfCnpj' => $payload['tomador']['documento'] ?? null,
                'razaoSocial' => $payload['tomador']['nome'] ?? null,
                'email' => $payload['tomador']['email'] ?? null,
                'endereco' => $this->endereco($payload['tomador']['endereco'] ?? []),
            ],
            'servico' => [[
                'codigo' => $payload['servico']['codigo'] ?? null,
                'descricao' => $payload['servico']['descricao'] ?? null,
                'aliquota' => $payload['servico']['aliquota_iss'] ?? null,
                'valor' => $payload['valores']['servico'] ?? 0,
                'desconto' => $payload['valores']['desconto'] ?? 0,
            ]],
            'ambiente' => $payload['ambiente'] === 'producao' ? 'producao' : 'homologacao',
            'externalId' => (string) $nota->id,
        ];

        $resposta = $this->enviar('post', '/nfse', $corpo);
        $id = $this->valor($resposta, ['id', 'nfse.id', 'data.id']);

        return new FiscalResultado(
            sucesso: true,
            status: $this->mapearStatus($this->valor($resposta, ['status', 'situacao'])),
            mensagem: $this->valor($resposta, ['mensagem', 'message', 'erro']),
            chave: $this->valor($resposta, ['chave', 'chaveAcesso', 'nfse.chave']),
            numero: $this->valor($resposta, ['numero', 'nfse.numero']),
            serie: $this->valor($resposta, ['serie', 'nfse.serie']),
            protocolo: $this->valor($resposta, ['protocolo', 'nfse.protocolo']) ?? $id,
            pdfUrl: $this->valor($resposta, ['pdf', 'urlPdf', 'nfse.pdf'])
                ?? ($id ? rtrim($this->baseUrl, '/')."/nfse/{$id}/pdf" : null),
            resposta: ['provider' => 'plugnotas', 'provider_id' => $id] + $resposta,
        );
    }

    public function cancelar(NotaFiscal $nota, string $motivo): FiscalResultado
    {
        $id = data_get($nota->dados, 'provider_id');

        if (blank($id)) {
            throw new FiscalException('Nota sem documento vinculado no PlugNotas para cancelar.');
        }

        $resposta = $this->enviar('post', "/nfse/{$id}/cancelamento", ['motivo' => $motivo]);

        return new FiscalResultado(
            sucesso: true,
            status: 'cancelada',
            mensagem: $this->valor($resposta, ['mensagem', 'message']) ?? 'Cancelamento enviado ao PlugNotas.',
            chave: $nota->chave,
            resposta: ['provider' => 'plugnotas'] + $resposta,
        );
    }

    public function consultar(NotaFiscal $nota): FiscalResultado
    {
        $id = data_get($nota->dados, 'provider_id');

        if (blank($id)) {
            throw new FiscalException('Nota sem documento vinculado no PlugNotas.');
        }

        $resposta = $this->enviar('get', "/nfse/{$id}");

        return new FiscalResultado(
            sucesso: true,
            status: $this->mapearStatus($this->valor($resposta, ['status', 'situacao'])),
            mensagem: $this->valor($resposta, ['mensagem', 'message']),
            chave: $this->valor($resposta, ['chave', 'chaveAcesso', 'nfse.chave']),
            numero: $this->valor($resposta, ['numero', 'nfse.numero']),
            serie: $this->valor($resposta, ['serie', 'nfse.serie']),
            protocolo: $this->valor($resposta, ['protocolo', 'nfse.protocolo']) ?? $id,
            resposta: ['provider' => 'plugnotas'] + $resposta,
        );
    }

    /**
     * @param  array<string, mixed>  $endereco
     * @return array<string, mixed>
     */
    protected function endereco(array $endereco): array
    {
        return [
            'logradouro' => $endereco['logradouro'] ?? null,
            'numero' => $endereco['numero'] ?? null,
            'complemento' => $endereco['complemento'] ?? null,
            'bairro' => $endereco['bairro'] ?? null,
            'cep' => $endereco['cep'] ?? null,
            'cidade' => $endereco['cidade'] ?? null,
            'uf' => $endereco['uf'] ?? null,
            'codigoIbge' => $endereco['codigo_municipio'] ?? null,
        ];
    }

    protected function mapearStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'autorizada', 'autorizado', 'authorized', 'concluido', 'concluída', 'success' => 'autorizada',
            'cancelada', 'cancelado', 'cancelled', 'canceled' => 'cancelada',
            'rejeitada', 'rejeitado', 'erro', 'error', 'rejected' => 'rejeitada',
            default => 'processando',
        };
    }
}
