<?php

namespace App\Models;

use App\Models\Central\Clinic;
use App\Models\Central\User;
use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['clinica_id', 'user_id', 'nome', 'cpf', 'cro', 'registro_profissional', 'conselho_regional', 'tipo', 'comissao', 'is_active'])]
class Professional extends TenantModel
{
    /**
     * Tipos de profissional aceitos.
     *
     * `dentista` continua existindo e continua sendo o padrão: a clínica
     * odontológica não muda por causa da vertical de fisioterapia. O
     * `fisioterapeuta` é um tipo novo, e não uma renomeação. `nutricionista`
     * existe porque a planilha PROFISSIONAIS EXTERNOS registra a nutricionista
     * por diária; o tipo identifica quem é, sem fixar valor nem forma de
     * remuneração.
     *
     * @var list<string>
     */
    public const TIPOS = [
        'dentista',
        'fisioterapeuta',
        'nutricionista',
        'auxiliar',
        'recepcionista',
        'outro',
    ];

    /**
     * Registro no conselho: CRO para odontologia, CREFITO para fisioterapia.
     *
     * O campo continua sendo `cro` na coluna — nada foi renomeado — e o
     * registro do fisioterapeuta mora em `registro_profissional`, que é
     * aditivo. O método devolve o que aparece na tela e no relatório.
     */
    public function registroConselho(): ?string
    {
        return $this->registro_profissional ?: $this->cro;
    }

    public function conselhoRegional(): ?string
    {
        return $this->conselho_regional;
    }

    public function clinica(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
