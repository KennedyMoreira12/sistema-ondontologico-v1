# MATRIZ DE MIGRATIONS — Implementação da Daiane

**Versão de referência:** commit `d73d2c1ae37368c32f0b063d226aecd03acfec8e`  
**Escopo:** migrations de tenant (banco de cada clínica). Não há migrations de banco central nesta implementação.

---

## 1. Migrations novas e modificadas (ordem cronológica)

| # | Migration | Data | Tipo | Banco afetado |
|---|---|---|---|---|
| 1 | `2026_10_03_073934_add_idempotency_key_to_appointments_table.php` | 03/10/2026 | Alteração | Tenant |
| 2 | `2026_10_21_000005_create_pacotes_sessao_table.php` | 21/10/2026 | Criação | Tenant |
| 3 | `2026_10_21_000006_create_consumo_sessao_table.php` | 21/10/2026 | Criação | Tenant |
| 4 | `2026_10_22_000001_create_fiscal_tables.php` | 22/10/2026 | Criação | Tenant |
| 5 | `2026_11_05_081447_add_financeiro_e_atendimento_fields_to_tables.php` | 05/11/2026 | Alteração | Tenant |
| 6 | `2026_11_05_081450_create_accounts_payable_tables.php` | 05/11/2026 | Criação | Tenant |

---

## 2. Detalhe por migration

### 2.1 `add_idempotency_key_to_appointments_table`

- **Tabela:** `appointments`
- **Coluna criada:** `idempotency_key` (string 64, nullable, unique, after `titulo`)
- **Índices:** índice único implícito na coluna
- **Chaves estrangeiras:** nenhuma
- **Risco de incompatibilidade:** Baixo. No MySQL, múltiplos `NULL` convivem em índice único.
- **Perda de dados:** Não. Coluna anulável.
- **Reversível:** Sim (`dropUnique` + `dropColumn`).
- **Impacto em clínicas existentes:** Nenhum dado existente é alterado. Apenas uma nova coluna anulável é adicionada.

### 2.2 `create_pacotes_sessao_table`

- **Tabela:** `pacotes_sessao` (nova)
- **Colunas:** `id`, `paciente_id` (FK), `profissional_id` (FK nullable), `nome`, `total_sessoes`, `sessoes_usadas`, `valor_sessao`, `valor_total`, `status`, `validade_em`, timestamps
- **Índices:** `['paciente_id', 'status']`
- **FKs:** `paciente_id` → `pacientes.id` (cascade on delete), `profissional_id` → `professionals.id` (null on delete)
- **Constraint CHECK:** `sessoes_usadas >= 0 AND sessoes_usadas <= total_sessoes` (apenas MySQL/MariaDB/PostgreSQL)
- **Risco de incompatibilidade:** Baixo. Tabela nova, não afeta dados existentes.
- **Perda de dados:** Não.
- **Reversível:** Sim (`dropIfExists`).
- **Impacto em clínicas existentes:** Nenhum. Tabela nova.

### 2.3 `create_consumo_sessao_table`

- **Tabela:** `consumo_sessao` (nova)
- **Colunas:** `id`, `pacote_id` (FK), `paciente_id` (FK), `profissional_id` (FK nullable), `evolucao_sessao_id` (FK nullable), `data`, `valor`, `observacoes`, timestamps
- **Índices:** `['pacote_id', 'data']`
- **FKs:** `pacote_id` → `pacotes_sessao.id`, `paciente_id` → `pacientes.id`, `profissional_id` → `professionals.id`, `evolucao_sessao_id` → `evolucoes_sessao.id`
- **Risco de incompatibilidade:** Baixo. Depende da tabela `pacotes_sessao` (migration anterior).
- **Perda de dados:** Não.
- **Reversível:** Sim (`dropIfExists`).
- **Impacto em clínicas existentes:** Nenhum. Tabela nova.

### 2.4 `create_fiscal_tables`

- **Tabelas:** `fiscal_companies`, `nfse`, `nfse_events` (novas)
- **Colunas principais:** CNPJ, razão social, status NFSe, idempotency_key, valor, alíquota, etc.
- **FKs:** `nfse.fiscal_company_id` → `fiscal_companies.id`, `nfse_events.nfse_id` → `nfse.id`
- **Índices únicos:** `fiscal_companies.external_id`, `nfse.idempotency_key`, `nfse_events(['nfse_id', 'event_id'])`
- **Risco de incompatibilidade:** Baixo. Tabelas novas. Não afeta emissão fiscal real (SendNF segue mock).
- **Perda de dados:** Não.
- **Reversível:** Sim (`dropIfExists` em ordem inversa).
- **Impacto em clínicas existentes:** Nenhum. Tabelas novas.

### 2.5 `add_financeiro_e_atendimento_fields_to_tables`

