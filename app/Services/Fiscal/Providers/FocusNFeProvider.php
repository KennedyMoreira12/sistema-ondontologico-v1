<?php

namespace App\Services\Fiscal\Providers;

use App\Models\NotaFiscal;
use App\Services\Fiscal\FiscalResultado;

/**
 * Provedor Focus NFe (NFS-e).
 *
 * Autenticação HTTP Basic com o token como usuário. O `ref` da nota é o id
 * local, então emitir duas vezes o mesmo rascunho reaproveita o documento em
 * vez de duplicar.
 *
 * Endpoints: POST/GET/DELETE /v2/nfse/{ref}. Confirme a versão da API e o
 * município habilitado no painel da Focus antes de usar em produção.
 */
class FocusNFeProvider extends HttpFiscalProvider
{
    public function nome(): string
    {
        return 'focusnfe';
    }

    /**
     * @return array<string, string>
     */
    protected function headers(): array
    {
        return ['Authorization' => 'Basic '.base64_encode($this->credencial.':')];
    }

    public function emitir(NotaFiscal $nota, array $payload): FiscalResultado
    {
        $corpo = [
            'cnpj_prestador' => $payload['prestador']['cnpj'] ?? null,
            'inscricao_municipal' => $payload['prestador']['inscricao_municipal'] ?? null,
            'data_emissao' => $payload['data_emissao'] ?? null,
            'servico' => [
                'codigo' => $payload['servico']['codigo'] ?? null,
                'descricao' => $payload['servico']['descricao'] ?? null,
                'aliquota' => $payload['servico']['aliquota_iss'] ?? null,
                'iss_retido' => false,
            ],
            'prestador' => $payload['prestador'] ?? [],
            'tomador' => $payload['tomador'] ?? [],
            'valor_total' => $payload['valores']['servico'] ?? 0,
            'valor_iss' => $payload['valores']['iss'] ?? 0,
            'valor_desconto' => $payload['valores']['desconto'] ?? 0,
            'valor_liquido' => $payload['valores']['liquido'] ?? 0,
        ];

        $resposta = $this->enviar('post', '/v2/nfse?ref='.urlencode((string) $nota->id), $corpo);
        $ref = (string) $nota->id;

        return new FiscalResultado(
            sucesso: in_array($this->mapearStatus($this->valor($resposta, ['status'])), ['autorizada', 'processando'], true),
            status: $this->mapearStatus($this->valor($resposta, ['status'])),
            mensagem: $this->valor($resposta, ['mensagem', 'message', 'erro']),
            chave: $this->valor($resposta, ['chave_nfse', 'chave', 'codigo_verificacao']),
            numero: $this->valor($resposta, ['numero', 'numero_rps']),
            serie: $this->valor($resposta, ['serie', 'serie_rps']),
            protocolo: $this->valor($resposta, ['protocolo', 'numero_rps']),
            xml: $this->valor($resposta, ['caminho_xml_nota_fiscal', 'xml'])
                ?? $this->baixarXml("/v2/nfse/{$ref}.xml"),
            pdfUrl: $this->valor($resposta, ['url_danfse', 'url'])
                ?? rtrim($this->baseUrl, '/')."/v2/nfse/{$ref}.pdf",
            resposta: ['provider' => 'focusnfe'] + $resposta,
        );
    }

    public function cancelar(NotaFiscal $nota, string $motivo): FiscalResultado
    {
        $ref = (string) $nota->id;
        $resposta = $this->enviar('delete', "/v2/nfse/{$ref}", ['justificativa' => $motivo]);

        return new FiscalResultado(
            sucesso: true,
            status: 'cancelada',
            mensagem: $this->valor($resposta, ['mensagem', 'message']) ?? 'Cancelamento solicitado à Focus NFe.',
            chave: $nota->chave,
            resposta: ['provider' => 'focusnfe'] + $resposta,
        );
    }

    public function consultar(NotaFiscal $nota): FiscalResultado
    {
        $ref = (string) $nota->id;
        $resposta = $this->enviar('get', "/v2/nfse/{$ref}");

        return new FiscalResultado(
            sucesso: true,
            status: $this->mapearStatus($this->valor($resposta, ['status'])),
            mensagem: $this->valor($resposta, ['mensagem', 'message']),
            chave: $this->valor($resposta, ['chave_nfse', 'chave', 'codigo_verificacao']),
            numero: $this->valor($resposta, ['numero', 'numero_rps']),
            serie: $this->valor($resposta, ['serie', 'serie_rps']),
            protocolo: $this->valor($resposta, ['protocolo', 'numero_rps']),
            pdfUrl: rtrim($this->baseUrl, '/')."/v2/nfse/{$ref}.pdf",
            resposta: ['provider' => 'focusnfe'] + $resposta,
        );
    }

    protected function mapearStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'autorizado', 'autorizada', 'authorized' => 'autorizada',
            'cancelado', 'cancelada', 'cancelled', 'canceled' => 'cancelada',
            'erro', 'rejeitado', 'rejeitada', 'rejected', 'error' => 'rejeitada',
            default => 'processando',
        };
    }
}
