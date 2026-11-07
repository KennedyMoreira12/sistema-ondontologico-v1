<?php

namespace App\Services\Fiscal;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SendNfClient
{
    /**
     * Monta requisição base para SendNF. Não realiza requisições automáticas.
     *
     * @param  array<string, string>  $extraHeaders
     */
    protected function request(array $extraHeaders = []): PendingRequest
    {
        $baseUrl = rtrim((string) config('fiscal.sendnf.base_url'), '/');
        $apiKey = (string) config('fiscal.sendnf.api_key');

        if ($baseUrl === '') {
            throw new RuntimeException('SENDFN_BASE_URL não configurada.');
        }

        if ($apiKey === '') {
            throw new RuntimeException('SENDFN_API_KEY não configurada.');
        }

        $connectTimeout = (int) config('fiscal.sendnf.timeout.connect', 10);
        $requestTimeout = (int) config('fiscal.sendnf.timeout.request', 30);

        $headers = array_merge([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$apiKey,
        ], $extraHeaders);

        return Http::withHeaders($headers)
            ->timeout($requestTimeout)
            ->connectTimeout($connectTimeout)
            ->baseUrl($baseUrl);
    }

    /**
     * Envia requisição GET. Não expõe segredos em exceções/logs.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $extraHeaders
     * @return array<string, mixed>
     */
    public function get(string $endpoint, array $query = [], array $extraHeaders = []): array
    {
        try {
            $response = $this->request($extraHeaders)->get(ltrim($endpoint, '/'), $query);

            return $this->handleResponse($response);
        } catch (ConnectionException $e) {
            Log::warning('Falha de conexão com SendNF.', ['endpoint' => $endpoint]);
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        } catch (RequestException $e) {
            Log::warning('Timeout/erro de requisição com SendNF.', ['endpoint' => $endpoint]);
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        }
    }

    /**
     * Envia requisição POST. Não expõe segredos em exceções/logs.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $extraHeaders
     * @return array<string, mixed>
     */
    public function post(string $endpoint, array $payload = [], array $extraHeaders = []): array
    {
        try {
            $response = $this->request($extraHeaders)->post(ltrim($endpoint, '/'), $payload);

            return $this->handleResponse($response);
        } catch (ConnectionException $e) {
            Log::warning('Falha de conexão com SendNF.', ['endpoint' => $endpoint]);
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        } catch (RequestException $e) {
            Log::warning('Timeout/erro de requisição com SendNF.', ['endpoint' => $endpoint]);
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        }
    }

    /**
     * Trata respostas HTTP sem registrar segredos/dados sensíveis completos.
     *
     * @return array<string, mixed>
     */
    protected function handleResponse(Response $response): array
    {
        $status = $response->status();

        if ($response->successful()) {
            $json = $response->json();

            return is_array($json) ? $json : [];
        }

        if ($status === 401 || $status === 403) {
            Log::warning('Falha de autenticação com SendNF.', ['status' => $status]);
            throw new RuntimeException('Falha de autenticação com SendNF.');
        }

        if ($status === 429) {
            Log::warning('Limite de requisições SendNF excedido.', ['status' => $status]);
            throw new RuntimeException('Limite de requisições excedido na SendNF.');
        }

        if ($status >= 400 && $status < 500) {
            Log::warning('Erro de validação/requisição na SendNF.', ['status' => $status]);
            throw new RuntimeException('Erro de requisição à SendNF.');
        }

        if ($status >= 500) {
            Log::warning('Erro no servidor SendNF.', ['status' => $status]);
            throw new RuntimeException('Erro no servidor SendNF.');
        }

        throw new RuntimeException('Resposta inesperada da SendNF.');
    }

    /**
     * Consulta dados públicos de um CNPJ (sistema da prefeitura e códigos de serviço sugeridos).
     * Endpoint sem necessidade de empresa cadastrada: GET /v1/lookup/cnpj/{cnpj}
     *
     * @return array<string, mixed>
     */
    public function lookupCnpj(string $cnpj): array
    {
        $digits = preg_replace('/\D/', '', $cnpj);
        try {
            return $this->handleResponse($this->request()->get("v1/lookup/cnpj/{$digits}"));
        } catch (ConnectionException $e) {
            Log::warning('Falha de conexão com SendNF (lookup CNPJ).', ['cnpj' => $digits]);
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        } catch (RequestException $e) {
            Log::warning('Timeout/erro de requisição com SendNF (lookup CNPJ).', ['cnpj' => $digits]);
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        }
    }

    /**
     * Cadastra a empresa emitente pelo CNPJ (basta o CNPJ; aceita external_id, metadata).
     * Endpoint: POST /v1/companies
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCompany(array $payload): array
    {
        try {
            return $this->handleResponse($this->request()->post('v1/companies', $payload));
        } catch (ConnectionException $e) {
            Log::warning('Falha de conexão com SendNF (cadastro empresa).');
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        } catch (RequestException $e) {
            Log::warning('Timeout/erro de requisição com SendNF (cadastro empresa).');
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        }
    }

    /**
     * Busca empresa por id próprio: GET /v1/companies/{id}
     *
     * @return array<string, mixed>
     */
    public function getCompany(string $id): array
    {
        try {
            return $this->handleResponse($this->request()->get("v1/companies/{$id}"));
        } catch (ConnectionException $e) {
            Log::warning('Falha de conexão com SendNF (buscar empresa).');
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        } catch (RequestException $e) {
            Log::warning('Timeout/erro de requisição com SendNF (buscar empresa).');
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        }
    }

