# RELATÓRIO DE DIFERENÇAS — PLANILHAS DA DAIANE x LJ SMARTCLINIC

Este documento lista as diferenças concretas entre o que as 8 planilhas originais da clínica Daiane fazem e o que o LJ SmartClinic implementa hoje, agrupadas por tipo de diferença. Serve de base para o plano de correções.

---

## 1. Diferenças de estrutura de dados (planilha vs. banco relacional)

| Diferença | Planilha | Sistema | Impacto |
|-----------|----------|---------|---------|
| Grade semanal sem data | AGENDA CLINICA .xlsx usa SEGUNDA..SÁBADO com horários em frações (0.25, 0.3333) | `appointments` usa `inicio`/`fim` (datetime) + `dia` + `hora_inicio` | Importar a grade exigiria inventar datas; não há mapeamento 1:1. O sistema é mais preciso, mas muda o hábito da usuária. |
| Tabela cruzada (clientes × meses) | PGTO PILATES.xlsx: clientes em linhas, meses em colunas, valores/presença nas células | `pacotes_sessao` + `consumo_sessao` (modelo relacional: uma linha por pacato, uma linha por consumo) | Não há conversão automática da tabela cruzada para o modelo relacional. |
| Nome textual sem vínculo | PAGAMENTOS.xlsx: NOME é texto livre (GISELE, GISELE ALVES, GISELE LVES) | `payments.paciente_id` (FK anulável) + `PacienteNomeService` (busca aproximada, não funde) | 6.163 pagamentos importados sem FK; vínculo é decisão humana, nunca automática. |
| Contas a pagar como fornecedor | CONTAS .xlsx: cada linha é uma conta com vencimento, valor, status | `suppliers` (quem recebe) + `accounts_payable` (conta com vencimento, baixa) — tabela nova criada mas não populada com dados históricos | O sistema tem a tabela correta, mas os dados históricos de CONTAS .xlsx não foram migrados para ela. |
| Pacotes fora da vertical FT | PGTO PILATES.xlsx: pacotes de pilates/massagem | `pacotes_sessao` existe, rotas liberadas para todas as verticais (middleware `vertical:fisioterapia` removido) | Pacotes de pilates/massagem podem ser criados/consumidos por clínicas odontológicas. |

---

## 2. Diferenças de fórmulas e cálculos

| Diferença | Planilha | Sistema | Observação |
|-----------|----------|---------|------------|
| DAYS360 | CONTAS .xlsx: `DAYS360(B4,A4)` | `FinanceHelper->dias360()` (base 30/360, mesma convenção do Excel) | Equivalente implementado, mas não exposto na tela. |
| Juros sobre vencimento | Fórmulas em algumas abas (OUTUBRO 2023+) | `FinanceHelper->jurosSimples()` / `encargos()` (taxa informada pelo usuário) | Regra comercial (taxa %) não fixada; sistema não inventa juros. |
| Multa | Fórmulas em algumas abas | `FinanceHelper->multa()` | Idem juros. |
| Desconto à vista | Algumas abas (valor 0.0) | `accounts_receivable.desconto` (campo disponível) + fluxo de recebimento aceita `desconto` informado pelo usuário | Fluxo de desconto implementado: usuário informa valor no recebimento; desconto acumulado na conta; status quita quando valor_pago + desconto >= valor. |
| "TÁ CHEGANDO!" (aniversário) | ANIVERSARIANTES.xlsx: `IF(NOW()-DATE(...)>=-2 ...)` | Relatório de aniversariantes implementado (`relatorios.aniversariantes`) com sinalizador `chegando` para aniversários em até 2 dias | Relatório de consulta implementado. Alerta/notificação automática pendente de decisão da cliente (ver DECISOES_PENDENTES_CLIENTE.md). |
| COUNTIF/SUM aninhados | DEPILAÇÃO LASER.xlsx (97 fórmulas em algumas abas) | Relatórios via controller (`RelatorioController`) | Lógica de negócio existe, mas não replicada como fórmulas de planilha. |
| SUM por mês/coluna | PAGAMENTOS.xlsx, PGTO PILATES.xlsx, CONTAS .xlsx | `RelatorioController` (por mês, por serviço, por pacote) | Totais existem, mas com layout diferente da planilha. |

---

## 3. Diferenças de fluxo e comportamento

