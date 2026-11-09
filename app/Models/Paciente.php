<?php

namespace App\Models;

use App\Models\Central\Clinic;
use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['clinica_id', 'nome', 'cpf', 'rg', 'nascimento', 'sexo', 'email', 'phone', 'celular', 'cep', 'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'responsavel', 'observacoes', 'status'])]
class Paciente extends TenantModel
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nascimento' => 'date',
        ];
    }

    public function clinica(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function anamneses(): HasMany
    {
        return $this->hasMany(Anamnese::class);
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(MedicalAlert::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(PatientDocument::class);
    }

    public function odontogramas(): HasMany
    {
        return $this->hasMany(Odontogram::class);
    }

    public function consultas(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function evolucoes(): HasMany
    {
        return $this->hasMany(ClinicalNote::class);
    }

    /**
     * Fichas de avaliação da fisioterapia.
     */
    public function avaliacoes(): HasMany
    {
        return $this->hasMany(Avaliacao::class);
    }

    /**
     * Evoluções por sessão da fisioterapia (uma por atendimento).
     */
    public function evolucoesSessao(): HasMany
    {
        return $this->hasMany(EvolucaoSessao::class);
    }

    /**
     * Pacotes de sessões contratados.
     */
    public function pacotesSessao(): HasMany
    {
        return $this->hasMany(PacoteSessao::class);
    }

    /**
     * Turmas de Pilates das quais o paciente é aluno.
     */
    public function turmas(): BelongsToMany
    {
        return $this->belongsToMany(Turma::class, 'turma_pacientes')
            ->withPivot(['data_inicio', 'data_fim', 'is_active'])
            ->withTimestamps();
    }

    /**
     * Presenças do paciente nas turmas.
     */
    public function presencasTurmas(): HasMany
    {
        return $this->hasMany(TurmaPresenca::class);
    }

    public function planosTratamento(): HasMany
    {
        return $this->hasMany(TreatmentPlan::class);
    }

    public function orcamentos(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function contasReceber(): HasMany
    {
        return $this->hasMany(AccountsReceivable::class);
    }

    /**
     * Pagamentos recebidos do paciente.
     *
     * Nem todo pagamento tem vínculo (os importados da planilha foram
     * revisados um a um), por isso a relação pode voltar vazia mesmo quando
     * existe recebimento lançado no caixa.
     */
    public function pagamentos(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function prescricoes(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function convenios(): HasMany
    {
        return $this->hasMany(PatientInsurance::class);
    }
}
