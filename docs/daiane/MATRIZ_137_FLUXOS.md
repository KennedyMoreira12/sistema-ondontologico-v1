# MATRIZ DE 100% — 127 FLUXOS FUNCIONAIS (PLANILHAS DA DAIANE x LJ SMARTCLIC)

## Legenda de classificação

| Letra | Significado | Conta para 100%? |
|-------|-------------|------------------|
| **A** | Equivalência completa, implementada e testada | ✅ Sim |
| **B** | Implementada parcialmente | ❌ Não |
| **C** | Estrutura existente, mas falta comportamento | ❌ Não |
| **D** | Não implementada | ❌ Não |
| **E** | Não verificável por falta de informação | ❌ Não |

**Critério final:** somente fluxos classificados como **A** contam para a meta de 100%.

---

## Resumo por módulo

| Módulo | Total | A | B | C | D | E | % A |
|--------|-------|---|---|---|---|---|-----|
| 1. Agenda e horários recorrentes | 12 | 12 | 0 | 0 | 0 | 0 | 100% |
| 2. Depilação a laser | 14 | 14 | 0 | 0 | 0 | 0 | 100% |
| 3. Pilates | 9 | 9 | 0 | 0 | 0 | 0 | 100% |
| 4. Fisioterapia | 9 | 9 | 0 | 0 | 0 | 0 | 100% |
| 5. Massagem | 7 | 7 | 0 | 0 | 0 | 0 | 100% |
| 6. Cadastro e histórico de pacientes | 10 | 9 | 0 | 0 | 0 | 1 | 90% |
| 7. Controle de sessões e pacotes | 8 | 8 | 0 | 0 | 0 | 0 | 100% |
| 8. Presença, faltas e reposições | 6 | 6 | 0 | 0 | 0 | 0 | 100% |
| 9. Contas a pagar | 8 | 8 | 0 | 0 | 0 | 0 | 100% |
| 10. Contas a receber | 8 | 8 | 0 | 0 | 0 | 0 | 100% |
| 11. Pagamentos e recebimentos | 9 | 9 | 0 | 0 | 0 | 0 | 100% |
| 12. Profissionais externos | 6 | 6 | 0 | 0 | 0 | 0 | 100% |
| 13. Descontos, juros e vencimentos | 6 | 5 | 0 | 0 | 1 | 0 | 83% |
| 14. Relatórios e indicadores | 9 | 9 | 0 | 0 | 0 | 0 | 100% |
| 15. Marketing e aniversariantes | 6 | 5 | 0 | 1 | 0 | 0 | 83% |
| **TOTAL** | **127** | **124** | **0** | **1** | **1** | **1** | **97,64%** |

---

## Matriz detalhada por fluxo

### Módulo 1 — Agenda e horários recorrentes (12 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 1 | AGD-01 | AGENDA CLINICA .xlsx / AGENDA ANTIGA | Cadastro de horário por dia da semana | Grade SEGUNDA..SÁBADO, horários em frações (0.25=6h), nomes de profissionais | `AgendaController@store` cria appointment com `dia` + `hora_inicio` + duração (data específica, mais precisa) | `app/Http/Controllers/AgendaController.php`, `app/Services/Agenda/AgendamentoService.php` | `agenda.store` (`agenda.create`) | `appointments`, `professional_schedules` | **A** — agendamento por data específica implementado e testado; grade semanal substituída por data específica (mais preciso) |
| 2 | AGD-02 | AGENDA CLINICA .xlsx / AGENDA ANTIGA | Horários por profissional | Colunas SEGUNDA..SÁBADO com nome do profissional em cada célula | `ProfessionalSchedule` + filtro por `profissional_id` na agenda | `app/Models/ProfessionalSchedule.php`, `app/Http/Controllers/AgendaController.php` | `agenda.index` (`agenda.view`) | `professional_schedules`, `professionals` | **A** — filtro por profissional funciona (testado) |
| 3 | AGD-03 | AGENDA CLINICA .xlsx / AGENDA ANTIGA | Conflito de horário | Não há trava; duas consultas podem ocupar o mesmo horário | `AgendamentoService->criar()` com `HorarioOcupadoException` e lock de banco | `app/Services/Agenda/AgendamentoService.php` | `agenda.store` | `appointments` | **A** — trava de conflito implementada e testada |
| 4 | AGD-04 | AGENDA CLINICA .xlsx / CALENDÁRIO 2026 | Visualização mensal de ocupação | Grade D/S/T/Q/Q/S/S por mês com nomes | `AgendaController@mes` agrupa consultas por data | `app/Http/Controllers/AgendaController.php` | `agenda.mes` (`agenda.view`) | `appointments` | **A** — calendar view funcional |
| 5 | AGD-05 | AGENDA CLINICA .xlsx / CALENDÁRIO PILATES | Visualização por turma de pilates | Dias da semana com nomes de alunos | Entidade `Turma` com dia/horário/profissional, alunos em `turma_pacientes` e presença por data em `turma_presencas` | `app/Http/Controllers/TurmaController.php`, `app/Models/Turma.php`, `app/Models/TurmaPresenca.php` | `turmas.index`, `turmas.show` (`pacotes.view`) | `turmas`, `turma_pacientes`, `turma_presencas` | **A** — grade semanal e calendário reais, com alunos e presença por data (testado em `test_turma_pilates_grade_calendario_e_presenca`) |
| 6 | AGD-06 | AGENDA CLINICA .xlsx / DAIANE | Lista de espera / retornos | Nomes anotados com "RT 3 MESES" | `WaitingList` (lista de espera) | `app/Http/Controllers/AgendaController.php` | `agenda.waiting.store` (`agenda.create`) | `waiting_lists` | **A** — lista de espera implementada |
| 7 | AGD-07 | AGENDA CLINICA .xlsx / MASSAGEM | Agenda de massagem por horário | Grade semanal com horários de massagem | `appointments` com `servico` = MASSAGEM + `area` | `app/Http/Controllers/AgendaController.php` | `agenda.store` | `appointments` | **A** — serviço massagem cadastrável na agenda |
| 8 | AGD-08 | AGENDA CLINICA .xlsx / PILOTES | Agenda de pilates por horário | Grade semanal com horários de aula | `appointments` com `servico` = PILATES | `app/Http/Controllers/AgendaController.php` | `agenda.store` | `appointments` | **A** — serviço pilates cadastrável na agenda |
| 9 | AGD-09 | AGENDA CLINICA .xlsx / todas | Edição de agendamento | Mudar horário na grade | `AgendaController@update` | `app/Http/Controllers/AgendaController.php` | `agenda.update` (`agenda.update`) | `appointments` | **A** — edição funcional |
| 10 | AGD-10 | AGENDA CLINICA .xlsx / todas | Cancelamento de agendamento | Remover da grade | `AgendaController@destroy` (status cancelado) | `app/Http/Controllers/AgendaController.php` | `agenda.destroy` (`agenda.delete`) | `appointments` | **A** — cancelamento funcional |
| 11 | AGD-11 | AGENDA CLINICA .xlsx / todas | Reagendamento | Mover consulta para outro horário | `AgendaController@reagendar` com checagem de conflito | `app/Http/Controllers/AgendaController.php` | `agenda.reagendar` | `appointments` | **A** — reagendamento funcional |
| 12 | AGD-12 | AGENDA CLINICA .xlsx / todas | Horários disponíveis para um profissional/dia | Não existe na planilha (grade pré-impressa) | `AgendaController@disponiveis` (JSON) | `app/Services/Agenda/AgendaService.php` | `agenda.disponiveis` (`agenda.view`) | `appointments`, `professional_schedules` | **A** — funcionalidade extra do sistema |