| Diferença | Planilha | Sistema | Observação |
|-----------|----------|---------|------------|
| Status "atrasado" gravado | CONTAS .xlsx: status PAGO/ATRASADO gravado na célula | `AccountsPayable->statusEfetivo()`: atrasado calculado pela data de vencimento (não envelhece gravado) | O sistema é mais correto (não precisa de job para reescrever status), mas difere da planilha. |
| Falta nunca consome sessão | Não explicitado nas planilhas | `AtendimentoService`: `marcarFalta` recusa se já há consumo; `consumirSessao` exige `atendido` | Regra de proteção implementada; reposição é ato explícito (não confirmada pela cliente). |
| Idempotência de pagamento | Não existe | `consumo_sessao.appointment_id` (unique) + `accounts_payable_pagamentos.idempotency_key` | Impede débito duplicado por duplo clique/retry. |
| Baixa transacional | Não existe | `RecebimentoService` e `ContasPagarService` com `lockForUpdate` | Evita valor_pago acima do saldo em acesso concorrente. |
| Merge automático de cadastros | Não existe (usuário gerencia manualmente) | Nunca acontece: `PacienteNomeService` só sugere candidatos | O sistema não funde cadastros; a cliente decide. |

---

## 4. Diferenças de telas e usabilidade

| Diferença | Planilha | Sistema | Observação |
|-----------|----------|---------|------------|
| Agenda por data específica | Grade semanal (dia da semana) | Agenda por data específica (`agenda.dia`, `agenda.mes`) | Mudança de hábito para a usuária, acostumada com grade SEGUNDA..SÁBADO. |
| Tela de contas a pagar | CONTAS .xlsx (50 abas, uma por mês) | `financeiro.contas-pagar.index` (listagem paginada com filtros) | Tela nova, mais rica, mas organização diferente. |
| Tela de pagamentos importados | Abas mensais com nome textual | `financeiro.pagamentos.index` com filtro `vinculo=sem` + tela de revisão | Fluxo de revisão humana para vincular pagamento a paciente. |
| Histórico do cliente | Revisar abas passadas | `pacientes.historico` (consultas, pacotes, pagamentos em uma página) | O sistema unifica o histórico que estava espalhado. |

---

## 5. Diferenças de dados (reconciliação)

| Item | Planilha | Sistema (clinic_000005) | Diferença |
|------|----------|-------------------------|-----------|
| Pacientes | 767 (importados) | 767 | Igual. |
| Agendamentos/appointments | 1.362 | 1.362 (1.314 atendido / 48 faltou) | Igual. 48 registros corrigidos de `atendido` para `faltou`. |
| Pagamentos | 6.174 lançamentos extraídos | 6.163 registrados | Diferença de 11 lançamentos — investigar se são duplicatas, cabeçalhos ou linhas vazias da planilha. |
| Fornecedores | 94 | 94 | Igual. |
| Pacotes de sessão | Não contado nas planilhas | 369 | Dados importados na fase 2. |

**Diferença de 11 lançamentos (6.174 vs 6.163):** não é alteração de valores; é contagem de linhas de lançamento. Pode decorrer de linhas de cabeçalho, totais ou linhas vazias contadas como lançamento na extração. Não há evidência de duplicata real. Não se deve forçar igualdade alterando valores.

---

## 6. Itens que dependem de decisão da cliente (não implementar por suposição)

- Regras comerciais de **juros, multa e desconto** (taxa %, base de dias, quando aplicar).
- Regra de **reposição de falta** (se falta gera reposição, quantas, prazo).
- Tipo de relacionamento do **profissional externo** (diária vs mensal vs por sessão).
- Relatórios de **aniversariantes** com alerta "TÁ CHEGANDO!".
- Migração dos dados históricos de **contas a pagar** (CONTAS .xlsx) para `accounts_payable`.
- Criação de pacotes de **pilates/massagem** fora da vertical fisioterapia.

---

## 7. Percentual real de equivalência

Com base na matriz de 127 fluxos (recontagem por script; a contagem anterior de 137 estava errada):

- **A (equivalente, implementado e testado): 123 fluxos → 96,85%**
- B (parcial): 0 fluxos
- C (estrutura, comportamento incompleto): 0 fluxos
- D (não implementado): 3 fluxos (DJ-03, DJ-04, DJ-06)
- E (não verificável): 1 fluxo (PAC-10)

**A meta de 100% NÃO está atingida.** Somente fluxos A contam. Os 4 fluxos restantes dependem de decisão comercial da cliente ou de arquivo externo.
