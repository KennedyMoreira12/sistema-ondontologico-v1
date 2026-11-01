<?php

namespace App\Services\Platform\Prospeccao;

use App\Models\Central\AdminUser;
use App\Models\Central\Prospect;
use App\Models\Central\ProspectImport;
use App\Models\Central\ProspectOrigin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Importa as listas de prospecção (JSON extraído dos PDFs) de forma idempotente
 * e conservadora.
 *
 * - Idempotência por procedência (source, source_ref): reprocessar a mesma lista
 *   não cria contatos nem duplica registros.
 * - Deduplicação só com evidência forte: telefone normalizado + nome iguais, ou
 *   nome + endereço iguais. Telefone compartilhado por nomes diferentes e nomes
 *   parecidos são apenas sinalizados, nunca fundidos.
 * - Nunca sobrescreve valor já preenchido (merge só preenche lacunas), para não
 *   apagar dado vindo de outra lista nem edição manual do operador.
 * - Cada linha roda em transação própria; erro numa linha registra o motivo e
 *   não interrompe o restante.
 */
class ProspectImporter
{
    public function __construct(private readonly ProspectPhone $phone) {}

    /**
     * @param  array{source_file:string,source_key:string,default_city?:?string,default_state?:?string,default_category?:?string,rows:array<int,array<string,mixed>>}  $source
     */
    public function import(array $source, ?AdminUser $admin = null): ProspectImport
    {
        $import = ProspectImport::query()->create([
            'source_file' => (string) ($source['source_file'] ?? 'desconhecido.pdf'),
            'source_key' => (string) ($source['source_key'] ?? Str::slug((string) ($source['source_file'] ?? 'lista'))),
            'admin_user_id' => $admin?->id,
        ]);

        $stats = ['total' => 0, 'imported' => 0, 'updated' => 0, 'duplicated' => 0, 'pending' => 0, 'rejected' => 0];
        $report = [
            'rejeitados' => [],
            'duplicados' => [],
            'possiveis_duplicados' => [],
            'telefones_compartilhados' => [],
            'pendentes' => [],
            'inalterados' => [],
        ];

        foreach ((array) ($source['rows'] ?? []) as $row) {
            $stats['total']++;
            $row = (array) $row;

            try {
                DB::connection('central')->transaction(function () use ($row, $source, $import, &$stats, &$report): void {
                    $this->processRow($row, $source, $import, $stats, $report);
                });
            } catch (Throwable $e) {
                $stats['rejected']++;
                $report['rejeitados'][] = [
                    'ref' => $row['ref'] ?? null,
                    'name' => $row['name'] ?? null,
                    'motivo' => $e->getMessage(),
                ];
            }
        }

        $import->update([
            'total' => $stats['total'],
            'imported' => $stats['imported'],
            'updated' => $stats['updated'],
            'duplicated' => $stats['duplicated'],
            'pending' => $stats['pending'],
            'rejected' => $stats['rejected'],
            'report' => $report,
        ]);

        return $import->fresh();
    }

    /**
     * @param  array<string,mixed>  $row
     * @param  array<string,mixed>  $source
     */
    private function processRow(array $row, array $source, ProspectImport $import, array &$stats, array &$report): void
    {
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('registro sem nome');
        }

        $sourceKey = (string) ($source['source_key'] ?? '');
        $ref = isset($row['ref']) ? (string) $row['ref'] : null;

        $phone = $this->phone->analyze($row['phone_original'] ?? null);
        $nameNormalized = $this->normalizeName($name);
        $address = $this->blankToNull($row['address'] ?? null);

        $data = $this->dataFields($row, $source, $phone, $nameNormalized, $address);

        // 1) Idempotência: a MESMA linha (source+ref) já foi importada.
        $origin = ProspectOrigin::query()
            ->where('source', $sourceKey)
            ->where('source_ref', $ref)
            ->with('prospect')
            ->first();

        if ($origin?->prospect) {
            if ($this->fillGaps($origin->prospect, $data)) {
                $stats['updated']++;
            } else {
                $report['inalterados'][] = $ref;
            }

            return;
        }

        // 2) Deduplicação conservadora.
        $ambiguous = false;
        $candidate = $this->findDuplicate($data, $ref, $name, $report, $ambiguous);

        if ($candidate) {
            $this->fillGaps($candidate, $data);
            $this->attachOrigin($candidate, $source, $row, $sourceKey, $ref);
            $stats['duplicated']++;
            $report['duplicados'][] = [
                'ref' => $ref,
                'name' => $name,
                'prospect_id' => $candidate->id,
                'source' => $sourceKey,
            ];
            $this->countPending($phone, $ambiguous, $ref, $name, $stats, $report, 'duplicado fundido');

            return;
        }