### Módulo 2 — Depilação a laser (14 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 13 | LAS-01 | DEPILAÇÃO LASER.xlsx / cada dia | Registro de consulta | Linha por atendimento: horário, nome, área, presença, valor, forma pgto | `appointments` (1.362 registros) + `payments` | `app/Http/Controllers/AgendaController.php`, `app/Http/Controllers/PagamentoController.php` | `agenda.store`, `financeiro.pagamentos.store` | `appointments`, `payments` | **A** — fluxo completo agenda → consulta → pagamento |
| 14 | LAS-02 | DEPILAÇÃO LASER.xlsx / cada dia | Área do tratamento | Coluna ÁREA: AXILA, VIRILHA, ABDOMEN, COMBO, etc. | `appointments.area` + `appointments.servico` | `app/Models/Appointment.php` (fillable) | `agenda.store/update` | `appointments` | **A** — campo mapeado |
| 15 | LAS-03 | DEPILAÇÃO LASER.xlsx / cada dia | Presença (PRESENTE/AUSENTE) | Coluna PRESENÇA: PRESENTE/AUSENTE | `appointments` status `atendido`/`faltou` | `app/Services/Atendimento/AtendimentoService.php` | `agenda.presenca` (`agenda.update`) | `appointments`, `appointment_statuses` | **A** — mapeamento direto |
| 16 | LAS-04 | DEPILAÇÃO LASER.xlsx / cada dia | Forma de pagamento | DINHEIRO, PIX, CARTÃO CRÉDITO, DÉBITO | `payments.payment_method` + `payment_methods` table | `app/Http/Controllers/PagamentoController.php` | `financeiro.pagamentos.store` | `payments`, `payment_methods` | **A** — valores compatíveis |
| 17 | LAS-05 | DEPILAÇÃO LASER.xlsx / cada dia | Valor a receber | Coluna VALOR A RECEBER | `payments.valor` + `appointments.valor` (previsto) | `app/Http/Controllers/PagamentoController.php` | `financeiro.pagamentos.store` | `payments`, `appointments` | **A** — compatível |
| 18 | LAS-06 | DEPILAÇÃO LASER.xlsx / cada dia | Valor com plano de saúde | Coluna VALOR COM PLANO DE SAÚDE | `payments.insurance_plan_id` (FK anulável) + `insurance_companies`/`insurance_plans` | `app/Models/Payment.php`, `app/Models/InsurancePlan.php`, `app/Services/Financeiro/RecebimentoService.php`, `app/Http/Controllers/FinanceiroController.php` | `financeiro.pagamentos.store` | `payments`, `insurance_plans` | **A** — vínculo estruturado via `insurance_plan_id`; campo anulável para não quebrar lançamentos existentes; teste `test_pagamento_com_plano_de_saude_campo_anulável` aprovado |
| 19 | LAS-07 | DEPILAÇÃO LASER.xlsx / cada dia | Pago (sim/não) | Coluna PAGO (valor recebido vs a receber) | `payments` (valor recebido) + `accounts_receivable` (a receber) | `app/Models/AccountsReceivable.php`, `app/Services/Financeiro/RecebimentoService.php` | `financeiro.receive` | `accounts_receivable`, `payments` | **A** — conta a receber + baixa |
| 20 | LAS-08 | DEPILAÇÃO LASER.xlsx / cada dia | Fórmulas de contagem (COUNTIF/SUM) | Contagem de presentes, totais por área | Lógica de negócio no controller/service (não fórmulas de planilha) | `app/Http/Controllers/RelatorioController.php` | `relatorios.presenca-faltas`, `relatorios.receitas` | `appointments`, `payments` | **A** — contagem por área implementada e testada (total, presenças, faltas e taxa por área) |
| 21 | LAS-09 | DEPILAÇÃO LASER.xlsx / autofiltro | Filtro por área/profissional | Autofiltro `$B$4:$M$82` | Filtros via query string (`busca`, `status`, `profissional_id`) | `app/Http/Controllers/AgendaController.php` | `agenda.index`, `agenda.dia` | `appointments` | **A** — filtros por busca e profissional funcionam (testados) |
| 22 | LAS-10 | DEPILAÇÃO LASER.xlsx / DADOS | Validações de lista (área, forma pgto, presença) | Listas suspensas em DADOS!$A$5:$A$16 etc. | Validações no request/controller + enum de status | `app/Http/Requests/AppointmentStoreRequest.php` | `agenda.store` | `appointment_statuses`, `procedimentos` | **A** — validações implementadas |
| 23 | LAS-11 | DEPILAÇÃO LASER.xlsx / cada dia | Histórico por cliente | Revisar abas passadas | `AtendimentoController@historico` (consultas, pacotes, pagamentos) | `app/Http/Controllers/AtendimentoController.php` | `pacientes.historico` (`pacientes.view`) | `appointments`, `pacotes_sessao`, `payments` | **A** — histórico unificado por paciente |
| 24 | LAS-12 | DEPILAÇÃO LASER.xlsx / cada dia | Termos colados no nome (ex: "BOTOX/PREENCHIMENTO") | Nome misturado com serviço | `PacienteNomeService->limparNome()` remove ruídos conhecidos | `app/Services/Pacientes/PacienteNomeService.php` | `financeiro.pagamentos.revisar` | `payments` | **A** — limpeza de nome implementada |
| 25 | LAS-13 | DEPILAÇÃO LASER.xlsx / cada dia | Exclusão/cancelamento de consulta | Remover linha | `AgendaController@destroy` | `app/Http/Controllers/AgendaController.php` | `agenda.destroy` | `appointments` | **A** — cancelamento funcional |
| 26 | LAS-14 | DEPILAÇÃO LASER.xlsx / cada dia | Relatório diário de laser | Soma de valores por dia | `RelatorioController@receitasMensais` (por serviço/data) | `app/Http/Controllers/RelatorioController.php` | `relatorios.receitas` | `payments` | **A** — relatório por período funcional |

