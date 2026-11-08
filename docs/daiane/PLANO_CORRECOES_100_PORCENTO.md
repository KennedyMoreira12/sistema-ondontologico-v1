# PLANO DE CORREÇÕES — 100% DE SUBSTITUIÇÃO DAS PLANILHAS DA DAIANE

Este plano lista as correções e implementações necessárias para levar os 77 fluxos não-A (B, C, D, E) da matriz à classificação A. Não inventa regras comerciais: onde a cliente não confirmou a regra, o item fica pendente de decisão e o sistema não é alterado por suposição.

## Critério de conclusão

Somente fluxos classificados como **A** contam para 100%. O plano será considerado concluído quando todos os 137 fluxos forem A, com testes automatizados aprovados e dados históricos preservados.

---

## Etapa 1 — Correções de comportamento em funcionalidades já existentes (B → A)

Essas correções não criam novas tabelas nem migrações de produção; ajustam código, validações e telas.

| # | Fluxo (ID) | Correção necessária | Arquivos envolvidos | Risco | Testes a criar |
|---|------------|---------------------|---------------------|-------|----------------|
| 1 | AGD-01 (grade semanal) | A tela de agenda já usa data específica; manter como padrão. Documentar que a grade SEGUNDA..SÁBADO foi substituída por agenda por data (mais precisa). Nenhuma alteração de código obrigatória. | `app/Http/Controllers/AgendaController.php` | Baixo | Teste de agendamento por data específica |
| 2 | AGD-02 (horários por profissional) | Ajustar preenchimento de `professional_schedules` para refletir a grade da planilha (opcional, sob demanda da cliente). | `app/Models/ProfessionalSchedule.php` | Baixo | — |
| 6 | LAS-06 (valor com plano saúde) | Estruturar vínculo `payments` ↔ `insurance_plans` (FK `insurance_plan_id`) para registro do copagamento/plano. | `app/Models/Payment.php`, migration futura | Médio | Teste de pagamento com plano de saúde |
| 9 | LAS-09 (filtros) | Os filtros existem; não são idênticos ao autofiltro da planilha, mas são funcionais. Sem alteração obrigatória. | — | Baixo | — |
| 27 | PIL-01 (tabela cruzada pilates) | Não converter automaticamente. Oferecer tela de criação de pacote `pacotes_sessao` para pilates (fora da vertical FT, veja Etapa 4). | `app/Http/Controllers/PacoteSessaoController.php` | Médio | Teste de pacote pilates |
| 29 | PIL-03 (situação DEVENDO/REGULAR) | Usar `accounts_receivable.status` (aberta/parcial/pago) como equivalente. Sem alteração obrigatória. | — | Baixo | — |
| 31 | PIL-05 (calendário turmas) | Existe `ModuloPlanejadoController`; verificar se atende turmas de pilates. | `app/Http/Controllers/ModuloPlanejadoController.php` | Baixo | — |
| 45 | MAS-01 (caixa mensal massagem) | Relatório por serviço já existe (`receitasMensais` filtra por `servico`). Sem alteração obrigatória. | — | Baixo | — |
| 49 | MAS-05 (soma mensal) | `RelatorioController@receitasMensais` já agrupa por mês. Sem alteração obrigatória. | — | Baixo | — |
| 50 | MAS-06 (presença massagem) | `appointments` status `atendido`/`faltou` já serve. Sem alteração obrigatória. | — | Baixo | — |
| 53 | PAC-02 (variações de nome) | `PacienteNomeService` já faz busca aproximada. Sem alteração obrigatória (não funde cadastros). | — | Baixo | — |
| 54 | PAC-03 (histórico) | `AtendimentoController@historico` já unifica consultas, pacotes e pagamentos. Sem alteração obrigatória. | — | Baixo | — |
| 93 | PG-03 (plano saúde copagamento) | Veja LAS-06 (FK `insurance_plan_id` em `payments`). | `app/Models/Payment.php` | Médio | Teste de pagamento com plano |
| 94 | PG-04 (vínculo pagamento-paciente) | Tela de revisão já existe (`PagamentoController@revisar`). Sem alteração obrigatória. | — | Baixo | — |
| 102 | PE-02 (pagamento profissional externo) | Pagamento possível via `payments` ou `accounts_payable`. Tipo "diária" não estruturado — campo livre `tipo`/`observacoes` já serve. | — | Baixo | — |
| 104 | PE-04 (total mensal profissional) | Relatório por serviço existe; total por profissional externo não é um bloco dedicado. Pode ser adicionado como filtro. | `app/Http/Controllers/RelatorioController.php` | Baixo | Teste de relatório por profissional |
| 111 | DJ-05 (desconto à vista) | Campo `accounts_receivable.desconto` já existe. Fluxo de aplicação não mapeado. | `app/Http/Controllers/FinanceiroController.php` | Baixo | Teste de desconto na conta a receber |
| 114 | REL-03 (plano saúde indicador) | `convenios.*` + `insurance_plans` existem. Relatório de NF vs pago pode ser reforçado. | `app/Http/Controllers/RelatorioController.php` | Baixo | Teste de relatório plano saúde |
| 115 | REL-05 (indicadores pilates/massagem) | Dados por serviço existem em `receitasMensais`. Blocos dedicados não obrigatórios. | — | Baixo | — |
| 122 | MKT-01 (aniversariantes) | Paciente tem dados de cadastro; relatório de aniversariantes com alerta não existe. Veja MKT-02. | — | Baixo | — |
| 123 | MKT-02 (alerta TÁ CHEGANDO!) | Não implementado. Depende de decisão da cliente sobre se quer esse alerta. | — | Baixo | — |
| 125 | MKT-05 (campanhas) | `SocialPost` existe. Depende de uso da cliente. | — | Baixo | — |
| 126 | MKT-06 (posts sociais) | `SocialPost` + `SocialSetting` existem. Depende de uso da cliente. | — | Baixo | — |

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

