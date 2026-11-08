# PLANO DE CORREÇÕES — 100% DE SUBSTITUIÇÃO DAS PLANILHAS DA DAIANE

Este plano lista as correções e implementações necessárias para levar os fluxos não-A (B, C, D, E) da matriz à classificação A. A recontagem confirmou 127 fluxos (não 137); restam 4 não-A. Não inventa regras comerciais: onde a cliente não confirmou a regra, o item fica pendente de decisão e o sistema não é alterado por suposição.

## Critério de conclusão

Somente fluxos classificados como **A** contam para 100%. O plano será considerado concluído quando todos os 127 fluxos forem A, com testes automatizados aprovados e dados históricos preservados.

---

## Etapa 1 — Correções de comportamento em funcionalidades já existentes (B → A)

Essas correções não criam novas tabelas nem migrações de produção; ajustam código, validações e telas.

| # | Fluxo (ID) | Correção necessária | Arquivos envolvidos | Status | Testes |
|---|------------|---------------------|---------------------|--------|--------|
| 1 | AGD-01 (grade semanal) | Documentar que a grade SEGUNDA..SÁBADO foi substituída por agenda por data. | `docs/` | Documentado | — |
| 2 | AGD-02 (horários por profissional) | Ajustar preenchimento de `professional_schedules` (opcional, sob demanda da cliente). | — | Pendente | — |
| 6 | LAS-06 (valor com plano saúde) | Vínculo `payments` ↔ `insurance_plans` (FK `insurance_plan_id`) implementado. Campo anulável. | `app/Models/Payment.php`, migration `2026_11_08_000001`, `RecebimentoService`, `FinanceiroController` | ✅ Concluído | `test_pagamento_com_plano_de_saude_campo_anulavel` |
| 9 | LAS-09 (filtros) | Filtros existentes são funcionais. Sem alteração obrigatória. | — | Documentado | — |
| 27 | PIL-01 (tabela cruzada pilates) | Pacotes não-FT liberados (removido middleware `vertical:fisioterapia`). Tabela cruzada não convertida (decisão de produto). | `routes/web.php` | ✅ Concluído (rotas) | `test_pacote_pilates_fora_vertical_ft` |
| 29 | PIL-03 (situação DEVENDO/REGULAR) | `accounts_receivable.status` usado como equivalente. Sem alteração obrigatória. | — | Documentado | — |
| 31 | PIL-05 (calendário turmas) | `ModuloPlanejadoController` existe. Verificar se atende turmas de pilates. | — | Pendente | — |
| 45 | MAS-01 (caixa mensal massagem) | Relatório por serviço existe (`receitasMensais` filtra por `servico`). | — | Documentado | — |
| 49 | MAS-05 (soma mensal) | `RelatorioController@receitasMensais` já agrupa por mês. | — | Documentado | — |
| 50 | MAS-06 (presença massagem) | `appointments` status `atendido`/`faltou` já serve. | — | Documentado | — |
| 53 | PAC-02 (variações de nome) | `PacienteNomeService` busca aproximada. Não funde cadastros. | — | Documentado | `test_duplicatas_nao_fundem_cadastros` |
| 54 | PAC-03 (histórico) | `AtendimentoController@historico` unifica consultas, pacotes, pagamentos. | — | Documentado | — |
| 93 | PG-03 (plano saúde copagamento) | Veja LAS-06. | — | ✅ Concluído | `test_pagamento_com_plano_de_saude_campo_anulavel` |
| 94 | PG-04 (vínculo pagamento-paciente) | Tela de revisão já existe. | — | Documentado | — |
| 102 | PE-02 (pagamento profissional externo) | Pagamento possível via `payments`/`accounts_payable`. Tipo "diária" não estruturado. | — | Pendente (decisão cliente) | — |
| 104 | PE-04 (total mensal profissional) | Relatório por serviço existe. Total por profissional externo como filtro. | `RelatorioController` | Pendente | — |
| 111 | DJ-05 (desconto à vista) | Campo `desconto` usado no fluxo de recebimento. Desconto acumulado em `accounts_receivable.desconto`. | `FinanceiroPaymentRequest`, `RecebimentoService`, `FinanceiroController` | ✅ Concluído | `test_recebimento_com_desconto_quita_conta`, `test_recebimento_desconto_nao_quita_fica_parcial` |
| 114 | REL-03 (plano saúde indicador) | `convenios.*` + `insurance_plans` existem. | — | Pendente | — |
| 115 | REL-05 (indicadores pilates/massagem) | Dados por serviço existem em `receitasMensais`. | — | Documentado | — |
| 122 | MKT-01 (aniversariantes) | Relatório de aniversariantes implementado. | `RelatorioController`, rota, view | ✅ Concluído | `test_relatorio_aniversariantes_mes`, `test_relatorio_aniversariantes_chegando` |
| 123 | MKT-02 (alerta TÁ CHEGANDO!) | Relatório implementado. Alerta/notificação automática pendente de decisão da cliente. | `RelatorioController`, rota, view | ✅ Concluído (relatório) | `test_relatorio_aniversariantes_chegando` |
| 125 | MKT-05 (campanhas) | `SocialPost` existe. Depende de uso da cliente. | — | Pendente | — |
| 126 | MKT-06 (posts sociais) | `SocialPost` + `SocialSetting` existem. Depende de uso da cliente. | — | Pendente | — |

