<?php

namespace App\Services\Fiscal\Providers;

use App\Models\NotaFiscal;
use App\Services\Fiscal\FiscalException;
use App\Services\Fiscal\FiscalResultado;

/**
 * Provedor SendNF (NFS-e).
 *
 * Endpoints conferidos com a integração SendNF usada no projeto:
 *   POST /v1/nfse
 *   GET  /v1/documents/{id}
 *   POST /v1/documents/{id}/cancel
 *   GET  /v1/documents/{id}/xml
 *   GET  /v1/documents/{id}/pdf
 *
 * A empresa emitente precisa estar cadastrada na SendNF antes da primeira
 * emissão (`SENDFN_COMPANY_ID`).
 */
class SendNfProvider extends HttpFiscalProvider
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
        return 'sendnf';
    }

    /**
     * @return array<string, string>
     */
    protected function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->credencial];
    }

    public function emitir(NotaFiscal $nota, array $payload): FiscalResultado
    {
        if (blank($this->companyId)) {
            throw new FiscalException('SENDFN_COMPANY_ID não configurado.');
        }

        /*
         * Formato usado pela integração SendNF deste projeto: `unit_amount` em
         * CENTAVOS (mesma convenção de `valor_servico_cents` no FiscalService).
         * `wait=true` pede a resposta já processada em vez de só o protocolo.
         */
        $corpo = array_filter([
            'company_id' => $this->companyId,
            'reference' => (string) $nota->id,
            'external_id' => (string) $nota->id,
            'taker' => array_filter([
                'document' => $payload['tomador']['documento'] ?? null,
                'name' => $payload['tomador']['nome'] ?? null,
                'email' => $payload['tomador']['email'] ?? null,
            ], fn ($valor) => filled($valor)),
            'items' => [[
                'description' => $payload['servico']['descricao'] ?? null,
                'unit_amount' => (int) round(((float) ($payload['valores']['servico'] ?? 0)) * 100),
            ]],
        ], fn ($valor) => $valor !== null);

        $resposta = $this->enviar('post', '/v1/nfse?wait=true', $corpo, [
            'Idempotency-Key' => 'nota-'.$nota->id.'-'.$nota->tentativas,
        ]);

        $documentoId = $this->valor($resposta, ['id', 'document_id', 'document.id', 'data.id']);
        $chave = $this->valor($resposta, ['access_key', 'chave', 'nfse_key', 'key', 'data.access_key']);
        $status = $this->mapearStatus($this->valor($resposta, ['status', 'state', 'data.status']));

        $xml = $this->valor($resposta, ['xml'])
            ?? ($documentoId ? $this->baixarXml("/v1/documents/{$documentoId}/xml") : null);

        return new FiscalResultado(
            sucesso: in_array($status, ['autorizada', 'processando'], true),
            status: $status,
            mensagem: $this->valor($resposta, ['message', 'mensagem', 'detail']),
            chave: $chave,
            numero: $this->valor($resposta, ['number', 'numero', 'data.number']),
            serie: $this->valor($resposta, ['series', 'serie', 'data.series']),
            protocolo: $this->valor($resposta, ['protocol', 'protocolo', 'data.protocol']),
            xml: $xml,
            pdfUrl: $documentoId ? $this->urlPdf($documentoId) : null,
            resposta: ['provider' => 'sendnf', 'provider_id' => $documentoId] + $resposta,
        );
    }

    public function cancelar(NotaFiscal $nota, string $motivo): FiscalResultado
    {
        $documentoId = data_get($nota->dados, 'provider_id');

        if (blank($documentoId)) {
            throw new FiscalException('Nota sem documento vinculado na SendNF para cancelar.');
        }

        $resposta = $this->enviar('post', "/v1/documents/{$documentoId}/cancel", ['reason' => $motivo]);

        return new FiscalResultado(
            sucesso: true,
            status: 'cancelada',
            mensagem: $this->valor($resposta, ['message', 'mensagem']) ?? 'Cancelamento enviado à SendNF.',
            chave: $nota->chave,
            protocolo: $this->valor($resposta, ['protocol', 'protocolo']),
            resposta: ['provider' => 'sendnf'] + $resposta,
        );
    }

    public function consultar(NotaFiscal $nota): FiscalResultado
    {
        $documentoId = data_get($nota->dados, 'provider_id');

        if (blank($documentoId)) {
            throw new FiscalException('Nota sem documento vinculado na SendNF.');
        }

        $resposta = $this->enviar('get', "/v1/documents/{$documentoId}");
        $status = $this->mapearStatus($this->valor($resposta, ['status', 'state']));

        return new FiscalResultado(
            sucesso: true,
            status: $status,
            mensagem: $this->valor($resposta, ['message', 'mensagem']),
            chave: $this->valor($resposta, ['access_key', 'chave', 'key']),
            numero: $this->valor($resposta, ['number', 'numero']),
            serie: $this->valor($resposta, ['series', 'serie']),
            protocolo: $this->valor($resposta, ['protocol', 'protocolo']),
            xml: $status === 'autorizada' ? $this->baixarXml("/v1/documents/{$documentoId}/xml") : null,
            pdfUrl: $this->urlPdf($documentoId),
            resposta: ['provider' => 'sendnf'] + $resposta,
        );
    }

    protected function mapearStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'authorized', 'authorised', 'issued', 'processed', 'autorizada', 'emitida' => 'autorizada',
            'cancelled', 'canceled', 'cancelada' => 'cancelada',
            'rejected', 'denied', 'error', 'erro', 'rejeitada' => 'rejeitada',
            'processing', 'pending', 'queued', 'processando' => 'processando',
            default => $status ?: 'processando',
        };
    }

    protected function urlPdf(string $documentoId): string
    {
        return rtrim($this->baseUrl, '/')."/v1/documents/{$documentoId}/pdf";
    }
}