| # | Fluxo (ID) | Correção necessária | Arquivos envolvidos | Observação |
|---|------------|---------------------|---------------------|------------|
| 1 | LAS-08 | Fórmulas COUNTIF/SUM da planilha não são replicadas; equivalente funcional existe nos relatórios. Não é código obrigatório. | — | Equivalente funcional já existe |
| 2 | CP-01 a CP-08 (contas a pagar) | Tabela `accounts_payable` já existe (migration `2026_11_05_081450`). Faltam: popular com dados históricos de CONTAS .xlsx e telas já existem (`ContaPagarController`). | `app/Http/Controllers/ContaPagarController.php` (já existe) | Telas já existem; falta migração de dados históricos (decisão da cliente) |
| 3 | PSP-08 | Pacotes de pilates/massagem fora da vertical FT: remover/ajustar middleware `vertical:fisioterapia` nas rotas de pacotes ou criar rotas dedicadas. | `routes/web.php`, `PacoteSessaoController` | Decisão de arquitetura |
| 4 | DJ-03/DJ-04 (juros/multa) | `FinanceHelper` já tem as funções; não fixar taxa/percentual (regra comercial da cliente). | `app/Support/FinanceHelper.php` | Não inventar regras |
| 5 | DJ-06 | Campo dedicado de juros/desconto na conta a pagar não existe; usar `observacoes`/`tipo_documento`. | `app/Models/AccountsPayable.php` | Sem alteração obrigatória |
| 6 | MKT-02 | Alerta de aniversariante "TÁ CHEGANDO!" não existe. Criar somente se a cliente pedir. | — | Pendente de decisão |

---

## Etapa 4 — Isolamento multi-tenant e pacotes não-FT

O isolamento por tenant já é garantido pelo `TenantModel` e pelas migrations tenant. Os testes de isolamento já existem (`TenantIsolationTest`, `PacoteSessaoTest`).

Ação específica: verificar se as rotas de `pacotes_sessao` devem ser liberadas para clínicas odontológicas (pilates/massagem) ou permanecer restritas à vertical fisioterapia. Isso é uma decisão de produto, não de código.

---

## Etapa 5 — Testes automatizados a criar

Para cada fluxo A que ainda não tem teste, e para cada correção das etapas acima:

| Módulo | Teste sugerido | Arquivo |
|--------|---------------|---------|
| Agenda | Agendamento por data com conflito de horário | `tests/Feature/AgendaTest.php` (já existe, estender) |
| Agenda | Reagendamento e cancelamento | `tests/Feature/AgendaTest.php` |
| Financeiro | Pagamento com plano de saúde (FK) | `tests/Feature/PagamentoTest.php` (estender) |
| Financeiro | Conta a pagar: criar, baixar parcial, quitar | `tests/Feature/ContaPagarTest.php` (já existe) |
| Pacotes | Pacote de pilates/massagem (não-FT) | `tests/Feature/PacoteSessaoTest.php` (estender) |
| Pacotes | Consumo idempotente por appointment_id | `tests/Feature/AtendimentoTest.php` (estender) |
| Relatórios | Exportação CSV por tipo | `tests/Feature/RelatorioTest.php` (já existe) |
| Relatórios | Fluxo de caixa | `tests/Feature/FinanceiroTest.php` (já existe) |
| Pacientes | Detecção de duplicatas | `tests/Feature/PagamentoTest.php` (já existe: `test_tela_de_duplicatas_mostra_grupo_sem_fundir_cadastros`) |

---

## Etapa 6 — Dados históricos e reconciliação

- Não alterar valores nem forçar igualdade.
- Diferença de 11 lançamentos (6.174 extraídos vs 6.163 registrados): investigar se são cabeçalhos/totais/linhas vazias. Não é duplicata confirmada.
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