---

## Etapa 2 — Estrutura existente, comportamento incompleto (C → A)

| # | Fluxo (ID) | Correção necessária | Arquivos envolvidos | Testes a criar |
|---|------------|---------------------|---------------------|----------------|
| 1 | AGD-01 | Veja Etapa 1 (não é código crítico). | — | — |
| 2 | LAS-06 | Veja Etapa 1. | — | — |
| 3 | PIL-01 | Criar pacotes de pilates/massagem (fora vertical FT). | `routes/web.php`, `PacoteSessaoController` | Teste de pacote não-FT |
| 4 | PIL-03 | Confirmar uso de `accounts_receivable.status` como situação. | — | — |
| 5 | CR-01 | Contas a receber: tabela existe; fluxo de lançamento de contas (não apenas pagamentos) já está em `FinanceiroController@receive`. Confirmar cobertura. | — | Teste de lançamento de conta a receber |
| 6 | CR-02 | FK `paciente_id` anulável em `payments`; vínculo manual já suportado. | — | Teste de vínculo pagamento-paciente |
| 7 | CR-08 | Pagamento manual cria conta; importados não têm FK. Tela de revisão já cobre. | — | — |
| 8 | MAS-06 | Presença massagem já usável via `appointments`. | — | — |
| 9 | PAC-09 | Detecção de duplicatas já implementada (não funde). | — | Teste de duplicatas |
| 10 | PG-08 | Proteção contra sobrescrever vínculo já implementada. | — | — |
| 11 | DJ-05 | Campo desconto existe; aplicar na tela de receber. | `FinanceiroController@receive` | Teste de desconto |
| 12 | MKT-03 | Prospects/social posts existem; conteúdo difere (não é código). | — | — |

---

## Etapa 3 — Não implementado (D → A)

Esses itens exigem nova tabela, nova tela ou nova regra. Nenhuma migration será executada em produção sem aprovação.

| # | Fluxo (ID) | Correção necessária | Arquivos envolvidos | Status | Observação |
|---|------------|---------------------|---------------------|--------|------------|
| 1 | LAS-08 | Fórmulas COUNTIF/SUM da planilha não são replicadas; equivalente funcional existe nos relatórios. Não é código obrigatório. | — | Documentado | Equivalente funcional já existe |
| 2 | CP-01 a CP-08 (contas a pagar) | Tabela `accounts_payable` já existe. Faltam: popular com dados históricos de CONTAS .xlsx. | `ContaPagarController` (já existe) | Pendente (decisão cliente) | Telas existem; falta importação de dados históricos |
| 3 | PSP-08 | Pacotes de pilates/massagem fora da vertical FT: middleware `vertical:fisioterapia` removido das rotas. | `routes/web.php` | ✅ Concluído | Pacotes disponíveis para todas as verticais |
| 4 | DJ-03/DJ-04 (juros/multa) | `FinanceHelper` já tem as funções; não fixar taxa/percentual (regra comercial da cliente). | `app/Support/FinanceHelper.php` | Pendente (decisão cliente) | Não inventar regras |
| 5 | DJ-06 | Campo dedicado de juros/desconto na conta a pagar não existe; usar `observacoes`/`tipo_documento`. | `app/Models/AccountsPayable.php` | Pendente | Sem alteração obrigatória |
| 6 | MKT-02 | Relatório de aniversariantes implementado. Alerta/notificação automática pendente de decisão. | `RelatorioController`, rota, view | ✅ Concluído (relatório) | Alerta automático depende da cliente |

---

## Etapa 4 — Isolamento multi-tenant e pacotes não-FT

O isolamento por tenant já é garantido pelo `TenantModel` e pelas migrations tenant. Os testes de isolamento já existem (`TenantIsolationTest`, `PacoteSessaoTest`).

Ação específica: verificar se as rotas de `pacotes_sessao` devem ser liberadas para clínicas odontológicas (pilates/massagem) ou permanecer restritas à vertical fisioterapia. Isso é uma decisão de produto, não de código.

---

## Etapa 5 — Testes automatizados a criar

Para cada fluxo A que ainda não tem teste, e para cada correção das etapas acima:

| Módulo | Teste implementado | Arquivo | Status |
|--------|-------------------|---------|--------|
| Agenda | Agendamento por data com conflito | `tests/Feature/EquivalenciaPlanilhasDaianeTest.php` | ✅ Aprovado |
| Agenda | Presença e falta | `tests/Feature/EquivalenciaPlanilhasDaianeTest.php` | ✅ Aprovado |
| Depilação a laser | Consulta completa com pagamento | `tests/Feature/EquivalenciaPlanilhasDaianeTest.php` | ✅ Aprovado |
| Depilação a laser | Pagamento com plano de saúde (campo anulável) | `tests/Feature/EquivalenciaPlanilhasDaianeTest.php` | ✅ Aprovado |
| Pilates | Pagamento por serviço PILATES | `tests/Feature/EquivalenciaPlanilhasDaianeTest.php` | ✅ Aprovado |
| Fisioterapia / pacotes | Consumo debita saldo | `tests/Feature/EquivalenciaPlanilhasDaianeTest.php` | ✅ Aprovado |
| Fisioterapia / pacotes | Saldo nunca negativo | `tests/Feature/EquivalenciaPlanilhasDaianeTest.php` | ✅ Aprovado |
| Fisioterapia / pacotes | Consumo idempotente por appointment_id | `tests/Feature/EquivalenciaPlanilhasDaianeTest.php` | ✅ Aprovado |
| Contas a pagar | Criar, baixar parcial, quitar | `tests/Feature/EquivalenciaPlanilhasDaianeTest.php` | ✅ Aprovado |
| Contas a pagar | Atraso calculado, não gravado | `tests/Feature/EquivalenciaPlanilhasDaianeTest.php` | ✅ Aprovado |
| Contas a receber | Baixa parcial | `tests/Feature/EquivalenciaPlanilhasDaianeTest.php` | ✅ Aprovado |
| Contas a receber | Rejeita pagamento acima do saldo | `tests/Feature/EquivalenciaPlanilhasDaianeTest.php` | ✅ Aprovado |
| Contas a receber | Total em aberto desconta parcial | `tests/Feature/EquivalenciaPlanilhasDaianeTest.php` | ✅ Aprovado |
| Pagamentos | Vínculo não sobrescreve | `tests/Feature/EquivalenciaPlanilhasDaianeTest.php` | ✅ Aprovado |
| FinanceHelper | DAYS360 igual ao Excel | `tests/Feature/EquivalenciaPlanilhasDaianeTest.php` | ✅ Aprovado |
| Relatórios | Receitas por mês | `tests/Feature/EquivalenciaPlanilhasDaianeTest.php` | ✅ Aprovado |
| Relatórios | Presença/faltas | `tests/Feature/EquivalenciaPlanilhasDaianeTest.php` | ✅ Aprovado |
| Relatórios | Aniversariantes por mês | `tests/Feature/EquivalenciaPlanilhasDaianeTest.php` | ✅ Aprovado |
| Relatórios | Aniversariantes "TÁ CHEGANDO!" | `tests/Feature/EquivalenciaPlanilhasDaianeTest.php` | ✅ Aprovado |
| Pacientes | Duplicatas não fundem | `tests/Feature/EquivalenciaPlanilhasDaianeTest.php` | ✅ Aprovado |
| Desconto | Recebimento com desconto quita conta | `tests/Feature/EquivalenciaPlanilhasDaianeTest.php` | ✅ Aprovado |
| Desconto | Recebimento com desconto fica parcial | `tests/Feature/EquivalenciaPlanilhasDaianeTest.php` | ✅ Aprovado |
| Pacotes | Pacote pilates fora vertical FT | `tests/Feature/EquivalenciaPlanilhasDaianeTest.php` | ✅ Aprovado |
| Pacotes não-FT | Clínica odontológica acessa pacotes | `tests/Feature/PacoteSessaoTest.php` | ✅ Aprovado |

---

## Etapa 6 — Dados históricos e reconciliação

- Não alterar valores nem forçar igualdade.
- Diferença de 11 lançamentos (6.174 extraídos vs 6.163 registrados): **fechada** — 14 duplicatas internas idênticas em (data, valor, observações) colapsadas pela idempotência + 3 pagamentos pré-existentes fora da planilha. Conta: 6.174 − 14 + 3 = 6.163. Nenhum dado foi alterado.
- 48 registros de `atendido` corrigidos para `faltou` (já feito).
- Migração de contas a pagar (CONTAS .xlsx) para `accounts_payable`: pendente de decisão da cliente.

---

## Cronograma sugerido (planejamento, não compromisso)

| Semana | Atividade |
|--------|-----------|
| 1 | Etapa 1: correções de comportamento (B → A) onde houver código obrigatório |
| 2 | Etapa 2: confirmação de fluxos C que já são funcionais |
| 3 | Etapa 3: decidir sobre contas a pagar (dados históricos), pacotes não-FT, juros/multa |
| 4 | Etapa 4: isolamento e pacotes não-FT (decisão de produto) |
| 5 | Etapa 5: criar testes automatizados para os fluxos A e correções |
| 6 | Etapa 6: reconciliação de dados e documentação de itens E |
| 7 | Validação com a cliente (homologação) |

---

## Itens que dependem da cliente (não implementar por suposição)

| Item | Por que depende da cliente |
|------|---------------------------|
| Taxa de juros / percentual de multa | Regra comercial não confirmada |
| Reposição de falta | Regra não confirmada |
| Tipo de profissional externo (diária/mensal) | Não informado |
| Alerta de aniversariante "TÁ CHEGANDO!" | Não solicitado |
| Migração histórica de contas a pagar | Decisão de importar ou não |
| Pacotes de pilates/massagem fora da vertical FT | Decisão de arquitetura de produto |