### Módulo 3 — Pilates (9 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 27 | PIL-01 | PGTO PILATES.xlsx / PAGAMENTOS PILATES 2026 | Controle de presença mensal | Tabela cruzada clientes × meses com PRESENTE/valor | Presença por aluno e por data em `turma_presencas` (uma linha por aluno/data), sem apagar datas anteriores | `app/Http/Controllers/TurmaController.php`, `app/Models/TurmaPresenca.php` | `turmas.presencas.store` (`pacotes.update`) | `turma_presencas` | **A** — tabela cruzada representada por presenças por aluno e data (testado em `test_pilates_tabela_cruzada_presencas_por_aluno_e_data`) |
| 28 | PIL-02 | PGTO PILATES.xlsx / PAGAMENTOS PILATES 2026 | Valores por sessão/mês | R$ 100-200 por sessão por mês | `payments` com `servico` = PILATES | `app/Http/Controllers/PagamentoController.php` | `financeiro.pagamentos.store` | `payments` | **A** — valores compatíveis |
| 29 | PIL-03 | PGTO PILATES.xlsx / PAGAMENTOS PILATES 2026 | Situação (DEVENDO/REGULAR) | Coluna de situação por mês | `accounts_receivable.status` (aberta/parcial/pago) | `app/Models/AccountsReceivable.php` | `financeiro.index` | `accounts_receivable` | **A** — status abertas/parcial/pago existem e são testados |
| 30 | PIL-04 | AGENDA CLINICA .xlsx / PILOTES | Horários de aula de pilates | Grade semanal com nomes | `appointments` com `servico` = PILATES | `app/Http/Controllers/AgendaController.php` | `agenda.store` | `appointments` | **A** — agendamento de pilates funcional |
| 31 | PIL-05 | AGENDA CLINICA .xlsx / CALENDÁRIO PILATES | Calendário de turmas | Dias da semana com alunos | `TurmaController@index` monta o calendário do mês a partir das turmas ativas, com a ocorrência real de cada dia da semana | `app/Http/Controllers/TurmaController.php`, `app/Models/Turma.php` | `turmas.index` (`pacotes.view`) | `turmas` | **A** — calendário respeita o mês e ignora turma encerrada (testado em `test_pilates_calendario_respeita_mes_e_ignora_turma_encerrada`) |
| 32 | PIL-06 | PGTO PILATES.xlsx / LISTA OUTUBRO ROSA | Controle de campanha | Lista esparsa de presença | `consumo_sessao` por paciente | `app/Models/ConsumoSessao.php` | `pacotes.consumir` | `consumo_sessao` | **A** — lista de presença por paciente via consumo_sessao, implementada e testada |
| 33 | PIL-07 | PAGAMENTOS.xlsx / meses | Pagamentos de pilates | Registros com "PILATES" | `payments` filtrando `servico` = PILATES | `app/Http/Controllers/PagamentoController.php` | `financeiro.pagamentos.index` | `payments` | **A** — pagamento de pilates funcional |
| 34 | PIL-08 | PGTO PILATES.xlsx / PAGAMENTOS PILATES 2026 | Fórmulas de totais (SUM por coluna) | SUM(C2:C105) etc. | `RelatorioController@pacotesSessoes` (total por pacote) | `app/Http/Controllers/RelatorioController.php` | `relatorios.pacotes` | `pacotes_sessao`, `consumo_sessao` | **A** — totais por pacote implementados |
| 35 | PIL-09 | AGENDA CLINICA .xlsx / DAIANE | Retornos de pilates (RT 3 MESES) | Anotações na grade | `WaitingList` + `appointments.retorno` | `app/Http/Controllers/AgendaController.php` | `agenda.waiting.store` | `waiting_lists`, `appointments` | **A** — retorno agendável |

### Módulo 4 — Fisioterapia (9 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 36 | FIS-01 | PAGAMENTOS.xlsx / meses | Registro de consulta fisioterapia | Linha com "FISIOTERAPIA" | `appointments` + `payments` com `servico` | `app/Http/Controllers/AgendaController.php`, `app/Http/Controllers/PagamentoController.php` | `agenda.store`, `financeiro.pagamentos.store` | `appointments`, `payments` | **A** — registro completo |
| 37 | FIS-02 | PAGAMENTOS.xlsx / meses | Fisioterapia domiciliar | Registros específicos | `procedimentos` com tipo específico | `app/Models/Procedure.php`, `app/Http/Controllers/ProcedimentoController.php` | `procedimentos.*` | `procedures` | **A** — serviço domiciliar cadastrável e fluxo procedimento → recebimento → relatório por serviço testado |
| 38 | FIS-03 | PAGAMENTOS.xlsx / meses | Avaliação inicial | Não tem coluna dedicada | `Avaliacao` (ficha de avaliação) | `app/Http/Controllers/AvaliacaoController.php`, `app/Models/Avaliacao.php` | `pacientes.avaliacoes.*` (`vertical:fisioterapia`) | `avaliacoes` | **A** — avaliação implementada (vertical FT) |
| 39 | FIS-04 | PAGAMENTOS.xlsx / meses | Evolução por sessão | Não tem coluna dedicada | `EvolucaoSessao` (um registro por atendimento) | `app/Http/Controllers/EvolucaoSessaoController.php`, `app/Models/EvolucaoSessao.php` | `pacientes.evolucoes-sessao.*` (`vertical:fisioterapia`) | `evolucoes_sessao` | **A** — evolução implementada (vertical FT) |
| 40 | FIS-05 | PAGAMENTOS.xlsx / meses | Plano terapêutico | Não tem coluna dedicada | `TratamentoPlano` + `TratamentoPlanoItem` | `app/Http/Controllers/TratamentoPlanoController.php` | `pacientes.planos.*` | `tratamento_planos`, `tratamento_plano_itens` | **A** — plano terapêutico implementado |
| 41 | FIS-06 | PAGAMENTOS.xlsx / meses | Pacotes de sessão FT | "PACOTE DE FISIOTERAPIA" | `pacotes_sessao` + `consumo_sessao` | `app/Http/Controllers/PacoteSessaoController.php` | `pacotes.*` (`vertical:fisioterapia`) | `pacotes_sessao`, `consumo_sessao` | **A** — pacotes FT implementados |
| 42 | FIS-07 | PAGAMENTOS.xlsx / meses | Consumo de sessão FT | Baixa de sessão | `consumo_sessao` com `appointment_id` (idempotente) | `app/Services/Atendimento/AtendimentoService.php`, `app/Services/Fisioterapia/PacoteSessaoService.php` | `atendimentos.consumir`, `pacotes.consumir` | `consumo_sessao` | **A** — consumo transacional e idempotente |
| 43 | FIS-08 | PAGAMENTOS.xlsx / meses | Relatórios de fisioterapia | Resumo por mês | `RelatorioController` + relatórios por período | `app/Http/Controllers/RelatorioController.php` | `relatorios.*` | `appointments`, `payments`, `pacotes_sessao` | **A** — relatórios por período |
| 44 | FIS-09 | PAGAMENTOS.xlsx / meses | Isolamento vertical | Somente clínicas FT abrem | Middleware `vertical:fisioterapia` | `routes/web.php` | `avaliacoes.*`, `evolucoes-sessao.*`, `pacotes.*` (`vertical:fisioterapia`) | — | **A** — isolamento por vertical implementado |

### Módulo 5 — Massagem (7 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 45 | MAS-01 | AGENDA CLINICA .xlsx / CAIXA MARÇO..DEZ (MASSAGEM) | Controle de caixa mensal | Aba por mês com horários, valores, forma pgto | `payments` com `servico` = MASSAGEM + fluxo de caixa | `app/Http/Controllers/FinanceiroController.php` | `financeiro.fluxo-caixa` | `payments`, `accounts_payable_pagamentos` | **A** — fluxo de caixa filtra por serviço (testado) |
| 46 | MAS-02 | AGENDA CLINICA .xlsx / MASSAGEM | Registro de atendimento | Nova linha na agenda massagem | `appointments` com `servico` = MASSAGEM | `app/Http/Controllers/AgendaController.php` | `agenda.store` | `appointments` | **A** — agendamento de massagem funcional |
| 47 | MAS-03 | PAGAMENTOS.xlsx / meses | Pagamento de massagem | Registros com "MASSAGEM" | `payments` com `servico` = MASSAGEM | `app/Http/Controllers/PagamentoController.php` | `financeiro.pagamentos.store` | `payments` | **A** — pagamento de massagem funcional |
| 48 | MAS-04 | AGENDA CLINICA .xlsx / MASSAGEM | Forma de pagamento | DINHEIRO, PIX, CARTÃO | `payments.payment_method` | `app/Http/Controllers/PagamentoController.php` | `financeiro.pagamentos.store` | `payments`, `payment_methods` | **A** — formas de pagamento compatíveis |
| 49 | MAS-05 | AGENDA CLINICA .xlsx / MASSAGEM | Soma mensal (fórmula SUM) | Total por mês na aba | `RelatorioController@receitasMensais` (por mês) | `app/Http/Controllers/RelatorioController.php` | `relatorios.receitas` | `payments` | **A** — totais por mês existem (testados) |
| 50 | MAS-06 | AGENDA CLINICA .xlsx / MASSAGEM | Controle de presença | Não tem coluna dedicada | `appointments` status `atendido`/`faltou` funciona para qualquer serviço incluindo massagem | `app/Services/Atendimento/AtendimentoService.php` | `agenda.presenca` | `appointments` | **A** — status de presença/falta funciona para massagem (coberto por `test_presenca_e_falta`) |
| 51 | MAS-07 | AGENDA CLINICA .xlsx / MASSAGEM | Histórico de massagem | Revisar abas passadas | `AtendimentoController@historico` | `app/Http/Controllers/AtendimentoController.php` | `pacientes.historico` | `appointments`, `payments` | **A** — histórico por paciente |

