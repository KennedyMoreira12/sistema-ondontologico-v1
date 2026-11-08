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
| Pagamentos | 6.174 lançamentos extraídos | 6.163 registrados | Diferença de 11 **explicada**: 14 duplicatas internas colapsadas pela idempotência + 3 pagamentos pré-existentes fora da planilha. |
| Fornecedores | 94 | 94 | Igual. |
| Pacotes de sessão | Não contado nas planilhas | 369 | Dados importados na fase 2. |

**Diferença de 11 lançamentos (6.174 vs 6.163) — fechada:** não é perda nem duplicação no banco. A reextração de `PAGAMENTOS.xlsx` em 08/11/2026 encontrou **6.174 linhas válidas** e **6.160 chaves distintas** em (`data`, `valor`, `observações`), ou seja, **14 duplicatas internas**. O importador idempotente grava uma linha por chave; a base anterior tinha 572 pagamentos, dos quais 569 casaram com a planilha e **3 não têm linha correspondente**. Conta exata: **6.174 − 14 + 3 = 6.163** (572 + 5.591 novos = 6.163). Nenhum valor foi alterado e nenhuma igualdade foi forçada.

As 14 duplicatas internas, com aba e linha de origem:

| # | Chave (data \| valor \| observações) | Ocorrências |
|---|--------------------------------------|-------------|
| 1 | 2023-03-31 \| 60,00 \| LEILA FISIOTERAPA 2X1 MASSAGENS PIX | MARCO 2023!205, 206 |
| 2 | 2022-10-24 \| 60,00 \| ANIBAL MASSAGEM RELAXANTE PIX | OUTUBRO 2022!84, 85 |
| 3 | 2022-11-04 \| 150,00 \| JANE RADIO COXA/GLUTEO DINHEIRO | NOVEMBRO 2022!18, 19 |
| 4 | 2022-12-12 \| 100,00 \| JANE RADIO AB CARTÃO DE CRÉDITO | DEZEMBRO 2022!44, 45 |
| 5 | 2023-02-13 \| 60,00 \| DALILA MASSAGEM RELAXANTE PIX | FEVEREIRO 2023!74, 75 |
| 6 | 2023-04-01 \| 40,00 \| MYLLA DETOX CARTÃO DE CRÉDITO | ABRIL 2023!10, 11 |
| 7 | 2023-05-09 \| 100,00 \| CINTYA ZENILDE LIMPEZA DE PELE PIX | MAIO 2023!46, 47 |
| 8 | 2023-06-14 \| 60,00 \| LARISSA PROMO 2X1 CARTÃO DE CRÉDITO | JUNHO 2023!84, 85 |
| 9 | 2023-06-16 \| 40,00 \| NEIA PROMO MASSAGEM CARTÃO DE CRÉDITO | JUNHO 2023!97, 98 |
| 10 | 2023-11-25 \| 60,00 \| GUSTAVO DURAES LIBERAÇÃO MIOFASCIAL PIX | NOVEMBRO 2023!111, 114 |
| 11 | 2024-12-02 \| 100,00 \| JOAQUIM LIMPEZA DE PELE CARTÃO DE DÉBITO | DEZEMBRO 2024!5, 7 |
| 12 | 2024-12-20 \| 100,00 \| JERRY PAI LIMPEZA DE PELE CARTÃO DE DÉBITO | DEZEMBRO 2024!120, 124 |
| 13 | 2025-02-17 \| 10,00 \| FRANCIELE N PIX | FEVEREIRO 2025!41, 49 |
| 14 | 2025-05-19 \| 200,00 \| PIX RECEBIDO-EXTRATO/CONTA FISICA PIX | MAIO 2025!87, 88 |

Os 3 pagamentos pré-existentes só podem ser individualizados com o banco original (MySQL local indisponível); a contagem está registrada em `comparacao.txt` (569 match / 3 sobra).

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