- **Tabelas alteradas:** `payments`, `suppliers`, `accounts_receivable`, `appointments`, `pacotes_sessao`, `consumo_sessao`
- **Colunas criadas:**
  - `payments`: `paciente_id` (nullable, index), `servico` (string 120, nullable, index), `origem` (string 30, nullable)
  - `suppliers`: `tipo_documento` (string 20, default 'outros'), `observacoes` (text, nullable)
  - `accounts_receivable`: `descricao` (nullable), `servico` (string 120, nullable)
  - `appointments`: `area` (string 80, nullable), `servico` (string 120, nullable, index), `valor` (decimal 12,2, nullable)
  - `pacotes_sessao`: `servico` (string 120, nullable, index)
  - `consumo_sessao`: `appointment_id` (unsignedBigInteger, nullable, unique), `reposicao` (boolean, default false)
- **Risco de incompatibilidade:** Baixo. Todas as colunas são anuláveis. Não quebra histórico existente.
- **Perda de dados:** Não. Todas as colunas novas são anuláveis.
- **Reversível:** Sim (remove índices primeiro, depois colunas, em ordem inversa).
- **Impacto em clínicas existentes:** Nenhum dado é alterado ou excluído. Apenas colunas anuláveis são adicionadas.

### 2.6 `create_accounts_payable_tables`

- **Tabelas:** `financeiro_categorias`, `accounts_payable`, `accounts_payable_pagamentos` (novas)
- **Colunas `financeiro_categorias`:** `id`, `nome`, `tipo`, `is_active`, timestamps; unique `['nome', 'tipo']`
- **Colunas `accounts_payable`:** `id`, `supplier_id` (FK nullable), `financeiro_categoria_id` (FK nullable), `nome_conta`, `descricao`, `valor`, `data_vencimento`, `data_pagamento`, `status`, `tipo`, `tipo_documento`, `observacoes`, timestamps
- **Colunas `accounts_payable_pagamentos`:** `id`, `accounts_payable_id` (FK cascade), `payment_method_id` (FK nullable), `user_id` (nullable), `idempotency_key` (string 64, nullable, unique), `data`, `valor`, `observacoes`, timestamps; index `['accounts_payable_id', 'data']`
- **Seed inicial:** 14 categorias financeiras (10 despesa, 4 receita) via `insertOrIgnore`
- **FKs:** `accounts_payable_pagamentos.accounts_payable_id` → `accounts_payable.id` (cascade on delete); `payment_method_id` → `payment_methods.id` (null on delete)
- **Risco de incompatibilidade:** Baixo. Tabelas novas. Não afeta dados existentes.
- **Perda de dados:** Não.
- **Reversível:** Sim (`dropIfExists` em ordem inversa).
- **Impacto em clínicas existentes:** Nenhum. Tabelas novas.

---

## 3. Ordem de execução e dependências

As migrations devem ser executadas na ordem cronológica (Laravel faz isso automaticamente com `php artisan migrate`):

1. `add_idempotency_key_to_appointments_table` (depende de `appointments` já existente)
2. `create_pacotes_sessao_table` (depende de `pacientes`, `professionals`)
3. `create_consumo_sessao_table` (depende de `pacotes_sessao`, `pacientes`, `professionals`, `evolucoes_sessao`)
4. `create_fiscal_tables` (independente)
5. `add_financeiro_e_atendimento_fields_to_tables` (depende de `payments`, `suppliers`, `accounts_receivable`, `appointments`, `pacotes_sessao`, `consumo_sessao`)
6. `create_accounts_payable_tables` (depende de `suppliers`, `payment_methods`)

---

## 4. Compatibilidade por banco

| Banco | Tabela `appointments` existe? | Tabela `pacotes_sessao` existe? | Pode receber as migrations? |
|---|---|---|---|
| Banco central | N/A (migrations são de tenant) | N/A | Não aplicar (migrations de tenant) |
| `clinic_000005` — Daiane | Sim | Sim | Sim |
| `clinic_000001` — Malheiro | Sim | Sim | Sim |
| Outros tenants | Sim (se provisionados) | Sim (se provisionados) | Sim |

---

## 5. Recomendações para execução na VPS

1. **Não executar migrations em produção sem backup válido.**
2. Simular primeiro em banco descartável local (SQLite ou MySQL de teste).
3. Executar `php artisan tenants:migrate --clinic=5` para a Daiane (ou `php artisan tenants:migrate` para todos).
4. Verificar que nenhuma tabela existente foi dropada ou alterada destrutivamente.
5. Confirmar que dados existentes continuam intactos após a migration.

---

## 6. Rollback de migrations

Cada migration possui método `down()` reversível. No entanto:

> **Não presumir que rollback de migration é sempre seguro.** Em produção, o rollback seguro é restaurar o backup do banco e voltar o código para a tag anterior.
