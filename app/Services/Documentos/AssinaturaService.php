<?php

namespace App\Services\Documentos;

use App\Models\Central\User;
use App\Models\Contract;
use App\Models\DocumentSignature;
use App\Models\EvolucaoSessao;
use App\Models\Paciente;
use App\Services\AuditLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registro de assinatura manuscrita eletrônica.
 *
 * O serviço concentra as garantias que não podem depender da tela:
 * - a assinatura recebida é um PNG de verdade, dentro de um limite de tamanho;
 * - não existe segunda assinatura ativa para o mesmo documento;
 * - o desenho e o conteúdo assinado ganham hash SHA-256;
 * - a operação e a auditoria acontecem na mesma transação.
 */
class AssinaturaService
{
    private const TAMANHO_MINIMO = 30;

    private const TAMANHO_MAXIMO = 3 * 1024 * 1024;

    public function assinarContrato(Contract $contrato, string $assinatura, User $user): DocumentSignature
    {
        $contrato->loadMissing('paciente');

        return $this->registrar(
            signable: $contrato,
            paciente: $contrato->paciente,
            documentType: DocumentSignature::TIPO_TERMO,
            documentTitle: 'Termo de responsabilidade',
            versao: (int) $contrato->versao,
            conteudo: $contrato->conteudo,
            assinatura: $assinatura,
            user: $user,
            aoCriar: function (DocumentSignature $registrada) use ($contrato): void {
                $contrato->forceFill([
                    'status' => 'assinado',
                    'assinado_em' => $registrada->signed_at,
                ])->save();
            },
        );
    }

    public function assinarPresenca(EvolucaoSessao $evolucao, string $assinatura, User $user): DocumentSignature
    {
        $evolucao->loadMissing('paciente');

        return $this->registrar(
            signable: $evolucao,
            paciente: $evolucao->paciente,
            documentType: DocumentSignature::TIPO_PRESENCA,
            documentTitle: 'Confirmação de presença — sessão de '.($evolucao->data?->format('d/m/Y') ?? '—'),
            versao: 1,
            conteudo: $evolucao->conteudoParaAssinatura(),
            assinatura: $assinatura,
            user: $user,
        );
    }

    /**
     * Marca as assinaturas ativas de um documento como substituídas.
     *
     * A linha antiga continua no banco: o que muda é que ela deixa de valer
     * para o texto novo. É a forma de exigir nova assinatura sem apagar a
     * evidência anterior.
     */
    public function superseder(Model $signable, string $documentType, string $motivo): int
    {
        return DocumentSignature::query()
            ->where('signable_type', $signable->getMorphClass())
            ->where('signable_id', $signable->getKey())
            ->where('document_type', $documentType)
            ->whereNull('superseded_at')
            ->update(['superseded_at' => now()]);
    }

    public function assinaturaAtiva(Model $signable, string $documentType): ?DocumentSignature
    {
        return DocumentSignature::query()
            ->where('signable_type', $signable->getMorphClass())
            ->where('signable_id', $signable->getKey())
            ->where('document_type', $documentType)
            ->whereNull('superseded_at')
            ->latest('signed_at')
            ->first();
    }

    /**
     * @param  Closure(DocumentSignature): void|null  $aoCriar
     */
    private function registrar(
        Model $signable,
        ?Paciente $paciente,
        string $documentType,
        string $documentTitle,
        int $versao,
        ?string $conteudo,
        string $assinatura,
        User $user,
        ?Closure $aoCriar = null,
    ): DocumentSignature {
        if ($paciente === null) {
            throw ValidationException::withMessages([
                'assinatura' => 'O documento precisa estar vinculado a um paciente para ser assinado.',
            ]);
        }

        $binario = $this->decodificarPng($assinatura);

        if ($this->assinaturaAtiva($signable, $documentType) !== null) {
            throw ValidationException::withMessages([
                'assinatura' => 'Este documento já possui assinatura ativa. Para assinar de novo, o termo precisa ser alterado.',
            ]);
        }

        return DB::transaction(function () use (
            $signable, $paciente, $documentType, $documentTitle, $versao,
            $conteudo, $binario, $user, $aoCriar,
        ): DocumentSignature {
            $assinatura = DocumentSignature::create([
                'paciente_id' => $paciente->id,
                'user_id' => $user->id,
                'signable_type' => $signable->getMorphClass(),
                'signable_id' => $signable->getKey(),
                'document_type' => $documentType,
                'document_title' => $documentTitle,
                'term_version' => $versao,
                'conteudo_assinado' => $conteudo,
                'content_hash' => DocumentSignature::hashDoConteudo($versao, $conteudo),
                'signature_data' => 'data:image/png;base64,'.base64_encode($binario),
                'signature_hash' => hash('sha256', $binario),
                'signed_at' => now(),
                'ip' => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
            ]);

            if ($aoCriar !== null) {
                $aoCriar($assinatura);
            }

            AuditLogger::log('assinar_documento', 'document_signatures', $assinatura->id, depois: [
                'paciente_id' => $paciente->id,
                'documento' => $documentTitle,
                'tipo' => $documentType,
                'versao' => $versao,
                'content_hash' => $assinatura->content_hash,
                'signature_hash' => $assinatura->signature_hash,
                'signed_at' => $assinatura->signed_at?->toDateTimeString(),
            ], user: $user);

            return $assinatura;
        });
    }

    /**
     * Aceita somente data URI PNG, valida o cabeçalho e o tamanho.
     */
    private function decodificarPng(string $assinatura): string
    {
        if (! preg_match('#^data:image/png;base64,#', $assinatura)) {
            throw ValidationException::withMessages([
                'assinatura' => 'Assinatura inválida: envie a imagem PNG gerada pela área de assinatura.',
            ]);
        }

        $base64 = substr($assinatura, strpos($assinatura, ',') + 1);
        $binario = base64_decode($base64, true);

        if ($binario === false || $binario === '') {
            throw ValidationException::withMessages([
                'assinatura' => 'Assinatura inválida: a imagem não pôde ser lida.',
            ]);
        }

        if (strlen($binario) < self::TAMANHO_MINIMO) {
            throw ValidationException::withMessages([
                'assinatura' => 'Assinatura vazia: assine na área branca antes de confirmar.',
            ]);
        }

        if (strlen($binario) > self::TAMANHO_MAXIMO) {
            throw ValidationException::withMessages([
                'assinatura' => 'Assinatura muito grande: reduza o traço e tente novamente.',
            ]);
        }

        if (substr($binario, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            throw ValidationException::withMessages([
                'assinatura' => 'Assinatura inválida: o conteúdo enviado não é uma imagem PNG.',
            ]);
        }

        return $binario;
    }
}