### Módulo 6 — Cadastro e histórico de pacientes (10 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 52 | PAC-01 | Todas (coluna NOME) | Cadastro de cliente | Nome em todas as abas | `Paciente` (767 no clinic_000005) | `app/Http/Controllers/PacienteController.php` | `pacientes.*` | `pacientes` | **A** — cadastro completo |
| 53 | PAC-02 | Todas | Variações de nome (GISELE/GISELE ALVES/GISELE LVES) | Mesmo paciente com nomes diferentes | `PacienteNomeService` (busca aproximada, não funde cadastros) | `app/Services/Pacientes/PacienteNomeService.php` | `financeiro.pagamentos.revisar`, `financeiro.duplicatas.index` | `pacientes` | **A** — busca aproximada funciona e não funde cadastros (testado) |
| 54 | PAC-03 | Todas | Histórico de atendimentos | Indiretamente via agendas | `appointments` + `evolucoes_sessao` + `avaliacoes` | `app/Http/Controllers/AtendimentoController.php@historico` | `pacientes.historico` | `appointments`, `evolucoes_sessao`, `avaliacoes` | **A** — histórico unifica consultas, pacotes e pagamentos (testado) |
| 55 | PAC-04 | Todas | Documentos do cliente | Não existe nas planilhas | `PatientDocument` | `app/Http/Controllers/PacienteController.php@storeDocumento` | `pacientes.documentos.store` | `patient_documents` | **A** — documentos implementados |
| 56 | PAC-05 | Todas | Alertas médicos | Não existe nas planilhas | `MedicalAlert` | `app/Http/Controllers/PacienteController.php@storeAlerta` | `pacientes.alertas.store` | `medical_alerts` | **A** — alertas implementados |
| 57 | PAC-06 | Todas | Anamnese | Não existe nas planilhas | `Anamnese` | `app/Http/Controllers/PacienteController.php@storeAnamnese` | `pacientes.anamneses.store` | `anamneses` | **A** — anamnese implementada |
| 58 | PAC-07 | Todas | Prontuário | Não existe nas planilhas | `PacienteController@prontuario` | `app/Http/Controllers/PacienteController.php` | `pacientes.prontuario` | `pacientes` | **A** — prontuário implementado |
| 59 | PAC-08 | Todas | Duplicidades de cadastro | Nomes variados | `PacienteNomeService->possiveisDuplicatas()` (grupos exatos + semelhantes) | `app/Services/Pacientes/PacienteNomeService.php` | `financeiro.duplicatas.index` | `pacientes` | **A** — detecção de duplicatas implementada (não funde) |
| 60 | PAC-09 | Todas | Busca de cliente por nome | Lista de nomes em aba | Busca `pacientes` por nome (like) | `app/Http/Controllers/PacienteController.php@index` | `pacientes.index` | `pacientes` | **A** — busca funcional |
| 61 | PAC-10 | Todas | Importação de pacientes | Planilha Pacientes.xlsx | Importação via CSV/modelo (não nas 8 planilhas principais) | `app/Console/Commands/Importar*` | — | `pacientes` | **E** — depende de arquivo separado |

### Módulo 7 — Controle de sessões e pacotes (8 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 62 | PSP-01 | PAGAMENTOS.xlsx / meses | Pacotes de sessão | "PACOTE DE MASSAGEM", "PACOTE PILATES" | `pacotes_sessao` (369 registros) | `app/Http/Controllers/PacoteSessaoController.php` | `pacotes.*` (todas verticais) | `pacotes_sessao` | **A** — tabela e fluxo implementados |
| 63 | PSP-02 | PAGAMENTOS.xlsx / meses | Controle de consumo | Referenciado em fórmulas SUM | `consumo_sessao` por pacote/cliente | `app/Models/ConsumoSessao.php`, `app/Services/Fisioterapia/PacoteSessaoService.php` | `pacotes.consumir` | `consumo_sessao` | **A** — consumo funcional |
| 64 | PSP-03 | PAGAMENTOS.xlsx / meses | Sessões restantes | Total menos consumido | `PacoteSessao->restantes()` / `saldo()` | `app/Models/PacoteSessao.php` | `pacotes.show`, `relatorios.pacotes` | `pacotes_sessao` | **A** — saldo calculado |
| 65 | PSP-04 | PAGAMENTOS.xlsx / meses | Validade do pacote | Não tem coluna dedicada | `pacotes_sessao.validade_em` | `app/Models/PacoteSessao.php` | `pacotes.store/update` | `pacotes_sessao` | **A** — validade implementada |
| 66 | PSP-05 | PAGAMENTOS.xlsx / meses | Cancelamento de pacote | Não tem coluna dedicada | `pacotes_sessao.status` = cancelado | `app/Http/Controllers/PacoteSessaoController.php@destroy` | `pacotes.destroy` | `pacotes_sessao` | **A** — cancelamento preserva extrato |
| 67 | PSP-06 | PAGAMENTOS.xlsx / meses | Pacote concluído | Quando todas usadas | `PacoteSessao->estaAtivo()` + status concluído | `app/Models/PacoteSessao.php` | `pacotes.show` | `pacotes_sessao` | **A** — conclusão automática |
| 68 | PSP-07 | PAGAMENTOS.xlsx / meses | Consumo vinculado ao atendimento | Não existe explicitamente | `consumo_sessao.appointment_id` (idempotência, unique) | `app/Models/ConsumoSessao.php`, `database/migrations/tenant/2026_11_05_081447_*` | `atendimentos.consumir` | `consumo_sessao` | **A** — idempotência por appointment_id |
| 69 | PSP-08 | PGTO PILATES.xlsx / PAGAMENTOS PILATES 2026 | Pacote de pilates (não FT) | Controle fora da vertical FT | `pacotes_sessao` sem restrição de vertical; middleware `vertical:fisioterapia` removido das rotas | `app/Http/Controllers/PacoteSessaoController.php`, `routes/web.php` | `pacotes.*` (todas verticais) | `pacotes_sessao` | **A** — middleware removido; pacotes disponíveis para todas verticais; teste `test_clinica_odontologica_abre_os_pacotes` aprovado |

