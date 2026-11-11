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
    /**
     * Todas as tabelas específicas do banco da clínica (não infraestrutura).
     *
     * Como o sistema é vertical-agnóstico (atende clínicas de qualquer ramo),
     * essas tabelas estão disponíveis para qualquer clínica, independentemente
     * do ramo de atuação.
     *
     * @var list<string>
     */
    public const TABELAS_ESPECIFICAS = [
        'odontograms',
        'odontogram_teeth',
        'odontogram_tooth_conditions',
        'tooth_conditions',
        'avaliacoes',
        'evolucoes_sessao',
        'pacotes_sessao',
        'consumo_sessao',
        'turmas',
        'turma_pacientes',
        'turma_presencas',
        'anamneses',
        'clinical_notes',
        'medical_alerts',
        'prescriptions',
        'prescription_items',
        'treatment_plan_items',
        'treatment_plans',
        'budget_items',
        'budgets',
        'patient_documents',
        'professional_schedules',
        'professional_specialty',
        'rooms',
        'social_posts',
        'social_settings',
        'stock_movements',
        'suppliers',
        'whatsapp_alerts',
        'whatsapp_conversations',
        'whatsapp_messages',
        'whatsapp_notes',
        'whatsapp_outbox',
        'whatsapp_quick_replies',
        'whatsapp_rules',
    ];

    /**
     * Tabelas comuns a qualquer clínica (usuários, permissões, cadastros).
     *
     * @var list<string>
     */
    public const TABELAS_COMUNS = [
        'accounts_payable',
        'accounts_payable_pagamentos',
        'accounts_receivable',
        'appointment_statuses',
        'appointments',
        'audit_logs',
        'clinic_profile',
        'clinic_settings',
        'contracts',
        'financeiro_categorias',
        'fiscal_companies',
        'installments',
        'insurance_companies',
        'insurance_plans',
        'nfse',
        'nfse_events',
        'pacientes',
        'patient_insurances',
        'payment_methods',
        'payments',
        'permissions',
        'procedures',
        'product_categories',
        'products',
        'professionals',
        'role_permission',
        'roles',
        'specialties',
        'user_roles',
        'waiting_lists',
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
     * Todas as tabelas que o inventário conhece, classificadas como comuns.
     *
     * @return array<string, string>
     */
    public function classificacao(): array
    {
        $tabelas = [];

        foreach (self::TABELAS_ESPECIFICAS as $tabela) {
            $tabelas[$tabela] = 'comum';
        }

        foreach (self::TABELAS_COMUNS as $tabela) {
            $tabelas[$tabela] = 'comum';
        }

        foreach (self::TABELAS_INFRA as $tabela) {
            $tabelas[$tabela] = 'comum';
        }

        return $tabelas;
    }

    /**
     * A classificação de uma tabela (sempre 'comum' no sistema vertical-agnóstico).
     */
    public function classificacaoDaTabela(string $tabela): ?string
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

        $problemas = [];

        foreach ($tabelas as $tabela) {
            if (! array_key_exists($tabela, $classificacao)) {
                $problemas[] = "Tabela sem classificação: {$tabela}.";
            }
        }

        return [
            'ok' => $problemas === [],
            'problemas' => $problemas,
            'tabelas' => $tabelas,
        ];
    }

    /**
     * Todas as tabelas específicas do sistema (não infraestrutura).
     *
     * @return list<string>
     */
    public function tabelasEspecificas(): array
    {
        return array_values(self::TABELAS_ESPECIFICAS);
    }
}