    /**
     * Busca empresa pelo external_id (id da clínica no sistema): GET /v1/companies/by-external-id/{external_id}
     *
     * @return array<string, mixed>
     */
    public function getCompanyByExternalId(string $externalId): array
    {
        try {
            return $this->handleResponse($this->request()->get("v1/companies/by-external-id/{$externalId}"));
        } catch (ConnectionException $e) {
            Log::warning('Falha de conexão com SendNF (buscar empresa por external_id).');
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        } catch (RequestException $e) {
            Log::warning('Timeout/erro de requisição com SendNF (buscar empresa por external_id).');
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        }
    }

    /**
     * Consultar readiness/checklist de emissão da empresa: GET /v1/companies/{id}/readiness
     *
     * @return array<string, mixed>
     */
    public function getReadiness(string $id): array
    {
        try {
            return $this->handleResponse($this->request()->get("v1/companies/{$id}/readiness"));
        } catch (ConnectionException $e) {
            Log::warning('Falha de conexão com SendNF (readiness).');
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        } catch (RequestException $e) {
            Log::warning('Timeout/erro de requisição com SendNF (readiness).');
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        }
    }

    /**
     * Pré-emissão da NFS-e sem transmitir nada: POST /v1/nfse/preview.
     * Aceita o header Idempotency-Key para evitar pré-emissões duplicadas.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $extraHeaders
     * @return array<string, mixed>
     */
    public function previewNfse(array $payload, array $extraHeaders = []): array
    {
        try {
            return $this->handleResponse($this->request($extraHeaders)->post('v1/nfse/preview', $payload));
        } catch (ConnectionException $e) {
            Log::warning('Falha de conexão com SendNF (pré-emissão).');
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        } catch (RequestException $e) {
            Log::warning('Timeout/erro de requisição com SendNF (pré-emissão).');
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        }
    }

    /**
     * Emissão real da NFS-e: POST /v1/nfse (aceita ?wait=true e Idempotency-Key).
     * Disponível no cliente, mas a rota de transmissão permanece bloqueada
     * enquanto SENDFN_ENABLED=false e a clínica não estiver habilitada.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $extraHeaders
     * @return array<string, mixed>
     */
    public function emitNfse(array $payload, array $extraHeaders = []): array
    {
        try {
            return $this->handleResponse($this->request($extraHeaders)->post('v1/nfse', $payload));
        } catch (ConnectionException $e) {
            Log::warning('Falha de conexão com SendNF (emissão).');
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        } catch (RequestException $e) {
            Log::warning('Timeout/erro de requisição com SendNF (emissão).');
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        }
    }

    /**
     * Lista documentos fiscais (notas): GET /v1/documents.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function getDocuments(array $query = []): array
    {
        try {
            return $this->handleResponse($this->request()->get('v1/documents', $query));
        } catch (ConnectionException $e) {
            Log::warning('Falha de conexão com SendNF (listar documentos).');
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        } catch (RequestException $e) {
            Log::warning('Timeout/erro de requisição com SendNF (listar documentos).');
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        }
    }

    /**
     * Status e detalhes de um documento: GET /v1/documents/{id}.
     *
     * @return array<string, mixed>
     */
    public function getDocument(string $id): array
    {
        try {
            return $this->handleResponse($this->request()->get("v1/documents/{$id}"));
        } catch (ConnectionException $e) {
            Log::warning('Falha de conexão com SendNF (detalhar documento).');
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        } catch (RequestException $e) {
            Log::warning('Timeout/erro de requisição com SendNF (detalhar documento).');
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        }
    }

    /**
     * PDF do documento (DANFSe/DANFC-e): GET /v1/documents/{id}/pdf.
     * Retorna o conteúdo binário do PDF, não um array JSON.
     */
    public function getDocumentPdf(string $id): Response
    {
        try {
            return $this->request()->withHeaders(['Accept' => 'application/pdf'])->get("v1/documents/{$id}/pdf");
        } catch (ConnectionException $e) {
            Log::warning('Falha de conexão com SendNF (PDF do documento).');
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        } catch (RequestException $e) {
            Log::warning('Timeout/erro de requisição com SendNF (PDF do documento).');
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        }
    }

    /**
     * XML autorizado do documento: GET /v1/documents/{id}/xml.
     */
    public function getDocumentXml(string $id): Response
    {
        try {
            return $this->request()->withHeaders(['Accept' => 'application/xml'])->get("v1/documents/{$id}/xml");
        } catch (ConnectionException $e) {
            Log::warning('Falha de conexão com SendNF (XML do documento).');
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        } catch (RequestException $e) {
            Log::warning('Timeout/erro de requisição com SendNF (XML do documento).');
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        }
    }

    /**
     * Cancelamento de nota autorizada: POST /v1/documents/{id}/cancel.
     * Só disponível se a API e a regra fiscal permitirem; a rota de transmissão
     * permanece bloqueada até autorização explícita.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $extraHeaders
     * @return array<string, mixed>
     */
    public function cancelDocument(string $id, array $payload = [], array $extraHeaders = []): array
    {
        try {
            return $this->handleResponse($this->request($extraHeaders)->post("v1/documents/{$id}/cancel", $payload));
        } catch (ConnectionException $e) {
            Log::warning('Falha de conexão com SendNF (cancelar documento).');
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        } catch (RequestException $e) {
            Log::warning('Timeout/erro de requisição com SendNF (cancelar documento).');
            throw new RuntimeException('Falha de conexão com SendNF.', previous: $e);
        }
    }
}