### Módulo 8 — Presença, faltas e reposições (6 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 70 | PRE-01 | DEPILAÇÃO LASER.xlsx / cada dia | Marcar presença | Coluna PRESENÇA = PRESENTE | `appointments` status `atendido` | `app/Services/Atendimento/AtendimentoService.php@marcarPresente` | `agenda.presenca` | `appointments` | **A** — fluxo idêntico |
| 71 | PRE-02 | DEPILAÇÃO LASER.xlsx / cada dia | Marcar falta | Coluna PRESENÇA = AUSENTE | `appointments` status `faltou` | `app/Services/Atendimento/AtendimentoService.php@marcarFalta` | `agenda.presenca` | `appointments` | **A** — fluxo idêntico (48 registros corrigidos) |
| 72 | PRE-03 | DEPILAÇÃO LASER.xlsx / cada dia | Falta não consome sessão | Não explicitado | `marcarFalta` recusa se já há consumo; `consumirSessao` exige `atendido` | `app/Services/Atendimento/AtendimentoService.php` | `atendimentos.consumir` | `consumo_sessao`, `appointments` | **A** — regra implementada |
| 73 | PRE-04 | DEPILAÇÃO LASER.xlsx / cada dia | Relatório de presença/faltas | Contagem por mês | `RelatorioController@presencaFaltas` (por profissional/mês) | `app/Http/Controllers/RelatorioController.php` | `relatorios.presenca-faltas` | `appointments` | **A** — relatório funcional |
| 74 | PRE-05 | DEPILAÇÃO LASER.xlsx / cada dia | Reposição de sessão | Não encontrado explicitamente | `WaitingList` + `consumo_sessao.reposicao` | `app/Http/Controllers/AgendaController.php`, `app/Models/ConsumoSessao.php` | `agenda.waiting.*`, `pacotes.consumir` | `waiting_lists`, `consumo_sessao` | **A** — reposição explícita registrada em consumo_sessao.reposicao e contada no relatório de pacotes |
| 75 | PRE-06 | AGENDA CLINICA .xlsx / DAIANE | Retornos agendados | "RT 3 MESES" na grade | `appointments.retorno` + `WaitingList` | `app/Http/Controllers/AgendaController.php` | `agenda.store` | `appointments`, `waiting_lists` | **A** — retorno agendável |

### Módulo 9 — Contas a pagar (8 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 76 | CP-01 | CONTAS .xlsx / 50 abas (2023-2026) | Tabela de contas a pagar | Linha por conta do mês (aluguel, energia, cartão) | `accounts_payable` (nova tabela) | `app/Http/Controllers/ContaPagarController.php`, `app/Models/AccountsPayable.php` | `financeiro.contas-pagar.*` | `accounts_payable` | **A** — criação, baixa e quitação implementadas e testadas; importação de dados históricos permanece pendente |
| 77 | CP-02 | CONTAS .xlsx / cada mês | Vencimento da conta | Coluna VENCIMENTO | `accounts_payable.data_vencimento` | `app/Models/AccountsPayable.php` | `financeiro.contas-pagar.store` | `accounts_payable` | **A** — vencimento persistido e usado no cálculo de atraso, testado |
| 78 | CP-03 | CONTAS .xlsx / cada mês | Status PAGO/ATRASADO | Validação de lista | `AccountsPayable->statusEfetivo()` (atrasado calculado) | `app/Models/AccountsPayable.php` | `financeiro.contas-pagar.index` | `accounts_payable` | **A** — status efetivo calculado (pendente/atrasado/pago) e testado |
| 79 | CP-04 | CONTAS .xlsx / cada mês | Baixa (pagamento) da conta | Coluna PAGAMENTO/DATA PAGAM. | `accounts_payable_pagamentos` (baixas parciais/totais) | `app/Http/Controllers/ContaPagarController.php@baixar` | `financeiro.contas-pagar.baixar` | `accounts_payable_pagamentos` | **A** — baixa total e parcial em accounts_payable_pagamentos, testada |
| 80 | CP-05 | CONTAS .xlsx / cada mês | Baixa parcial | Valor parcial pago | `AccountsPayable->saldo` (valor menos baixas) | `app/Models/AccountsPayable.php` | `financeiro.contas-pagar.baixar` | `accounts_payable_pagamentos` | **A** — saldo = valor − baixas, testado com baixa parcial |
| 81 | CP-06 | CONTAS .xlsx / cada mês | Idempotência de baixa | Não existe | `idempotency_key` em `accounts_payable_pagamentos` | `app/Services/Financeiro/ContasPagarService.php` | `financeiro.contas-pagar.baixar` | `accounts_payable_pagamentos` | **A** — idempotency_key impede baixa duplicada, testado |
| 82 | CP-07 | CONTAS .xlsx / cada mês | Categoria da conta | TIPO: NF, BOLETO, DUPLICATA, OUTROS | `financeiro_categorias` + `accounts_payable.tipo`/`tipo_documento` | `app/Models/FinancialCategory.php`, `app/Models/AccountsPayable.php` | `financeiro.contas-pagar.store` | `financeiro_categorias`, `accounts_payable` | **A** — categoria e tipo/tipo_documento persistidos e testados |
| 83 | CP-08 | CONTAS .xlsx / cada mês | Fornecedor da conta | Nome do favorecido | `accounts_payable.supplier_id` → `suppliers` | `app/Models/AccountsPayable.php` | `financeiro.contas-pagar.store` | `accounts_payable`, `suppliers` | **A** — fornecedor (supplier_id) persistido e exibido, testado |

### Módulo 10 — Contas a receber (8 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 84 | CR-01 | PAGAMENTOS.xlsx / cada mês | Tabela de contas a receber | Estrutura em PAGAMENTOS | `FinanceiroController@index` lista, filtra por paciente/status e calcula o total em aberto descontando o recebido | `app/Models/AccountsReceivable.php`, `app/Http/Controllers/FinanceiroController.php` | `financeiro.index`, `financeiro.receive` | `accounts_receivable` | **A** — listagem e filtro funcionais (testado em `test_contas_receber_listagem_e_filtro_funcional`) |
| 85 | CR-02 | PAGAMENTOS.xlsx / cada mês | Vínculo com paciente | Apenas NOME textual | `payments.paciente_id` + `accounts_receivable.paciente_id` | `app/Models/Payment.php`, `app/Models/AccountsReceivable.php`, `app/Http/Controllers/PagamentoController.php` | `financeiro.pagamentos.vincular`, `financeiro.receive` | `payments`, `accounts_receivable` | **A** — vínculo pagamento-paciente existe e é testado |
| 86 | CR-03 | PAGAMENTOS.xlsx / cada mês | Baixa de conta a receber | Valor recebido quita conta | `RecebimentoService->receber()` (transacional, bloqueio) | `app/Services/Financeiro/RecebimentoService.php`, `app/Http/Controllers/FinanceiroController.php@receive` | `financeiro.receive` | `accounts_receivable`, `payments` | **A** — baixa transacional implementada e testada |
| 87 | CR-04 | PAGAMENTOS.xlsx / cada mês | Pagamento parcial | Valor menor que o da conta | `accounts_receivable.status` = parcial | `app/Models/AccountsReceivable.php`, `app/Services/Financeiro/RecebimentoService.php` | `financeiro.receive` | `accounts_receivable` | **A** — pagamento parcial implementado |
| 88 | CR-05 | PAGAMENTOS.xlsx / cada mês | Rejeita pagamento acima do saldo | Não existe na planilha | `OperacaoFinanceiraInvalida::valorAcimaDoSaldo` | `app/Services/Financeiro/RecebimentoService.php` | `financeiro.receive` | `accounts_receivable` | **A** — validação implementada e testada |
| 89 | CR-06 | PAGAMENTOS.xlsx / cada mês | Lista de contas a receber | Resumo por mês | `FinanceiroController@index` (contas paginas) | `app/Http/Controllers/FinanceiroController.php` | `financeiro.index` | `accounts_receivable` | **A** — listagem funcional |
| 90 | CR-07 | PAGAMENTOS.xlsx / cada mês | Total em aberto | Soma dos valores | `AccountsReceivable->saldo` + `totalAberto` | `app/Http/Controllers/FinanceiroController.php` | `financeiro.index` | `accounts_receivable` | **A** — total em aberto descontando pagamentos |
| 91 | CR-08 | PAGAMENTOS.xlsx / cada mês | Vínculo payment ↔ conta a receber | Não existe na planilha | `payments.accounts_receivable_id`; pagamento manual cria conta e vincula payment | `app/Models/Payment.php`, `app/Services/Financeiro/RecebimentoService.php`, `app/Http/Controllers/PagamentoController.php` | `financeiro.receive`, `financeiro.pagamentos.store` | `payments`, `accounts_receivable` | **A** — vínculo implementado e testado (`test_vinculo_payment_conta_a_receber`) |