        // 3) Novo contato.
        $prospect = Prospect::query()->create($data + [
            'source' => $sourceKey,
            'source_ref' => $ref,
            'import_id' => $import->id,
        ]);
        $this->attachOrigin($prospect, $source, $row, $sourceKey, $ref);
        $stats['imported']++;
        $this->countPending($phone, $ambiguous, $ref, $name, $stats, $report, 'novo');
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function findDuplicate(array $data, ?string $ref, string $name, array &$report, bool &$ambiguous): ?Prospect
    {
        $normalizedPhone = $data['phone_normalized'] ?? null;
        $normalizedName = $data['name_normalized'];

        if ($normalizedPhone !== null) {
            $exact = Prospect::query()
                ->where('phone_normalized', $normalizedPhone)
                ->where('name_normalized', $normalizedName)
                ->first();

            if ($exact) {
                return $exact;
            }

            $shared = Prospect::query()->where('phone_normalized', $normalizedPhone)->first();
            if ($shared) {
                $ambiguous = true;
                $report['telefones_compartilhados'][] = [
                    'ref' => $ref,
                    'name' => $name,
                    'telefone' => $data['phone_original'] ?? null,
                    'prospect_id' => $shared->id,
                    'name_existente' => $shared->name,
                ];
            }
        }

        $address = $data['address'] ?? null;
        if ($address !== null) {
            $byAddress = Prospect::query()
                ->where('name_normalized', $normalizedName)
                ->where('address', $address)
                ->first();

            if ($byAddress) {
                return $byAddress;
            }
        }

        if (Prospect::query()->where('name_normalized', $normalizedName)->exists()) {
            $ambiguous = true;
            $report['possiveis_duplicados'][] = [
                'ref' => $ref,
                'name' => $name,
                'motivo' => 'mesmo nome, sem telefone/endereço confirmando',
            ];
        }

        return null;
    }

    /**
     * Só preenche campos ainda vazios. Jamais sobrescreve valor existente.
     *
     * @param  array<string,mixed>  $data
     */
    private function fillGaps(Prospect $prospect, array $data): bool
    {
        $changed = false;

        foreach ($data as $key => $value) {
            if ($prospect->getAttribute($key) !== null) {
                continue;
            }

            if ($value !== null) {
                $prospect->setAttribute($key, $value);
                $changed = true;
            }
        }

        if ($changed) {
            $prospect->save();
        }

        return $changed;
    }

    /**
     * @param  array<string,mixed>  $source
     * @param  array<string,mixed>  $row
     */
    private function attachOrigin(Prospect $prospect, array $source, array $row, string $sourceKey, ?string $ref): void
    {
        ProspectOrigin::query()->create([
            'prospect_id' => $prospect->id,
            'source' => $sourceKey,
            'source_file' => $source['source_file'] ?? null,
            'source_ref' => $ref,
            'raw' => $row,
        ]);
    }

    /**
     * @param  array<string,mixed>  $row
     * @param  array<string,mixed>  $source
     * @param  array{original:?string,normalized:?string,alt:?string,status:string,reason:?string}  $phone
     * @return array<string,mixed>
     */
    private function dataFields(array $row, array $source, array $phone, string $nameNormalized, ?string $address): array
    {
        return [
            'name' => trim((string) $row['name']),
            'name_normalized' => $nameNormalized,
            'type' => $this->blankToNull($row['type'] ?? null),
            'category' => $this->blankToNull($row['category'] ?? null) ?? $this->blankToNull($source['default_category'] ?? null),
            'specialty' => $this->blankToNull($row['specialty'] ?? null),
            'phone_original' => $phone['original'],
            'phone_alt' => $phone['alt'],
            'phone_normalized' => $phone['normalized'],
            'phone_status' => $phone['status'],
            'address' => $address,
            'priority' => $this->priorityKey($row['priority'] ?? null),
            'city' => $this->blankToNull($source['default_city'] ?? null) ?? config('prospeccao.default_city'),
            'state' => $this->blankToNull($source['default_state'] ?? null) ?? config('prospeccao.default_state'),
        ];
    }

    /**
     * @param  array{original:?string,normalized:?string,alt:?string,status:string,reason:?string}  $phone
     */
    private function countPending(array $phone, bool $ambiguous, ?string $ref, string $name, array &$stats, array &$report, string $contexto): void
    {
        if ($phone['status'] === 'valid' && ! $ambiguous) {
            return;
        }

        $motivos = [];
        if ($phone['status'] !== 'valid') {
            $motivos[] = $phone['reason'] ?? $phone['status'];
        }
        if ($ambiguous) {
            $motivos[] = 'possível duplicidade (revisar)';
        }

        $stats['pending']++;
        $report['pendentes'][] = [
            'ref' => $ref,
            'name' => $name,
            'phone_status' => $phone['status'],
            'motivo' => implode('; ', $motivos),
            'contexto' => $contexto,
        ];
    }

    private function priorityKey(?string $priority): ?string
    {
        $priority = $this->blankToNull($priority);
        if ($priority === null) {
            return 'media';
        }

        return match (Str::lower(Str::ascii($priority))) {
            'alta', 'urgente' => 'alta',
            'baixa' => 'baixa',
            default => 'media',
        };
    }

    private function normalizeName(string $name): string
    {
        $ascii = Str::ascii(Str::lower($name));
        $spaced = (string) preg_replace('/[^a-z0-9]+/', ' ', $ascii);

        return trim((string) preg_replace('/\s+/', ' ', $spaced));
    }

    private function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
