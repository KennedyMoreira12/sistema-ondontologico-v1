<?php

namespace App\Models;

use App\Models\Central\User;
use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable(['paciente_id', 'user_id', 'tipo', 'conteudo', 'status', 'assinado_em', 'versao', 'conteudo_hash'])]
class Contract extends TenantModel
{
    public const TIPO_TERMO_RESPONSABILIDADE = 'responsabilidade';

    /**
     * Texto usado quando a clínica ainda não cadastrou um termo próprio. O
     * paciente lê este texto na ficha e assina contra uma cópia dele, que fica
     * gravada em `document_signatures.conteudo_assinado`.
     */
    public const TEXTO_TERMO_PADRAO = <<<'TEXTO'
TERMO DE RESPONSABILIDADE E CIÊNCIA

Eu, {paciente}, declaro que fui informado(a) pela equipe da {clinica} sobre o plano de cuidado, os procedimentos que serão realizados, os riscos e benefícios esperados, bem como as alternativas de tratamento existentes.

Declaro também que:
- Prestei informações verdadeiras sobre meu histórico de saúde, alergias, medicamentos em uso e condições preexistentes;
- Fui orientado(a) a comunicar qualquer alteração no meu quadro de saúde antes de cada atendimento;
- Compreendi as orientações de cuidado, os sinais de alerta e a importância de comparecer aos retornos;
- Autorizo o registro do meu atendimento na ficha clínica e o uso desses dados para continuidade do cuidado, conforme a LGPD.

Estou ciente de que este documento é uma assinatura manuscrita eletrônica, com registro de evidências, e não uma assinatura digital qualificada ICP-Brasil.
TEXTO;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assinado_em' => 'datetime',
            'versao' => 'integer',
        ];
    }

    /**
     * Todo contrato nasce na versão 1, com o hash do texto que está sendo
     * cadastrado. Sem isso, um contrato criado fora do controller ficaria com
     * `versao` nula e o hash do conteúdo não corresponderia ao texto.
     */
    protected static function booted(): void
    {
        static::creating(function (Contract $contrato): void {
            if ($contrato->versao === null) {
                $contrato->versao = 1;
            }

            if ($contrato->conteudo_hash === null) {
                $contrato->conteudo_hash = DocumentSignature::hashDoConteudo(
                    (int) $contrato->versao,
                    $contrato->conteudo,
                );
            }
        });
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assinaturas(): MorphMany
    {
        return $this->morphMany(DocumentSignature::class, 'signable');
    }

    /**
     * Assinatura que ainda vale para este termo. Substituída não conta.
     */
    public function assinaturaAtiva(): MorphOne
    {
        return $this->morphOne(DocumentSignature::class, 'signable')
            ->whereNull('superseded_at')
            ->latestOfMany('signed_at');
    }

    public function hashDoConteudoAtual(): string
    {
        return DocumentSignature::hashDoConteudo((int) $this->versao, $this->conteudo);
    }

    public static function textoTermoPadrao(?string $paciente, ?string $clinica): string
    {
        return strtr(self::TEXTO_TERMO_PADRAO, [
            '{paciente}' => $paciente ?: 'paciente',
            '{clinica}' => $clinica ?: 'clínica',
        ]);
    }
}