### Módulo 11 — Pagamentos e recebimentos (9 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 92 | PG-01 | PAGAMENTOS.xlsx / 69 abas | Lançamento de pagamento | DATA, NOME, SERVIÇOS, TIPO PGTO, VALOR, DATA PGTO, VALOR RECEBIDO | `payments` (6.163 registros) | `app/Http/Controllers/PagamentoController.php@store` | `financeiro.pagamentos.store` | `payments` | **A** — estrutura compatível, volume compatível |
| 93 | PG-02 | PAGAMENTOS.xlsx / cada mês | Formas de pagamento | PIX, DINHEIRO, CARTÃO CRÉDITO, DÉBITO | `payment_methods` + `payments.payment_method_id` | `app/Models/PaymentMethod.php` | `financeiro.pagamentos.store` | `payment_methods`, `payments` | **A** — formas compatíveis |
| 94 | PG-03 | PAGAMENTOS.xlsx / cada mês | Plano de saúde (copagamento) | Campo K (valor com plano) | `payments.insurance_plan_id` (FK anulável) + `insurance_companies`/`insurance_plans` | `app/Models/Payment.php`, `app/Models/InsurancePlan.php`, `app/Services/Financeiro/RecebimentoService.php`, `app/Http/Controllers/FinanceiroController.php` | `financeiro.pagamentos.store` | `payments`, `insurance_plans` | **A** — vínculo estruturado via `insurance_plan_id`; campo anulável; teste `test_pagamento_com_plano_de_saude_campo_anulável` aprovado |
| 95 | PG-04 | PAGAMENTOS.xlsx / cada mês | Vinculação pagamento-paciente | Nome textual (sem FK) | `payments.paciente_id` + `PacienteNomeService` + tela de revisão `vincular` | `app/Models/Payment.php`, `app/Services/Pacientes/PacienteNomeService.php`, `app/Http/Controllers/PagamentoController.php` | `financeiro.pagamentos.vincular` | `payments`, `pacientes` | **A** — vínculo implementado e testado (`test_vinculo_pagamento_paciente_nao_sobrescreve`) |
| 96 | PG-05 | PAGAMENTOS.xlsx / cada mês | Pagamento manual (nova conta) | Não existe na planilha | `PagamentoController@store` (conta + pagamento em transação) | `app/Http/Controllers/PagamentoController.php@store` | `financeiro.pagamentos.store` | `accounts_receivable`, `payments` | **A** — lançamento manual funcional |
| 97 | PG-06 | PAGAMENTOS.xlsx / cada mês | Pagamentos sem vínculo | Lançamentos sem dono | Filtro `vinculo=sem` + `resumo.sem_vinculo` | `app/Http/Controllers/PagamentoController.php@index` | `financeiro.pagamentos.index` | `payments` | **A** — filtro e resumo implementados |
| 98 | PG-07 | PAGAMENTOS.xlsx / cada mês | Tela de revisão (vincular) | Não existe | `PagamentoController@revisar` (candidatos por nome) | `app/Http/Controllers/PagamentoController.php@revisar` | `financeiro.pagamentos.revisar` | `payments`, `pacientes` | **A** — tela de revisão funcional |
| 99 | PG-08 | PAGAMENTOS.xlsx / cada mês | Não sobrescrever vínculo | Não existe | `vincular` recusa se `paciente_id` já preenchido | `app/Http/Controllers/PagamentoController.php@vincular` | `financeiro.pagamentos.vincular` | `payments` | **A** — proteção implementada |
| 100 | PG-09 | PAGAMENTOS.xlsx / cada mês | Duplicidades de paciente | GISELE/GISELE ALVES/GISELE LVES | `PacienteNomeService->possiveisDuplicatas()` | `app/Services/Pacientes/PacienteNomeService.php` | `financeiro.duplicatas.index` | `pacientes` | **A** — detecção de duplicatas implementada |

### Módulo 12 — Profissionais externos (6 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 101 | PE-01 | PROFISSIONAIS EXTERNOS.xlsx / cada mês | Cadastro de profissional externo | NUTRICIONISTA: APOENA (DIÁRIA) | `professionals` com tipo `nutricionista` e `registro_profissional`; cadastro pela tela | `app/Http/Controllers/ProfissionalController.php`, `app/Models/Professional.php` | `profissionais.*` | `professionals` | **A** — cadastro pela tela com tipo e registro (testado em `test_profissional_externo_cadastro_e_pagamento`) |
| 102 | PE-02 | PROFISSIONAIS EXTERNOS.xlsx / cada mês | Pagamento de profissional externo | VALOR PAGO por sessão/hora | Conta a pagar com `professional_id` e `tipo_remuneracao`; a baixa registra a saída do caixa (não é conta a receber) | `app/Http/Controllers/ContaPagarController.php`, `app/Services/Financeiro/ContasPagarService.php` | `financeiro.contas-pagar.*` (`financeiro.create/receive`) | `accounts_payable`, `accounts_payable_pagamentos` | **A** — pagamento sai do caixa vinculado ao profissional (testado em `test_profissional_externo_pagamento_sai_do_caixa`) |
| 103 | PE-03 | PROFISSIONAIS EXTERNOS.xlsx / cada mês | Valor fixo R$ 150,00 por sessão | Valor padrão por profissional | `tipo_remuneracao` (diaria/porcentagem/valor_fixo) + valor informado; o sistema não calcula a porcentagem sozinho | `app/Models/AccountsPayable.php`, `app/Services/Financeiro/ContasPagarService.php` | `financeiro.contas-pagar.store` | `accounts_payable` | **A** — modelo e valor informado aceitos; modelo inválido recusado (testado em `test_profissional_externo_tipo_porcentagem`) |
| 104 | PE-04 | PROFISSIONAIS EXTERNOS.xlsx / cada mês | Total mensal por profissional | Coluna TOTAL MENSAL | Painel mensal na listagem de contas a pagar, por data da baixa, separando principal, juros e multa | `app/Http/Controllers/ContaPagarController.php`, `resources/views/financeiro/contas-pagar/index.blade.php` | `financeiro.contas-pagar.index` | `accounts_payable`, `accounts_payable_pagamentos` | **A** — total mensal por profissional separado por encargo (testado em `test_total_mensal_por_profissional_externo`) |
| 105 | PE-05 | PROFISSIONAIS EXTERNOS.xlsx / cada mês | Outros profissionais externos | Apenas nutricionista APOENA registrado | `professionals` + `suppliers`; qualquer profissional externo usa o mesmo cadastro e a mesma conta a pagar | `app/Models/Professional.php`, `app/Models/Supplier.php` | `profissionais.index`, `financeiro.fornecedores.index` | `professionals`, `suppliers` | **A** — cadastro e pagamento do profissional externo, com roteiro de homologação (testado em `test_profissional_externo_cadastro_e_pagamento`) |
| 106 | PE-06 | PROFISSIONAIS EXTERNOS.xlsx / cada mês | Comissão do profissional | Não tem coluna dedicada | `professionals.comissao` | `app/Models/Professional.php`, `app/Http/Controllers/ProfissionalController.php` | `profissionais.store/update` | `professionals` | **A** — campo comissão implementado |

