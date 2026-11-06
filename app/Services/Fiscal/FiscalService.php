<?php

namespace App\Services\Fiscal;

use App\Models\AuditLog;
use App\Models\FiscalCompany;
use App\Models\Nfse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FiscalService
{
    public function __construct(protected SendNfClient $client) {}

    public function getOrCreateFiscalCompany(): FiscalCompany
    {
        return FiscalCompany::query()->firstOrCreate([], ['status' => 'not_configured']);
    }

    public function isEnabled(): bool
    {
        return (bool) config('fiscal.sendnf.enabled', false);
    }

    public function canEmit(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $company = $this->getOrCreateFiscalCompany();
        if (! $company->canEmitLocally()) {
            return false;
        }

        return true;
    }

    /**
     * Cadastra a empresa emitente na SendNF pelo CNPJ, vinculando-a à clínica
     * deste tenant via external_id. Não envia certificado aqui: o certificado A1
     * é enviado por requisição, nunca armazenado localmente.
     *
     * @param  array<string, mixed>  $extra
     * @return array{company: FiscalCompany, sendnf: array<string, mixed>}
     */
    public function registerCompany(string $externalId, string $cnpj, array $extra = []): array
    {
        if ($this->isEnabled() === false) {
            throw new RuntimeException('Integração SendNF desabilitada.');
        }

        $digits = preg_replace('/\D/', '', $cnpj);
        if ($digits === '') {
            throw new RuntimeException('CNPJ inválido.');
        }

        $payload = array_merge([
            'cnpj' => $digits,
            'external_id' => $externalId,
            'metadata' => ['origin' => 'lj_smartclinic_tenant'],
        ], $extra);

        $sendnf = $this->client->createCompany($payload);

        $company = FiscalCompany::query()->updateOrCreate(
            ['external_id' => $externalId],
            [
                'cnpj' => $digits,
                'razao_social' => $sendnf['razao_social'] ?? null,
                'nome_fantasia' => $sendnf['nome_fantasia'] ?? null,
                'sendnf_company_id' => $sendnf['id'] ?? null,
                'status' => $sendnf['state'] ?? 'not_configured',
                'last_check_at' => now(),
            ]
        );

        AuditFiscal::log('cadastrar_empresa', $company->id, depois: [
            'external_id' => $externalId,
            'cnpj' => $digits,
            'sendnf_company_id' => $company->sendnf_company_id,
            'status' => $company->status,
        ]);

        return ['company' => $company, 'sendnf' => $sendnf];
    }

    /**
     * Consulta o readiness/checklist de emissão na SendNF e atualiza o status
     * local. O status local NÃO é considerado confiável por si só: ele só muda
     * depois de consulta bem-sucedida à API.
     *
     * @return array{company: FiscalCompany, readiness: array<string, mixed>}
     */
    public function syncReadiness(FiscalCompany $company): array
    {
        if (! filled($company->sendnf_company_id)) {
            throw new RuntimeException('Empresa não cadastrada na SendNF.');
        }

        $readiness = $this->client->getReadiness($company->sendnf_company_id);

        $state = $readiness['state'] ?? $company->status;
        $company->update([
            'status' => $state,
            'readiness_payload' => $readiness,
            'last_check_at' => now(),
        ]);

        AuditFiscal::log('consultar_readiness', $company->id, depois: [
            'status' => $state,
            'nfse_ready' => $readiness['nfse_ready'] ?? null,
        ]);

        return ['company' => $company, 'readiness' => $readiness];
    }

    /**
     * Pré-visualiza a NFS-e sem transmitir nada. Gera/usa a chave de idempotência
     * e grava o rascunho com controle de concorrência.
     *
     * @param  array<string, mixed>  $dadosNota
     * @return array{draft: Nfse, preview: array<string, mixed>}
     */
    public function previewDraft(FiscalCompany $company, array $dadosNota): array
    {
        $key = $dadosNota['idempotency_key'] ?? $this->createIdempotencyKey('preview', $dadosNota);

        $draft = DB::transaction(function () use ($company, $dadosNota, $key) {
            $existing = Nfse::query()->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            return Nfse::create(array_merge($dadosNota, [
                'fiscal_company_id' => $company->id,
                'idempotency_key' => $key,
                'status' => 'draft',
                'issued_by_user_id' => Auth::id(),
            ]));
        });

        $previewPayload = array_filter(array_merge([
            'company_id' => $company->sendnf_company_id,
            'reference' => $draft->id,
            'taker' => [
                'document' => $dadosNota['tomador_documento'] ?? null,
                'name' => $dadosNota['tomador_nome'] ?? null,
                'email' => $dadosNota['tomador_email'] ?? null,
            ],
            'items' => [
                [
                    'description' => $dadosNota['servico_descricao'] ?? '',
                    'unit_amount' => $dadosNota['valor_servico_cents'] ?? 0,
                ],
            ],
        ], $dadosNota['preview_override'] ?? []), fn ($value) => $value !== null);

        $preview = $this->client->previewNfse($previewPayload, [
            'Idempotency-Key' => $key,
        ]);

        $draft->update([
            'request_hash' => $key,
            'response_sanitized' => $this->sanitizeResponse($preview),
        ]);

        AuditFiscal::log('pre_emitir_nfse', $draft->id, depois: [
            'idempotency_key' => $key,
            'preview_only' => true,
            'nfse_id' => $draft->id,
        ]);

        return ['draft' => $draft, 'preview' => $preview];
    }

    /**
     * Cria rascunho de NFS-e com idempotência e bloqueio de concorrência, sem
     * transmitir nada para a SendNF.
     *
     * @param  array<string, mixed>  $data
     */
    public function createDraftNfse(FiscalCompany $company, array $data): Nfse
    {
        $key = $data['idempotency_key'] ?? $this->createIdempotencyKey('draft', $data);

        return DB::transaction(function () use ($company, $data, $key) {
            $existing = Nfse::query()->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            return Nfse::create(array_merge($data, [
                'fiscal_company_id' => $company->id,
                'idempotency_key' => $key,
                'status' => 'draft',
                'issued_by_user_id' => Auth::id(),
            ]));
        });
    }

    /**
     * Emissão real: POST /v1/nfse. Bloqueada por canEmit(); não há rota acessível
     * para transmissão enquanto a integração estiver desabilitada.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $extraHeaders
     * @return array<string, mixed>
     */
    public function emitNfse(array $payload, array $extraHeaders = []): array
    {
        if (! $this->canEmit()) {
            throw new RuntimeException('Emissão fiscal não habilitada para esta clínica.');
        }

        return $this->client->emitNfse($payload, $extraHeaders);
    }

    /**
     * Lista documentos fiscais da SendNF (notas já transmitidas).
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function getDocuments(array $query = []): array
    {
        return $this->client->getDocuments($query);
    }

    /**
     * Detalhes de um documento na SendNF.
     *
     * @return array<string, mixed>
     */
    public function getDocument(string $sendnfId): array
    {
        return $this->client->getDocument($sendnfId);
    }

    public function getDocumentPdf(string $sendnfId)
    {
        return $this->client->getDocumentPdf($sendnfId);
    }

    public function getDocumentXml(string $sendnfId)
    {
        return $this->client->getDocumentXml($sendnfId);
    }

    public function createIdempotencyKey(string $context, array $payload): string
    {
        $hash = hash('sha256', json_encode([$context, $payload]));

        return 'nfse_'.$hash;
    }

    /**
     * Remove dados sensíveis antes de gravar a resposta da SendNF no banco.
     * Nunca grava certificados, senhas, tokens ou payload fiscal completo.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    protected function sanitizeResponse(array $response): array
    {
        $sensiveis = ['pfx_base64', 'password', 'secret', 'token', 'certificate'];
        $limpo = [];
        foreach ($response as $chave => $valor) {
            if (in_array(strtolower((string) $chave), $sensiveis, true)) {
                continue;
            }
            $limpo[$chave] = $valor;
        }

        return $limpo;
    }
}

/**
 * Auditoria fiscal isolada: registra ações de configuração e consulta, sem
 * expor segredos. Usa a mesma tabela `audit_logs` do tenant.
 */
class AuditFiscal
{
    public static function log(string $acao, ?int $registroId = null, ?array $antes = null, ?array $depois = null): void
    {
        $antes = self::sanitize($antes);
        $depois = self::sanitize($depois);

        AuditLog::create([
            'user_id' => Auth::id(),
            'acao' => $acao,
            'tabela' => 'fiscal',
            'registro_id' => $registroId,
            'antes' => $antes,
            'depois' => $depois,
            'ip' => self::clientIp(),
        ]);
    }

    private static function sanitize(?array $dados): ?array
    {
        if ($dados === null) {
            return null;
        }

        $sensiveis = ['password', 'senha', 'token', 'secret', 'pfx_base64', 'certificate', 'conteudo'];
        $limpo = [];
        foreach ($dados as $chave => $valor) {
            if (in_array(strtolower((string) $chave), $sensiveis, true)) {
                continue;
            }
            $limpo[$chave] = $valor;
        }

        return $limpo;
    }

    private static function clientIp(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        return request()->ip();
    }
}
