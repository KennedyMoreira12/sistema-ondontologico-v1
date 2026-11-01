<?php

namespace App\Services\Platform\Prospeccao;

use App\Models\Central\AdminUser;
use App\Models\Central\Prospect;
use App\Models\Central\ProspectImport;
use App\Models\Central\ProspectOrigin;
use Illuminate\Support\Carbon;
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
            'atualizados' => [],
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
     * Prévia da importação, SEM gravar nada: roda o `import()` de verdade dentro
     * de uma transação e desfaz tudo no fim. Assim a contagem mostrada é
     * exatamente a do dia seguinte (mesma deduplicação, mesma idempotência),
     * sem duplicar a lógica de casamento de duplicatas em dois lugares.
     *
     * @param  array{source_file:string,source_key:string,default_city?:?string,default_state?:?string,default_category?:?string,rows:array<int,array<string,mixed>>}  $source
     * @return array{total:int,imported:int,updated:int,duplicated:int,pending:int,rejected:int,possiveis:int,existentes:int,linhas:array<int,array<string,mixed>>,report:array<string,mixed>}
     */
    public function preview(array $source): array
    {
        $conexao = DB::connection('central');
        $nivel = $conexao->transactionLevel();
        $conexao->beginTransaction();

        try {
            $import = $this->import($source);
        } finally {
            while ($conexao->transactionLevel() > $nivel) {
                $conexao->rollBack();
            }
        }

        $report = (array) ($import->report ?? []);

        $possiveis = collect($report['possiveis_duplicados'] ?? [])
            ->pluck('ref')
            ->merge(collect($report['telefones_compartilhados'] ?? [])->pluck('ref'))
            ->filter()
            ->map(fn ($ref): string => (string) $ref)
            ->unique();

        return [
            'total' => $import->total,
            'imported' => $import->imported,
            'updated' => $import->updated,
            'duplicated' => $import->duplicated,
            'pending' => $import->pending,
            'rejected' => $import->rejected,
            'possiveis' => $possiveis->count(),
            'existentes' => $import->updated + count((array) ($report['inalterados'] ?? [])),
            'linhas' => $this->montarLinhas((array) ($source['rows'] ?? []), $report, $possiveis->flip()->all()),
            'report' => $report,
        ];
    }

    /**
     * Traduz o relatório do importador em uma linha legível por contato, sem
     * reconsultar o banco (o que foi gravado já foi desfeito pela prévia).
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @param  array<string,mixed>  $report
     * @param  array<string,int>  $possiveis
     * @return array<int,array<string,mixed>>
     */
    private function montarLinhas(array $rows, array $report, array $possiveis): array
    {
        $porRef = function (array $itens): array {
            $mapa = [];

            foreach ($itens as $item) {
                $ref = (string) (($item['ref'] ?? '') ?? '');

                if ($ref !== '') {
                    $mapa[$ref] = $item;
                }
            }

            return $mapa;
        };

        $rejeitados = $porRef((array) ($report['rejeitados'] ?? []));
        $duplicados = $porRef((array) ($report['duplicados'] ?? []));
        $pendentes = $porRef((array) ($report['pendentes'] ?? []));
        $atualizados = array_flip(array_map('strval', (array) ($report['atualizados'] ?? [])));
        $inalterados = array_flip(array_map('strval', (array) ($report['inalterados'] ?? [])));

        $linhas = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $ref = (string) ($row['ref'] ?? '');
            $nome = trim((string) ($row['name'] ?? ''));
            $situacao = 'novo';
            $motivo = null;

            if (isset($rejeitados[$ref])) {
                $situacao = 'rejeitado';
                $motivo = (string) ($rejeitados[$ref]['motivo'] ?? 'registro inválido');
            } elseif (isset($atualizados[$ref])) {
                $situacao = 'atualizado';
            } elseif (isset($inalterados[$ref])) {
                $situacao = 'existente';
            } elseif (isset($duplicados[$ref])) {
                $situacao = 'duplicado';
                $motivo = 'Mesmo telefone/nome ou nome/endereço de um contato já cadastrado.';
            } elseif (isset($possiveis[$ref])) {
                $situacao = 'possivel';
                $motivo = 'Mesmo nome (ou telefone compartilhado) de outro contato. Será importado e marcado para revisão.';
            }

            if (isset($pendentes[$ref]) && $motivo === null) {
                $motivo = (string) ($pendentes[$ref]['motivo'] ?? 'telefone a confirmar');
            }

            $linhas[] = [
                'ref' => $ref,
                'name' => $nome,
                'city' => (string) ($row['city'] ?? ''),
                'phone' => (string) ($row['phone_original'] ?? ''),
                'situacao' => $situacao,
                'motivo' => $motivo,
            ];
        }

        return $linhas;
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
                $report['atualizados'][] = $ref;
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
            'city' => $this->blankToNull($row['city'] ?? null) ?? $this->blankToNull($source['default_city'] ?? null) ?? config('prospeccao.default_city'),
            'state' => $this->blankToNull($row['state'] ?? null) ?? $this->blankToNull($source['default_state'] ?? null) ?? config('prospeccao.default_state'),
            'status' => $this->statusKey($row['status'] ?? null),
            'notes' => $this->blankToNull($row['notes'] ?? null),
            'last_contact_at' => $this->parseDate($row['last_contact_at'] ?? null),
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

    /**
     * Converte o rótulo de status da lista/planilha na chave do funil.
     *
     * "Não contatada" (feminino, como vem na planilha) e "Não contatado" caem no
     * mesmo `nao_contatado`. Rótulos informativos que não são etapa de funil
     * (ex.: "Pesquisar telefone") não viram status inválido: ficam no padrão
     * inicial, e o texto original é preservado na observação/procedência.
     */
    private function statusKey(?string $status): string
    {
        $status = $this->blankToNull($status);
        $statuses = (array) config('prospeccao.statuses', []);
        $padrao = (string) config('prospeccao.default_status', 'nao_contatado');

        if ($status === null) {
            return $padrao;
        }

        if (array_key_exists($status, $statuses)) {
            return $status;
        }

        $normalizado = $this->normalizeName($status);

        foreach ($statuses as $chave => $meta) {
            if ($this->normalizeName((string) ($meta['nome'] ?? '')) === $normalizado) {
                return (string) $chave;
            }
        }

        if (str_contains($normalizado, 'nao contatad') && array_key_exists('nao_contatado', $statuses)) {
            return 'nao_contatado';
        }

        return $padrao;
    }

    private function parseDate(mixed $value): ?string
    {
        $value = $this->blankToNull($value);

        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (Throwable) {
            return null;
        }
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