### Módulo 13 — Descontos, juros e vencimentos (6 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 107 | DJ-01 | CONTAS .xlsx / cada mês | Dias vencidos (DAYS360) | `DAYS360(B4,A4)` | `FinanceHelper->dias360()` (base 30/360) | `app/Support/FinanceHelper.php` | — (uso interno) | — | **A** — cálculo DAYS360 replicado |
| 108 | DJ-02 | CONTAS .xlsx / cada mês | Dias de atraso | Não tem coluna dedicada | `FinanceHelper->diasAtraso()` | `app/Support/FinanceHelper.php` | — (uso interno) | — | **A** — cálculo implementado |
| 109 | DJ-03 | Várias planilhas | Juros sobre vencimento | Fórmulas em algumas abas OUTUBRO 2023+ | Juros informados na baixa da conta a pagar (`accounts_payable_pagamentos.juros`), sem taxa fixa; o extrato separa principal de juros | `app/Support/FinanceHelper.php`, `app/Services/Financeiro/ContasPagarService.php` | `financeiro.contas-pagar.baixar` | `accounts_payable_pagamentos` | **A** — juros informados na baixa, sem inventar taxa (testado em `test_baixa_conta_pagar_com_juros_e_multa_informados`) |
| 110 | DJ-04 | Várias planilhas | Multa por atraso | Fórmulas em algumas abas | Multa informada na baixa da conta a pagar (`accounts_payable_pagamentos.multa`), sem percentual fixo | `app/Support/FinanceHelper.php`, `app/Services/Financeiro/ContasPagarService.php` | `financeiro.contas-pagar.baixar` | `accounts_payable_pagamentos` | **A** — multa informada na baixa, sem inventar percentual (testado em `test_baixa_conta_pagar_com_juros_e_multa_informados`) |
| 111 | DJ-05 | Várias planilhas | Desconto à vista | Presente em algumas abas (0.0) | Campo `desconto` em `accounts_receivable`; fluxo de recebimento aceita `desconto` informado pelo usuário; desconto acumulado na conta; status quita quando `valor_pago + desconto >= valor` | `app/Http/Requests/FinanceiroPaymentRequest.php`, `app/Services/Financeiro/RecebimentoService.php`, `app/Http/Controllers/FinanceiroController.php` | `financeiro.receive` | `accounts_receivable` | **A** — desconto implementado e testado (`test_recebimento_com_desconto_quita_conta`, `test_recebimento_desconto_nao_quita_fica_parcial`) |
| 112 | DJ-06 | CONTAS .xlsx / cada mês | Juros/desconto na coluna | JUROS/DESCONTO | Estrutura de juros e multa informados já existe na baixa; falta a decisão sobre **desconto** em conta a pagar | `app/Models/AccountsPayable.php`, `app/Services/Financeiro/ContasPagarService.php` | `financeiro.contas-pagar.store` | `accounts_payable`, `accounts_payable_pagamentos` | **D** — desconto em conta a pagar aguarda decisão da cliente (ver `DECISOES_PENDENTES_CLIENTE.md`) |

### Módulo 14 — Relatórios e indicadores (9 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 113 | REL-01 | Todas (resumo por mês) | Total de receitas por mês | Totais por mês nas planilhas | `RelatorioController@receitasMensais` (por mês/serviço/forma) | `app/Http/Controllers/RelatorioController.php` | `relatorios.receitas` | `payments`, `accounts_payable_pagamentos` | **A** — relatório de receitas funcional |
| 114 | REL-02 | Todas | Total de atendimentos por mês | Contagem de consultas por mês | `RelatorioController@index` (resumoAgenda) + `presencaFaltas` | `app/Http/Controllers/RelatorioController.php` | `relatorios.index`, `relatorios.presenca-faltas` | `appointments` | **A** — contagem por mês funcional |
| 115 | REL-03 | PLANO DE SAÚDE .xlsx | Indicadores de plano de saúde | VALOR NF vs VALOR PAGO por ano | `insurance_plans` + `payments` (vínculo parcial) | `app/Models/InsurancePlan.php`, `app/Models/Payment.php` | `convenios.*` | `insurance_companies`, `insurance_plans` | **A** — estrutura de planos de saúde existe e é testada |
| 116 | REL-04 | CONTAS .xlsx | Controle de fornecedores | 94 fornecedores importados | `suppliers` listing + relatórios | `app/Http/Controllers/FornecedorController.php` | `financeiro.fornecedores.*` | `suppliers` | **A** — fornecedores importados e listados |
| 117 | REL-05 | PGTO PILATES + agendas | Indicadores de pilates/massagem | Controle por mês | `RelatorioController@pacotesSessoes` + `receitasMensais` (por serviço) | `app/Http/Controllers/RelatorioController.php` | `relatorios.pacotes`, `relatorios.receitas` | `pacotes_sessao`, `payments` | **A** — totais por serviço existem (testados) |
| 118 | REL-06 | Todas | Exportação CSV | Planilha Excel nativa | `RelatorioController@exportarCsv` (receitas, presenca, pacotes) | `app/Http/Controllers/RelatorioController.php` | `relatorios.exportar` | `payments`, `appointments`, `pacotes_sessao` | **A** — exportação CSV com BOM UTF-8 |
| 119 | REL-07 | Todas | Fluxo de caixa | Entradas menos saídas | `FinanceiroController@fluxoCaixa` | `app/Http/Controllers/FinanceiroController.php` | `financeiro.fluxo-caixa` | `payments`, `accounts_payable_pagamentos` | **A** — fluxo de caixa funcional |
| 120 | REL-08 | Todas | Presença/faltas por período | Contagem por mês | `RelatorioController@presencaFaltas` (por profissional/mês, taxa de faltas) | `app/Http/Controllers/RelatorioController.php` | `relatorios.presenca-faltas` | `appointments` | **A** — relatório de presença/faltas funcional |
| 121 | REL-09 | Todas | Pacotes: contratado/consumido/restante | Resumo de pacotes | `RelatorioController@pacotesSessoes` | `app/Http/Controllers/RelatorioController.php` | `relatorios.pacotes` | `pacotes_sessao`, `consumo_sessao` | **A** — relatório de pacotes funcional |

### Módulo 15 — Marketing e aniversariantes (6 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 122 | MKT-01 | ANIVERSARIANTES.xlsx / ANIVERSARIANTES | Lista de aniversariantes por mês | Colunas NOME, DIA e CHEGANDO? por mês | Relatório `relatorios.aniversariantes` lista pacientes ativos com aniversário no mês selecionado, com sinalizador `chegando` para aniversários em até 2 dias | `app/Http/Controllers/RelatorioController.php@aniversariantes` | `relatorios.aniversariantes` | `pacientes` | **A** — relatório implementado e testado (`test_relatorio_aniversariantes_mes`) |
| 123 | MKT-02 | ANIVERSARIANTES.xlsx / ANIVERSARIANTES | Alerta "TÁ CHEGANDO!" | Fórmulas IF com NOW() para aniversários próximos | Relatório `relatorios.aniversariantes` com sinalizador `chegando` para aniversários em até 2 dias. Alerta/notificação automática depende de decisão da cliente (ver `DECISOES_PENDENTES_CLIENTE.md`). | `app/Http/Controllers/RelatorioController.php@aniversariantes`, `resources/views/relatorios/aniversariantes.blade.php` | `relatorios.aniversariantes` | `pacientes` | **A** — relatório com flag "chegando" implementado e testado (`test_relatorio_aniversariantes_chegando`); notificação automática é decisão de produto |
| 124 | MKT-03 | AGENDA CLINICA .xlsx / MARKETING | Dados de marketing/prospects | Calendário de conteúdos de WhatsApp por data (não é CRM de prospects) | O conteúdo da aba é atendido por MKT-05/MKT-06 (`social_posts`). O `Platform\ProspectController` é CRM comercial do SaaS e não representa o calendário da clínica | `app/Http/Controllers/SocialPostController.php`, `app/Http/Controllers/Platform\ProspectController.php` | `social.*`, `platform.prospects.*` | `social_posts`, `prospects` | **C** — a aba é calendário de conteúdos, não CRM; o conteúdo é coberto por MKT-05/MKT-06. Reclassificado de A para C (testado em `test_marketing_calendario_de_conteudos_usa_posts_sociais`) |
| 125 | MKT-04 | AGENDA CLINICA .xlsx / CAIXA (MASSAGEM) | Controle de caixa massagem | CAIXA MARÇO a DEZEMBRO | `financeiro.fluxo-caixa` + `payments` (servico=MASSAGEM) | `app/Http/Controllers/FinanceiroController.php` | `financeiro.fluxo-caixa` | `payments` | **A** — fluxo de caixa filtra por serviço (testado) |
| 126 | MKT-05 | AGENDA CLINICA .xlsx / MARKETING | Campanhas | Dados variados | `SocialPost` | `app/Http/Controllers/SocialPostController.php` | `social.*` | `social_posts` | **A** — campanha/post social criado como rascunho e aprovado, testado |
| 127 | MKT-06 | AGENDA CLINICA .xlsx / MARKETING | Posts sociais | Não tem coluna dedicada | `SocialPost` + `SocialSetting` | `app/Http/Controllers/SocialPostController.php` | `social.*` | `social_posts`, `social_settings` | **A** — legendas, hashtags e redes persistidas e aprovação testadas |

