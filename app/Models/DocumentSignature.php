<?php

namespace App\Models;

use App\Models\Central\User;
use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * Assinatura manuscrita eletrônica de um documento da clínica.
 *
 * A linha é imutável: depois de criada, não pode ser alterada nem apagada. A
 * única mudança permitida é marcar `superseded_at`, que acontece quando o
 * termo é reeditado e o paciente precisa assinar de novo. O registro antigo
 * permanece — é ele que prova o que foi assinado e quando.
 *
 * NÃO é assinatura digital qualificada ICP-Brasil: é assinatura manuscrita
 * eletrônica, com evidências de integridade (hash do desenho e do conteúdo).
 */
#[Fillable([
    'paciente_id', 'user_id', 'signable_type', 'signable_id', 'document_type',
    'document_title', 'term_version', 'conteudo_assinado', 'content_hash',
    'signature_data', 'signature_hash', 'signed_at', 'ip', 'user_agent',
    'superseded_at',
])]
class DocumentSignature extends TenantModel
{
    public const TIPO_TERMO = 'termo_responsabilidade';

    public const TIPO_PRESENCA = 'presenca_sessao';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'term_version' => 'integer',
            'signed_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (DocumentSignature $assinatura): void {
            $permitidos = ['superseded_at', 'updated_at'];

            if (array_diff(array_keys($assinatura->getDirty()), $permitidos) !== []) {
                throw new RuntimeException('Assinatura já realizada é imutável: os dados não podem ser alterados.');
            }

            if ($assinatura->getOriginal('superseded_at') !== null) {
                throw new RuntimeException('Assinatura já foi substituída e não pode ser alterada novamente.');
            }
        });

        static::deleting(function (): void {
            throw new RuntimeException('Assinatura já realizada não pode ser apagada.');
        });
    }

    /**
     * Hash canônico do conteúdo assinado.
     *
     * A versão entra no hash: o mesmo texto em outra versão do termo é outra
     * assinatura, não uma reaproveitável.
     */
    public static function hashDoConteudo(int $versao, ?string $conteudo): string
    {
        return hash('sha256', $versao."\n".($conteudo ?? ''));
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signable(): MorphTo
    {
        return $this->morphTo();
    }

    public function estaAtiva(): bool
    {
        return $this->superseded_at === null;
    }

    /**
     * Reconfere os dois hashes gravados: o do desenho e o do conteúdo.
     */
    public function integra(): bool
    {
        $binario = $this->binario();

        if ($binario === null) {
            return false;
        }

        $desenhoConfere = hash_equals($this->signature_hash, hash('sha256', $binario));
        $conteudoConfere = hash_equals(
            $this->content_hash,
            self::hashDoConteudo((int) $this->term_version, $this->conteudo_assinado),
        );

        return $desenhoConfere && $conteudoConfere;
    }

    /**
     * Bytes do PNG assinado, ou null quando a base64 está corrompida.
     */
    public function binario(): ?string
    {
        $base64 = preg_replace('/^data:image\/png;base64,/', '', (string) $this->signature_data) ?? '';
        $binario = base64_decode($base64, true);

        return $binario === false ? null : $binario;
    }

    public function dataUri(): string
    {
        return 'data:image/png;base64,'.base64_encode((string) $this->binario());
    }
}
