<?php

namespace App\Services\Fiscal\Providers;

use App\Models\NotaFiscal;
use App\Services\Fiscal\FiscalException;
use App\Services\Fiscal\FiscalResultado;

/**
 * Provedor NFe.io (NFS-e).
 *
 * Autenticação por `api_key` como query string, o formato adotado pela
 * NFe.io. A empresa emitente é identificada por `NFEIO_COMPANY_ID`.
 *
 * Endpoints: POST/GET /v2/companies/{companyId}/serviceinvoices. Confirme no
 * painel da NFe.io se o município da clínica está habilitado.
 */
class NFeIoProvider extends HttpFiscalProvider
{
    public function __construct(
        string $baseUrl,
        string $credencial,
        private readonly ?string $companyId = null,
    ) {
        parent::__construct($baseUrl, $credencial);
    }

    public function nome(): string
    {
        return 'nfeio';
    }

    /**
     * @return array<string, string>
     */
    protected function headers(): array
    {
        return [];
    }

    /**
     * A NFe.io autentica por query string, não por header.
     */
    protected function caminho(string $caminho): string
    {
        $separador = str_contains($caminho, '?') ? '&' : '?';

        return $caminho.$separador.'api_key='.urlencode($this->credencial);
    }

    public function emitir(NotaFiscal $nota, array $payload): FiscalResultado
    {
        if (blank($this->companyId)) {
            throw new FiscalException('NFEIO_COMPANY_ID não configurado.');
        }

        $corpo = [
            'cityServiceCode' => $payload['servico']['codigo'] ?? null,
            'description' => $payload['servico']['descricao'] ?? null,
            'servicesAmount' => $payload['valores']['servico'] ?? 0,
            'issRate' => ($payload['servico']['aliquota_iss'] ?? 0) / 100,
            'borrower' => [
                'name' => $payload['tomador']['nome'] ?? null,
                'federalTaxNumber' => $payload['tomador']['documento'] ?? null,
                'email' => $payload['tomador']['email'] ?? null,
                'address' => [
                    'country' => 'BRA',
                    'postalCode' => $payload['tomador']['endereco']['cep'] ?? null,
                    'street' => $payload['tomador']['endereco']['logradouro'] ?? null,
                    'number' => $payload['tomador']['endereco']['numero'] ?? null,
                    'district' => $payload['tomador']['endereco']['bairro'] ?? null,
                    'city' => $payload['tomador']['endereco']['cidade'] ?? null,
                    'state' => $payload['tomador']['endereco']['uf'] ?? null,
                ],
            ],
            'externalId' => (string) $nota->id,
        ];

        $resposta = $this->enviar(
            'post',
            $this->caminho("/v2/companies/{$this->companyId}/serviceinvoices"),
            $corpo,
        );

        $id = $this->valor($resposta, ['id', 'serviceInvoice.id']);

        return new FiscalResultado(
            sucesso: true,
            status: $this->mapearStatus($this->valor($resposta, ['status', 'state'])),
            mensagem: $this->valor($resposta, ['message', 'mensagem']),
            chave: $this->valor($resposta, ['checkCode', 'check_code', 'chave']),
            numero: $this->valor($resposta, ['number', 'numero']),
            serie: $this->valor($resposta, ['serie', 'series']),
            protocolo: $id,
            pdfUrl: $id ? rtrim($this->baseUrl, '/')."/v2/companies/{$this->companyId}/serviceinvoices/{$id}.pdf" : null,
            resposta: ['provider' => 'nfeio', 'provider_id' => $id] + $resposta,
        );
    }

    public function cancelar(NotaFiscal $nota, string $motivo): FiscalResultado
    {
        $id = data_get($nota->dados, 'provider_id');

        if (blank($id)) {
            throw new FiscalException('Nota sem documento vinculado na NFe.io para cancelar.');
        }

        $resposta = $this->enviar(
            'delete',
            $this->caminho("/v2/companies/{$this->companyId}/serviceinvoices/{$id}"),
            ['reason' => $motivo],
        );

        return new FiscalResultado(
            sucesso: true,
            status: 'cancelada',
            mensagem: $this->valor($resposta, ['message', 'mensagem']) ?? 'Cancelamento solicitado à NFe.io.',
            chave: $nota->chave,
            resposta: ['provider' => 'nfeio'] + $resposta,
        );
    }

    public function consultar(NotaFiscal $nota): FiscalResultado
    {
        $id = data_get($nota->dados, 'provider_id');

        if (blank($id)) {
            throw new FiscalException('Nota sem documento vinculado na NFe.io.');
        }

        $resposta = $this->enviar(
            'get',
            $this->caminho("/v2/companies/{$this->companyId}/serviceinvoices/{$id}"),
        );

        return new FiscalResultado(
            sucesso: true,
            status: $this->mapearStatus($this->valor($resposta, ['status', 'state'])),
            mensagem: $this->valor($resposta, ['message', 'mensagem']),
            chave: $this->valor($resposta, ['checkCode', 'check_code', 'chave']),
            numero: $this->valor($resposta, ['number', 'numero']),
            serie: $this->valor($resposta, ['serie', 'series']),
            protocolo: $id,
            resposta: ['provider' => 'nfeio'] + $resposta,
        );
    }

    protected function mapearStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'issued', 'authorized', 'autorizada', 'autorizado' => 'autorizada',
            'cancelled', 'canceled', 'cancelada' => 'cancelada',
            'rejected', 'denied', 'error', 'rejeitada' => 'rejeitada',
            'processing', 'pending', 'processando' => 'processando',
            default => $status ?: 'processando',
        };
    }
}