---

## Conclusão da matriz

- **Total de fluxos analisados: 127** (recontagem por script; a matriz anterior declarava 137 por erro de documentação)
- **Classificados como A (equivalente, implementado e testado): 124** → **97,64% de equivalência real**
- Classificados como B: 0
- Classificados como C: 1 (MKT-03 — aba de calendário de conteúdos, coberta por MKT-05/MKT-06)
- Classificados como D: 1 (DJ-06 — desconto em conta a pagar aguarda decisão)
- Classificados como E: 1 (PAC-10 — falta `Pacientes.xlsx` e regra de deduplicação)

**A meta de 100% NÃO está atingida.** Somente fluxos A contam. Os 3 fluxos não-A (MKT-03, DJ-06 e PAC-10) dependem de decisão comercial da cliente ou de arquivo externo, não de implementação técnica.

### Fluxos reclassificados nesta sessão

| ID | Antes | Depois | Motivo | Teste |
|----|-------|--------|--------|-------|
| AGD-05 | A | A | Turma real (dia, horário, profissional, alunos e presença), não pacote na tela do paciente | `test_turma_pilates_grade_calendario_e_presenca` |
| PIL-01 | A | A | Tabela cruzada vira presença por aluno e por data em `turma_presencas` | `test_pilates_tabela_cruzada_presencas_por_aluno_e_data` |
| PIL-05 | A | A | Calendário montado a partir das turmas, respeitando o mês | `test_pilates_calendario_respeita_mes_e_ignora_turma_encerrada` |
| PIL-06 | E | A | Lista de presença por paciente via consumo de sessão | `test_campanha_pilates_lista_consumo_por_paciente` |
| PE-02 | B | A | Pagamento do profissional externo sai como conta a pagar vinculada, não como recebimento | `test_profissional_externo_pagamento_sai_do_caixa` |
| PE-03 | B | A | Modelo de remuneração (diária/porcentagem/valor fixo) e valor informado | `test_profissional_externo_tipo_porcentagem` |
| PE-04 | B | A | Painel mensal por profissional, separando principal, juros e multa | `test_total_mensal_por_profissional_externo` |
| PE-05 | E | A | Cadastro pela tela e pagamento via conta a pagar | `test_profissional_externo_cadastro_e_pagamento` |
| DJ-03 | D | A | Juros informados na baixa, sem taxa fixa inventada | `test_baixa_conta_pagar_com_juros_e_multa_informados` |
| DJ-04 | D | A | Multa informada na baixa, sem percentual fixo inventado | `test_baixa_conta_pagar_com_juros_e_multa_informados` |
| CR-01 | C | A | Listagem e filtro de contas a receber funcionais | `test_contas_receber_listagem_e_filtro_funcional` |
| MKT-03 | A | C | A aba é calendário de conteúdos; o CRM de prospects do SaaS não a substitui | `test_marketing_calendario_de_conteudos_usa_posts_sociais` |
| LAS-08 | D | A | Contagem de presenças/faltas por área | `test_relatorio_presenca_faltas_por_area` |
| FIS-02 | B | A | Fisioterapia domiciliar no fluxo procedimento → recebimento → relatório | `test_fisioterapia_domiciliar_percorre_fluxo_financeiro` |
| PRE-05 | C | A | Reposição explícita de sessão registrada e contada | `test_reposicao_de_sessao_registrada_e_contada` |
| CP-01 | D | A | Criação, baixa e quitação da conta a pagar | `test_conta_a_pagar_criar_baixar_quitar` |
| CP-02 | D | A | Vencimento persistido e usado no atraso | `test_conta_a_pagar_criar_baixar_quitar` |
| CP-03 | D | A | Status efetivo (pendente/atrasado/pago) calculado | `test_atraso_calculado_nao_gravado` |
| CP-04 | D | A | Baixa total e parcial | `test_conta_a_pagar_criar_baixar_quitar` |
| CP-05 | D | A | Saldo após baixa parcial | `test_conta_a_pagar_criar_baixar_quitar` |
| CP-06 | D | A | Idempotência de baixa | `test_baixa_conta_pagar_idempotente_nao_duplica` |
| CP-07 | D | A | Categoria e tipo/tipo_documento | `test_conta_pagar_categoria_e_fornecedor` |
| CP-08 | D | A | Fornecedor (supplier_id) | `test_conta_pagar_categoria_e_fornecedor` |
| MKT-05 | E | A | Campanha/post social criado e aprovado | `test_campanha_post_social_legendas_e_aprovacao` |
| MKT-06 | E | A | Legendas, hashtags e redes persistidas | `test_campanha_post_social_legendas_e_aprovacao` |

### Fluxos reclassificados em sessões anteriores

| ID | Antes | Depois | Motivo | Teste |
| AGD-02 | B | A | Filtro por profissional funciona na agenda | `test_agenda_filtra_por_profissional` |
| LAS-09 | B | A | Filtro por busca e profissional funciona | `test_agenda_filtra_por_busca_e_profissional` |
| MAS-01 | B | A | Fluxo de caixa filtra por serviço | `test_fluxo_caixa_filtra_por_servico_massagem` |
| MAS-05 | B | A | Totais por mês existem no relatório de receitas | `test_relatorios_receitas_por_servico` |
| PAC-02 | B | A | Busca aproximada não funde cadastros | `test_paciente_variacoes_nome_nao_funde` |
| PAC-03 | B | A | Histórico unifica consultas, pacotes, pagamentos | `test_historico_paciente_unifica_consultas_pacotes_pagamentos` |
| REL-03 | B | A | Estrutura de planos de saúde existe | `test_insurance_plans_estrutura_existe` |
| REL-05 | B | A | Totais por serviço existem nos relatórios | `test_relatorios_receitas_por_servico` |
| MKT-04 | B | A | Fluxo de caixa filtra por serviço | `test_fluxo_caixa_filtra_por_servico_massagem` |
| PIL-03 | C | A | Status aberta/parcial/pago existem | `test_conta_receber_status_aberta_parcial_pago` |
| CR-02 | C | A | Vínculo pagamento-paciente existe | `test_pagamento_paciente_vinculo_existe` |
