<?php

namespace App\Services\Tenant;

use Illuminate\Support\Facades\Schema;

/**
 * Inventário das tabelas do banco da clínica, com a vertical a que cada uma
 * pertence.
 *
 * A vertical NÃO remove nada do banco. Ela decide quais módulos a clínica
 * enxerga; as tabelas de odontologia continuam existindo para toda clínica, e as
 * de fisioterapia também. Este inventário existe para que essa promessa seja
 * VERIFICÁVEL, e não apenas uma intenção: a auditoria abaixo acusa tabela
 * sem classificação, tabela da vertical que sumiu e tabela da outra vertical
 * que foi apagada.
 *
 * As tabelas do catálogo (procedimentos, convênios, formas de pagamento,
 * categorias de produto) são de cada clínica: elas moram no banco dela, e por
 * isso uma clínica cadastrar um procedimento não muda nada na outra.
 */
class InventarioTabelas
{
    public const COMUM = 'comum';

    public const ODONTOLOGIA = 'odontologia';

    public const FISIOTERAPIA = 'fisioterapia';

    /**
     * Tabelas que só a clínica odontológica usa.
     *
     * @var list<string>
     */
    public const TABELAS_ODONTOLOGIA = [
        'odontograms',
        'odontogram_teeth',
        'odontogram_tooth_conditions',
        'tooth_conditions',
    ];

    /**
     * Tabelas que só a clínica de fisioterapia usa.
     *
     * @var list<string>
     */
    public const TABELAS_FISIOTERAPIA = [
        'avaliacoes',
        'evolucoes_sessao',
        'pacotes_sessao',
        'consumo_sessao',
    ];

    /**
     * Tabelas usadas pelas duas verticais.
     *
     * `treatment_plans` e `treatment_plan_items` têm campo de dente, e mesmo
     * assim são comuns: o plano de tratamento serve às duas verticais e quem
     * escreve nele é o profissional, com o que entende do caso.
     *
     * @var list<string>
     */
    public const TABELAS_COMUNS = [
        'accounts_receivable',
        'anamneses',
        'appointment_statuses',
        'appointments',
        'audit_logs',
        'budget_items',
        'budgets',
        'clinic_profile',
        'clinic_settings',
        'clinical_notes',
        'contracts',
        'installments',
        'insurance_companies',
        'insurance_plans',
        'medical_alerts',
        'pacientes',
        'patient_documents',
        'patient_insurances',
        'payment_methods',
        'payments',
        'permissions',
        'prescription_items',
        'prescriptions',
        'procedures',
        'product_categories',
        'products',
        'professional_schedules',
        'professional_specialty',
        'professionals',
        'role_permission',
        'roles',
        'rooms',
        'social_posts',
        'social_settings',
        'specialties',
        'stock_movements',
        'suppliers',
        'treatment_plan_items',
        'treatment_plans',
        'user_roles',
        'waiting_lists',
        'whatsapp_conversations',
        'whatsapp_messages',
    ];

    /**
     * Tabelas da infraestrutura do Laravel, que não são da clínica.
     *
     * @var list<string>
     */
    public const TABELAS_INFRA = [
        'cache',
        'failed_jobs',
        'job_batches',
        'jobs',
        'migrations',
        'password_reset_tokens',
        'sessions',
        'sqlite_sequence',
    ];

    /**
     * Todas as tabelas que o inventário conhece, com a vertical de cada uma.
     *
     * @return array<string, string>
     */
    public function classificacao(): array
    {
        $tabelas = [];

        foreach (self::TABELAS_ODONTOLOGIA as $tabela) {
            $tabelas[$tabela] = self::ODONTOLOGIA;
        }

        foreach (self::TABELAS_FISIOTERAPIA as $tabela) {
            $tabelas[$tabela] = self::FISIOTERAPIA;
        }

        foreach (self::TABELAS_COMUNS as $tabela) {
            $tabelas[$tabela] = self::COMUM;
        }

        foreach (self::TABELAS_INFRA as $tabela) {
            $tabelas[$tabela] = self::COMUM;
        }

        return $tabelas;
    }

    /**
     * A que vertical esta tabela pertence?
     */
    public function verticalDaTabela(string $tabela): ?string
    {
        return $this->classificacao()[$tabela] ?? null;
    }

    /**
     * Tabelas que existem no banco da clínica em atendimento.
     *
     * O nome vem sem o esquema (`main.pacientes` no SQLite vira `pacientes`):
     * o inventário é sobre as tabelas, e o prefixo do banco é um detalhe do
     * driver.
     *
     * @return list<string>
     */
    public function tabelasExistentes(): array
    {
        $tabelas = [];

        foreach (Schema::connection('tenant')->getTables() as $tabela) {
            $nome = is_object($tabela)
                ? ($tabela->name ?? null)
                : (is_array($tabela) ? ($tabela['name'] ?? null) : null);

            if (is_string($nome) && $nome !== '') {
                $tabelas[] = $nome;
            }
        }

        sort($tabelas);

        return array_values(array_unique($tabelas));
    }

    /**
     * Auditoria do banco da clínica em atendimento.
     *
     * @return array{ok: bool, problemas: list<string>, tabelas: list<string>}
     */
    public function auditar(): array
    {
        $tabelas = $this->tabelasExistentes();
        $classificacao = $this->classificacao();
        $vertical = app(VerticalManager::class)->vertical();
        $outra = $vertical === VerticalManager::FISIOTERAPIA
            ? VerticalManager::ODONTOLOGIA
            : VerticalManager::FISIOTERAPIA;

        $problemas = [];

        foreach ($tabelas as $tabela) {
            if (! array_key_exists($tabela, $classificacao)) {
                $problemas[] = "Tabela sem classificação de vertical: {$tabela}.";
            }
        }

        foreach ($this->tabelasDaVertical($vertical) as $tabela) {
            if (! in_array($tabela, $tabelas, true)) {
                $problemas[] = "Tabela da vertical {$vertical} ausente: {$tabela}.";
            }
        }

        foreach ($this->tabelasDaVertical($outra) as $tabela) {
            if (! in_array($tabela, $tabelas, true)) {
                $problemas[] = "Tabela da vertical {$outra} foi removida: {$tabela}.";
            }
        }

        return [
            'ok' => $problemas === [],
            'problemas' => $problemas,
            'tabelas' => $tabelas,
        ];
    }

    /**
     * Tabelas de uma vertical (as de infraestrutura ficam de fora).
     *
     * @return list<string>
     */
    public function tabelasDaVertical(string $vertical): array
    {
        $lista = match ($vertical) {
            VerticalManager::ODONTOLOGIA => self::TABELAS_ODONTOLOGIA,
            VerticalManager::FISIOTERAPIA => self::TABELAS_FISIOTERAPIA,
            default => [],
        };

        return array_values($lista);
    }
}
