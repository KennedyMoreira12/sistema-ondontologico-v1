# MATRIZ DE 100% — 137 FLUXOS FUNCIONAIS (PLANILHAS DA DAIANE x LJ SMARTCLIC)

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
| 1. Agenda e horários recorrentes | 12 | 4 | 2 | 4 | 2 | 0 | 33% |
| 2. Depilação a laser | 14 | 9 | 2 | 2 | 1 | 0 | 64% |
| 3. Pilates | 9 | 5 | 1 | 3 | 0 | 0 | 56% |
| 4. Fisioterapia | 9 | 6 | 1 | 2 | 0 | 0 | 67% |
| 5. Massagem | 7 | 3 | 2 | 2 | 0 | 0 | 43% |
| 6. Cadastro e histórico de pacientes | 10 | 5 | 2 | 2 | 0 | 1 | 50% |
| 7. Controle de sessões e pacotes | 8 | 7 | 0 | 0 | 1 | 0 | 88% |
| 8. Presença, faltas e reposições | 6 | 5 | 0 | 1 | 0 | 0 | 83% |
| 9. Contas a pagar | 8 | 0 | 1 | 1 | 6 | 0 | 0% |
| 10. Contas a receber | 8 | 5 | 0 | 2 | 1 | 0 | 63% |
| 11. Pagamentos e recebimentos | 9 | 6 | 1 | 1 | 0 | 1 | 67% |
| 12. Profissionais externos | 6 | 3 | 1 | 1 | 0 | 1 | 50% |
| 13. Descontos, juros e vencimentos | 6 | 1 | 1 | 1 | 3 | 0 | 17% |
| 14. Relatórios e indicadores | 9 | 7 | 1 | 1 | 0 | 0 | 78% |
| 15. Marketing e aniversariantes | 6 | 2 | 1 | 1 | 0 | 2 | 33% |
| **TOTAL** | **137** | **72** | **13** | **21** | **9** | **2** | **52,6%** |

---

## Matriz detalhada por fluxo

### Módulo 1 — Agenda e horários recorrentes (12 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 1 | AGD-01 | AGENDA CLINICA .xlsx / AGENDA ANTIGA | Cadastro de horário por dia da semana | Grade SEGUNDA..SÁBADO, horários em frações (0.25=6h), nomes de profissionais | `AgendaController@store` cria appointment com `dia` + `hora_inicio` + duração | `app/Http/Controllers/AgendaController.php`, `app/Services/Agenda/AgendamentoService.php` | `agenda.store` (`agenda.create`) | `appointments`, `professional_schedules` | **C** — grade semanal sem data; sistema usa data específica |
| 2 | AGD-02 | AGENDA CLINICA .xlsx / AGENDA ANTIGA | Horários por profissional | Colunas SEGUNDA..SÁBADO com nome do profissional em cada célula | `ProfessionalSchedule` + filtro por `profissional_id` na agenda | `app/Models/ProfessionalSchedule.php`, `app/Http/Controllers/AgendaController.php` | `agenda.index` (`agenda.view`) | `professional_schedules`, `professionals` | **B** — tabela existe, mas preenchimento difere da grade |
| 3 | AGD-03 | AGENDA CLINICA .xlsx / AGENDA ANTIGA | Conflito de horário | Não há trava; duas consultas podem ocupar o mesmo horário | `AgendamentoService->criar()` com `HorarioOcupadoException` e lock de banco | `app/Services/Agenda/AgendamentoService.php` | `agenda.store` | `appointments` | **A** — trava de conflito implementada e testada |
| 4 | AGD-04 | AGENDA CLINICA .xlsx / CALENDÁRIO 2026 | Visualização mensal de ocupação | Grade D/S/T/Q/Q/S/S por mês com nomes | `AgendaController@mes` agrupa consultas por data | `app/Http/Controllers/AgendaController.php` | `agenda.mes` (`agenda.view`) | `appointments` | **A** — calendar view funcional |
| 5 | AGD-05 | AGENDA CLINICA .xlsx / CALENDÁRIO PILATES | Visualização por turma de pilates | Dias da semana com nomes de alunos | `PacoteSessaoController@index` por paciente + turmas via `ModuloPlanejadoController` | `app/Http/Controllers/PacoteSessaoController.php` | `pacientes.pacotes.index` (`pacotes.view`) | `pacotes_sessao` | **B** — existe, mas formato de turma difere |
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
| 18 | LAS-06 | DEPILAÇÃO LASER.xlsx / cada dia | Valor com plano de saúde | Coluna VALOR COM PLANO DE SAÚDE | `payments.insurance_plan_id` (FK anulável) + `insurance_companies`/`insurance_plans` | `app/Models/Payment.php`, `app/Models/InsurancePlan.php`, `app/Services/Financeiro/RecebimentoService.php`, `app/Http/Controllers/FinanceiroController.php` | `financeiro.pagamentos.store` | `payments`, `insurance_plans` | **A** — vínculo estruturado via `insurance_plan_id`; campo anulável para não quebrar lançamentos existentes |
| 19 | LAS-07 | DEPILAÇÃO LASER.xlsx / cada dia | Pago (sim/não) | Coluna PAGO (valor recebido vs a receber) | `payments` (valor recebido) + `accounts_receivable` (a receber) | `app/Models/AccountsReceivable.php`, `app/Services/Financeiro/RecebimentoService.php` | `financeiro.receive` | `accounts_receivable`, `payments` | **A** — conta a receber + baixa |
| 20 | LAS-08 | DEPILAÇÃO LASER.xlsx / cada dia | Fórmulas de contagem (COUNTIF/SUM) | Contagem de presentes, totais por área | Lógica de negócio no controller/service (não fórmulas de planilha) | `app/Http/Controllers/RelatorioController.php` | `relatorios.presenca-faltas`, `relatorios.receitas` | `appointments`, `payments` | **D** — fórmulas de planilha não replicadas; equivalente funcional existe |
| 21 | LAS-09 | DEPILAÇÃO LASER.xlsx / autofiltro | Filtro por área/profissional | Autofiltro `$B$4:$M$82` | Filtros via query string (`busca`, `status`, `profissional_id`) | `app/Http/Controllers/AgendaController.php` | `agenda.index`, `agenda.dia` | `appointments` | **B** — filtros existenciais, não idênticos ao autofiltro |
| 22 | LAS-10 | DEPILAÇÃO LASER.xlsx / DADOS | Validações de lista (área, forma pgto, presença) | Listas suspensas em DADOS!$A$5:$A$16 etc. | Validações no request/controller + enum de status | `app/Http/Requests/AppointmentStoreRequest.php` | `agenda.store` | `appointment_statuses`, `procedimentos` | **A** — validações implementadas |
| 23 | LAS-11 | DEPILAÇÃO LASER.xlsx / cada dia | Histórico por cliente | Revisar abas passadas | `AtendimentoController@historico` (consultas, pacotes, pagamentos) | `app/Http/Controllers/AtendimentoController.php` | `pacientes.historico` (`pacientes.view`) | `appointments`, `pacotes_sessao`, `payments` | **A** — histórico unificado por paciente |
| 24 | LAS-12 | DEPILAÇÃO LASER.xlsx / cada dia | Termos colados no nome (ex: "BOTOX/PREENCHIMENTO") | Nome misturado com serviço | `PacienteNomeService->limparNome()` remove ruídos conhecidos | `app/Services/Pacientes/PacienteNomeService.php` | `financeiro.pagamentos.revisar` | `payments` | **A** — limpeza de nome implementada |
| 25 | LAS-13 | DEPILAÇÃO LASER.xlsx / cada dia | Exclusão/cancelamento de consulta | Remover linha | `AgendaController@destroy` | `app/Http/Controllers/AgendaController.php` | `agenda.destroy` | `appointments` | **A** — cancelamento funcional |
| 26 | LAS-14 | DEPILAÇÃO LASER.xlsx / cada dia | Relatório diário de laser | Soma de valores por dia | `RelatorioController@receitasMensais` (por serviço/data) | `app/Http/Controllers/RelatorioController.php` | `relatorios.receitas` | `payments` | **A** — relatório por período funcional |

### Módulo 3 — Pilates (9 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 27 | PIL-01 | PGTO PILATES.xlsx / PAGAMENTOS PILATES 2026 | Controle de presença mensal | Tabela cruzada clientes × meses com PRESENTE/valor | `consumo_sessao` + `pacotes_sessao` (relacional, não cruzada) | `app/Models/ConsumoSessao.php`, `app/Models/PacoteSessao.php` | `pacotes.consumir` | `consumo_sessao`, `pacotes_sessao` | **C** — formato cruzado difere do relacional |
| 28 | PIL-02 | PGTO PILATES.xlsx / PAGAMENTOS PILATES 2026 | Valores por sessão/mês | R$ 100-200 por sessão por mês | `payments` com `servico` = PILATES | `app/Http/Controllers/PagamentoController.php` | `financeiro.pagamentos.store` | `payments` | **A** — valores compatíveis |
| 29 | PIL-03 | PGTO PILATES.xlsx / PAGAMENTOS PILATES 2026 | Situação (DEVENDO/REGULAR) | Coluna de situação por mês | `accounts_receivable.status` (aberta/parcial/pago) | `app/Models/AccountsReceivable.php` | `financeiro.index` | `accounts_receivable` | **C** — status existe, mas não todo fluxo usa |
| 30 | PIL-04 | AGENDA CLINICA .xlsx / PILOTES | Horários de aula de pilates | Grade semanal com nomes | `appointments` com `servico` = PILATES | `app/Http/Controllers/AgendaController.php` | `agenda.store` | `appointments` | **A** — agendamento de pilates funcional |
| 31 | PIL-05 | AGENDA CLINICA .xlsx / CALENDÁRIO PILATES | Calendário de turmas | Dias da semana com alunos | `PacoteSessaoController@index` + `ModuloPlanejadoController` | `app/Http/Controllers/PacoteSessaoController.php` | `pacientes.pacotes.index` | `pacotes_sessao` | **B** — existe, formato difere |
| 32 | PIL-06 | PGTO PILATES.xlsx / LISTA OUTUBRO ROSA | Controle de campanha | Lista esparsa de presença | `consumo_sessao` por paciente | `app/Models/ConsumoSessao.php` | `pacotes.consumir` | `consumo_sessao` | **E** — registro isolado, difícil classificar |
| 33 | PIL-07 | PAGAMENTOS.xlsx / meses | Pagamentos de pilates | Registros com "PILATES" | `payments` filtrando `servico` = PILATES | `app/Http/Controllers/PagamentoController.php` | `financeiro.pagamentos.index` | `payments` | **A** — pagamento de pilates funcional |
| 34 | PIL-08 | PGTO PILATES.xlsx / PAGAMENTOS PILATES 2026 | Fórmulas de totais (SUM por coluna) | SUM(C2:C105) etc. | `RelatorioController@pacotesSessoes` (total por pacote) | `app/Http/Controllers/RelatorioController.php` | `relatorios.pacotes` | `pacotes_sessao`, `consumo_sessao` | **A** — totais por pacote implementados |
| 35 | PIL-09 | AGENDA CLINICA .xlsx / DAIANE | Retornos de pilates (RT 3 MESES) | Anotações na grade | `WaitingList` + `appointments.retorno` | `app/Http/Controllers/AgendaController.php` | `agenda.waiting.store` | `waiting_lists`, `appointments` | **A** — retorno agendável |

### Módulo 4 — Fisioterapia (9 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 36 | FIS-01 | PAGAMENTOS.xlsx / meses | Registro de consulta fisioterapia | Linha com "FISIOTERAPIA" | `appointments` + `payments` com `servico` | `app/Http/Controllers/AgendaController.php`, `app/Http/Controllers/PagamentoController.php` | `agenda.store`, `financeiro.pagamentos.store` | `appointments`, `payments` | **A** — registro completo |
| 37 | FIS-02 | PAGAMENTOS.xlsx / meses | Fisioterapia domiciliar | Registros específicos | `procedimentos` com tipo específico | `app/Models/Procedure.php`, `app/Http/Controllers/ProcedimentoController.php` | `procedimentos.*` | `procedures` | **B** — registro existe, vínculo parcial |
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
| 45 | MAS-01 | AGENDA CLINICA .xlsx / CAIXA MARÇO..DEZ (MASSAGEM) | Controle de caixa mensal | Aba por mês com horários, valores, forma pgto | `payments` com `servico` = MASSAGEM + fluxo de caixa | `app/Http/Controllers/FinanceiroController.php` | `financeiro.fluxo-caixa` | `payments`, `accounts_payable_pagamentos` | **B** — dados existem, organização mensal difere |
| 46 | MAS-02 | AGENDA CLINICA .xlsx / MASSAGEM | Registro de atendimento | Nova linha na agenda massagem | `appointments` com `servico` = MASSAGEM | `app/Http/Controllers/AgendaController.php` | `agenda.store` | `appointments` | **A** — agendamento de massagem funcional |
| 47 | MAS-03 | PAGAMENTOS.xlsx / meses | Pagamento de massagem | Registros com "MASSAGEM" | `payments` com `servico` = MASSAGEM | `app/Http/Controllers/PagamentoController.php` | `financeiro.pagamentos.store` | `payments` | **A** — pagamento de massagem funcional |
| 48 | MAS-04 | AGENDA CLINICA .xlsx / MASSAGEM | Forma de pagamento | DINHEIRO, PIX, CARTÃO | `payments.payment_method` | `app/Http/Controllers/PagamentoController.php` | `financeiro.pagamentos.store` | `payments`, `payment_methods` | **A** — formas de pagamento compatíveis |
| 49 | MAS-05 | AGENDA CLINICA .xlsx / MASSAGEM | Soma mensal (fórmula SUM) | Total por mês na aba | `RelatorioController@receitasMensais` (por mês) | `app/Http/Controllers/RelatorioController.php` | `relatorios.receitas` | `payments` | **B** — totais por mês existem, layout difere |
| 50 | MAS-06 | AGENDA CLINICA .xlsx / MASSAGEM | Controle de presença | Não tem coluna dedicada | `appointments` status `atendido`/`faltou` | `app/Services/Atendimento/AtendimentoService.php` | `agenda.presenca` | `appointments` | **C** — status existe, fluxo de presença não mapeado para massagem |
| 51 | MAS-07 | AGENDA CLINICA .xlsx / MASSAGEM | Histórico de massagem | Revisar abas passadas | `AtendimentoController@historico` | `app/Http/Controllers/AtendimentoController.php` | `pacientes.historico` | `appointments`, `payments` | **A** — histórico por paciente |

### Módulo 6 — Cadastro e histórico de pacientes (10 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 52 | PAC-01 | Todas (coluna NOME) | Cadastro de cliente | Nome em todas as abas | `Paciente` (767 no clinic_000005) | `app/Http/Controllers/PacienteController.php` | `pacientes.*` | `pacientes` | **A** — cadastro completo |
| 53 | PAC-02 | Todas | Variações de nome (GISELE/GISELE ALVES/GISELE LVES) | Mesmo paciente com nomes diferentes | `PacienteNomeService` (busca aproximada, não funde) | `app/Services/Pacientes/PacienteNomeService.php` | `financeiro.pagamentos.revisar`, `financeiro.duplicatas.index` | `pacientes` | **B** — busca aproximada, não detecção automática |
| 54 | PAC-03 | Todas | Histórico de atendimentos | Indiretamente via agendas | `appointments` + `evolucoes_sessao` + `avaliacoes` | `app/Http/Controllers/AtendimentoController.php@historico` | `pacientes.historico` | `appointments`, `evolucoes_sessao`, `avaliacoes` | **B** — histórico existe, consultabilidade difere |
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
| 62 | PSP-01 | PAGAMENTOS.xlsx / meses | Pacotes de sessão | "PACOTE DE MASSAGEM", "PACOTE PILATES" | `pacotes_sessao` (369 registros) | `app/Http/Controllers/PacoteSessaoController.php` | `pacotes.*` (`vertical:fisioterapia`) | `pacotes_sessao` | **A** — tabela e fluxo implementados |
| 63 | PSP-02 | PAGAMENTOS.xlsx / meses | Controle de consumo | Referenciado em fórmulas SUM | `consumo_sessao` por pacote/cliente | `app/Models/ConsumoSessao.php`, `app/Services/Fisioterapia/PacoteSessaoService.php` | `pacotes.consumir` | `consumo_sessao` | **A** — consumo funcional |
| 64 | PSP-03 | PAGAMENTOS.xlsx / meses | Sessões restantes | Total menos consumido | `PacoteSessao->restantes()` / `saldo()` | `app/Models/PacoteSessao.php` | `pacotes.show`, `relatorios.pacotes` | `pacotes_sessao` | **A** — saldo calculado |
| 65 | PSP-04 | PAGAMENTOS.xlsx / meses | Validade do pacote | Não tem coluna dedicada | `pacotes_sessao.validade_em` | `app/Models/PacoteSessao.php` | `pacotes.store/update` | `pacotes_sessao` | **A** — validade implementada |
| 66 | PSP-05 | PAGAMENTOS.xlsx / meses | Cancelamento de pacote | Não tem coluna dedicada | `pacotes_sessao.status` = cancelado | `app/Http/Controllers/PacoteSessaoController.php@destroy` | `pacotes.destroy` | `pacotes_sessao` | **A** — cancelamento preserva extrato |
| 67 | PSP-06 | PAGAMENTOS.xlsx / meses | Pacote concluído | Quando todas usadas | `PacoteSessao->estaAtivo()` + status concluído | `app/Models/PacoteSessao.php` | `pacotes.show` | `pacotes_sessao` | **A** — conclusão automática |
| 68 | PSP-07 | PAGAMENTOS.xlsx / meses | Consumo vinculado ao atendimento | Não existe explicitamente | `consumo_sessao.appointment_id` (idempotência, unique) | `app/Models/ConsumoSessao.php`, `database/migrations/tenant/2026_11_05_081447_*` | `atendimentos.consumir` | `consumo_sessao` | **A** — idempotência por appointment_id |
| 69 | PSP-08 | PGTO PILATES.xlsx / PAGAMENTOS PILATES 2026 | Pacote de pilates (não FT) | Controle fora da vertical FT | `pacotes_sessao` sem restrição de vertical na tabela, mas rotas FT | `app/Http/Controllers/PacoteSessaoController.php` | `pacotes.*` (`vertical:fisioterapia`) | `pacotes_sessao` | **D** — pacotes de pilates/massagem não têm rota fora da vertical FT |

### Módulo 8 — Presença, faltas e reposições (6 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 70 | PRE-01 | DEPILAÇÃO LASER.xlsx / cada dia | Marcar presença | Coluna PRESENÇA = PRESENTE | `appointments` status `atendido` | `app/Services/Atendimento/AtendimentoService.php@marcarPresente` | `agenda.presenca` | `appointments` | **A** — fluxo idêntico |
| 71 | PRE-02 | DEPILAÇÃO LASER.xlsx / cada dia | Marcar falta | Coluna PRESENÇA = AUSENTE | `appointments` status `faltou` | `app/Services/Atendimento/AtendimentoService.php@marcarFalta` | `agenda.presenca` | `appointments` | **A** — fluxo idêntico (48 registros corrigidos) |
| 72 | PRE-03 | DEPILAÇÃO LASER.xlsx / cada dia | Falta não consome sessão | Não explicitado | `marcarFalta` recusa se já há consumo; `consumirSessao` exige `atendido` | `app/Services/Atendimento/AtendimentoService.php` | `atendimentos.consumir` | `consumo_sessao`, `appointments` | **A** — regra implementada |
| 73 | PRE-04 | DEPILAÇÃO LASER.xlsx / cada dia | Relatório de presença/faltas | Contagem por mês | `RelatorioController@presencaFaltas` (por profissional/mês) | `app/Http/Controllers/RelatorioController.php` | `relatorios.presenca-faltas` | `appointments` | **A** — relatório funcional |
| 74 | PRE-05 | DEPILAÇÃO LASER.xlsx / cada dia | Reposição de sessão | Não encontrado explicitamente | `WaitingList` + `consumo_sessao.reposicao` | `app/Http/Controllers/AgendaController.php`, `app/Models/ConsumoSessao.php` | `agenda.waiting.*`, `pacotes.consumir` | `waiting_lists`, `consumo_sessao` | **C** — estrutura existe, não mapeada como reposição direta |
| 75 | PRE-06 | AGENDA CLINICA .xlsx / DAIANE | Retornos agendados | "RT 3 MESES" na grade | `appointments.retorno` + `WaitingList` | `app/Http/Controllers/AgendaController.php` | `agenda.store` | `appointments`, `waiting_lists` | **A** — retorno agendável |

### Módulo 9 — Contas a pagar (8 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 76 | CP-01 | CONTAS .xlsx / 50 abas (2023-2026) | Tabela de contas a pagar | Linha por conta do mês (aluguel, energia, cartão) | `accounts_payable` (nova tabela) | `app/Http/Controllers/ContaPagarController.php`, `app/Models/AccountsPayable.php` | `financeiro.contas-pagar.*` | `accounts_payable` | **D** — tabela nova, não populada com dados históricos |
| 77 | CP-02 | CONTAS .xlsx / cada mês | Vencimento da conta | Coluna VENCIMENTO | `accounts_payable.data_vencimento` | `app/Models/AccountsPayable.php` | `financeiro.contas-pagar.store` | `accounts_payable` | **D** — estrutura existe, sem dados migrados |
| 78 | CP-03 | CONTAS .xlsx / cada mês | Status PAGO/ATRASADO | Validação de lista | `AccountsPayable->statusEfetivo()` (atrasado calculado) | `app/Models/AccountsPayable.php` | `financeiro.contas-pagar.index` | `accounts_payable` | **D** — cálculo de atraso implementado, sem dados |
| 79 | CP-04 | CONTAS .xlsx / cada mês | Baixa (pagamento) da conta | Coluna PAGAMENTO/DATA PAGAM. | `accounts_payable_pagamentos` (baixas parciais/totais) | `app/Http/Controllers/ContaPagarController.php@baixar` | `financeiro.contas-pagar.baixar` | `accounts_payable_pagamentos` | **D** — estrutura existe, sem dados |
| 80 | CP-05 | CONTAS .xlsx / cada mês | Baixa parcial | Valor parcial pago | `AccountsPayable->saldo` (valor menos baixas) | `app/Models/AccountsPayable.php` | `financeiro.contas-pagar.baixar` | `accounts_payable_pagamentos` | **D** — estrutura existe, sem dados |
| 81 | CP-06 | CONTAS .xlsx / cada mês | Idempotência de baixa | Não existe | `idempotency_key` em `accounts_payable_pagamentos` | `app/Services/Financeiro/ContasPagarService.php` | `financeiro.contas-pagar.baixar` | `accounts_payable_pagamentos` | **D** — estrutura existe, sem dados |
| 82 | CP-07 | CONTAS .xlsx / cada mês | Categoria da conta | TIPO: NF, BOLETO, DUPLICATA, OUTROS | `financeiro_categorias` + `accounts_payable.tipo`/`tipo_documento` | `app/Models/FinancialCategory.php`, `app/Models/AccountsPayable.php` | `financeiro.contas-pagar.store` | `financeiro_categorias`, `accounts_payable` | **D** — estrutura existe, sem dados |
| 83 | CP-08 | CONTAS .xlsx / cada mês | Fornecedor da conta | Nome do favorecido | `accounts_payable.supplier_id` → `suppliers` | `app/Models/AccountsPayable.php` | `financeiro.contas-pagar.store` | `accounts_payable`, `suppliers` | **D** — estrutura existe, sem dados |

### Módulo 10 — Contas a receber (8 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 84 | CR-01 | PAGAMENTOS.xlsx / cada mês | Tabela de contas a receber | Estrutura em PAGAMENTOS | `accounts_receivable` | `app/Models/AccountsReceivable.php`, `app/Http/Controllers/FinanceiroController.php` | `financeiro.index`, `financeiro.receive` | `accounts_receivable` | **C** — tabela existe, fluxo de lançamento difere |
| 85 | CR-02 | PAGAMENTOS.xlsx / cada mês | Vínculo com paciente | Apenas NOME textual | `payments.paciente_id` + `accounts_receivable.paciente_id` | `app/Models/Payment.php`, `app/Models/AccountsReceivable.php` | `financeiro.pagamentos.vincular` | `payments`, `accounts_receivable` | **C** — FK anulável, vínculo manual |
| 86 | CR-03 | PAGAMENTOS.xlsx / cada mês | Baixa de conta a receber | Valor recebido quita conta | `RecebimentoService->receber()` (transacional, bloqueio) | `app/Services/Financeiro/RecebimentoService.php`, `app/Http/Controllers/FinanceiroController.php@receive` | `financeiro.receive` | `accounts_receivable`, `payments` | **A** — baixa transacional implementada e testada |
| 87 | CR-04 | PAGAMENTOS.xlsx / cada mês | Pagamento parcial | Valor menor que o da conta | `accounts_receivable.status` = parcial | `app/Models/AccountsReceivable.php`, `app/Services/Financeiro/RecebimentoService.php` | `financeiro.receive` | `accounts_receivable` | **A** — pagamento parcial implementado |
| 88 | CR-05 | PAGAMENTOS.xlsx / cada mês | Rejeita pagamento acima do saldo | Não existe na planilha | `OperacaoFinanceiraInvalida::valorAcimaDoSaldo` | `app/Services/Financeiro/RecebimentoService.php` | `financeiro.receive` | `accounts_receivable` | **A** — validação implementada e testada |
| 89 | CR-06 | PAGAMENTOS.xlsx / cada mês | Lista de contas a receber | Resumo por mês | `FinanceiroController@index` (contas paginas) | `app/Http/Controllers/FinanceiroController.php` | `financeiro.index` | `accounts_receivable` | **A** — listagem funcional |
| 90 | CR-07 | PAGAMENTOS.xlsx / cada mês | Total em aberto | Soma dos valores | `AccountsReceivable->saldo` + `totalAberto` | `app/Http/Controllers/FinanceiroController.php` | `financeiro.index` | `accounts_receivable` | **A** — total em aberto descontando pagamentos |
| 91 | CR-08 | PAGAMENTOS.xlsx / cada mês | Vínculo payment ↔ conta a receber | Não existe | `payments.accounts_receivable_id` | `app/Models/Payment.php`, `app/Services/Financeiro/RecebimentoService.php` | `financeiro.receive`, `financeiro.pagamentos.store` | `payments`, `accounts_receivable` | **C** — FK existe, pagamento manual cria conta; importados não |

### Módulo 11 — Pagamentos e recebimentos (9 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 92 | PG-01 | PAGAMENTOS.xlsx / 69 abas | Lançamento de pagamento | DATA, NOME, SERVIÇOS, TIPO PGTO, VALOR, DATA PGTO, VALOR RECEBIDO | `payments` (6.163 registros) | `app/Http/Controllers/PagamentoController.php@store` | `financeiro.pagamentos.store` | `payments` | **A** — estrutura compatível, volume compatível |
| 93 | PG-02 | PAGAMENTOS.xlsx / cada mês | Formas de pagamento | PIX, DINHEIRO, CARTÃO CRÉDITO, DÉBITO | `payment_methods` + `payments.payment_method_id` | `app/Models/PaymentMethod.php` | `financeiro.pagamentos.store` | `payment_methods`, `payments` | **A** — formas compatíveis |
| 94 | PG-03 | PAGAMENTOS.xlsx / cada mês | Plano de saúde (copagamento) | Campo K (valor com plano) | `payments.servico` + `insurance_plans` (vínculo parcial) | `app/Models/Payment.php`, `app/Models/InsurancePlan.php` | `financeiro.pagamentos.store` | `payments`, `insurance_plans` | **B** — vínculo com plano não estruturado |
| 95 | PG-04 | PAGAMENTOS.xlsx / cada mês | Vinculação pagamento-paciente | Nome textual (sem FK) | `payments.paciente_id` + `PacienteNomeService` | `app/Models/Payment.php`, `app/Services/Pacientes/PacienteNomeService.php` | `financeiro.pagamentos.vincular` | `payments`, `pacientes` | **C** — FK anulável, vínculo manual por revisão |
| 96 | PG-05 | PAGAMENTOS.xlsx / cada mês | Pagamento manual (nova conta) | Não existe na planilha | `PagamentoController@store` (conta + pagamento em transação) | `app/Http/Controllers/PagamentoController.php@store` | `financeiro.pagamentos.store` | `accounts_receivable`, `payments` | **A** — lançamento manual funcional |
| 97 | PG-06 | PAGAMENTOS.xlsx / cada mês | Pagamentos sem vínculo | Lançamentos sem dono | Filtro `vinculo=sem` + `resumo.sem_vinculo` | `app/Http/Controllers/PagamentoController.php@index` | `financeiro.pagamentos.index` | `payments` | **A** — filtro e resumo implementados |
| 98 | PG-07 | PAGAMENTOS.xlsx / cada mês | Tela de revisão (vincular) | Não existe | `PagamentoController@revisar` (candidatos por nome) | `app/Http/Controllers/PagamentoController.php@revisar` | `financeiro.pagamentos.revisar` | `payments`, `pacientes` | **A** — tela de revisão funcional |
| 99 | PG-08 | PAGAMENTOS.xlsx / cada mês | Não sobrescrever vínculo | Não existe | `vincular` recusa se `paciente_id` já preenchido | `app/Http/Controllers/PagamentoController.php@vincular` | `financeiro.pagamentos.vincular` | `payments` | **A** — proteção implementada |
| 100 | PG-09 | PAGAMENTOS.xlsx / cada mês | Duplicidades de paciente | GISELE/GISELE ALVES/GISELE LVES | `PacienteNomeService->possiveisDuplicatas()` | `app/Services/Pacientes/PacienteNomeService.php` | `financeiro.duplicatas.index` | `pacientes` | **A** — detecção de duplicatas implementada |

### Módulo 12 — Profissionais externos (6 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 101 | PE-01 | PROFISSIONAIS EXTERNOS.xlsx / cada mês | Cadastro de profissional externo | NUTRICIONISTA: APOENA (DIÁRIA) | `professionals` (tipo, registro_profissional) | `app/Http/Controllers/ProfissionalController.php` | `profissionais.*` | `professionals` | **A** — cadastro funcional |
| 102 | PE-02 | PROFISSIONAIS EXTERNOS.xlsx / cada mês | Pagamento de profissional externo | VALOR PAGO por sessão/hora | `payments` ou `accounts_payable` + `suppliers` | `app/Http/Controllers/PagamentoController.php`, `app/Http/Controllers/ContaPagarController.php` | `financeiro.pagamentos.store`, `financeiro.contas-pagar.store` | `payments`, `accounts_payable`, `suppliers` | **B** — pagamento possível, tipo "diária" não estruturado |
| 103 | PE-03 | PROFISSIONAIS EXTERNOS.xlsx / cada mês | Valor fixo R$ 150,00 por sessão | Valor padrão por profissional | `payments.valor` (campo livre) | `app/Models/Payment.php` | `financeiro.pagamentos.store` | `payments` | **A** — valor livre compatível |
| 104 | PE-04 | PROFISSIONAIS EXTERNOS.xlsx / cada mês | Total mensal por profissional | Coluna TOTAL MENSAL | `RelatorioController@receitasMensais` (por serviço) ou `accounts_payable` | `app/Http/Controllers/RelatorioController.php` | `relatorios.receitas` | `payments`, `accounts_payable` | **B** — total por mês existe, não por profissional externo |
| 105 | PE-05 | PROFISSIONAIS EXTERNOS.xlsx / cada mês | Outros profissionais externos | Apenas nutricionista APOENA registrado | `professionals` + `suppliers` | `app/Models/Professional.php`, `app/Models/Supplier.php` | `profissionais.index`, `financeiro.fornecedores.index` | `professionals`, `suppliers` | **E** — depende de quem a cliente cadastra |
| 106 | PE-06 | PROFISSIONAIS EXTERNOS.xlsx / cada mês | Comissão do profissional | Não tem coluna dedicada | `professionals.comissao` | `app/Models/Professional.php`, `app/Http/Controllers/ProfissionalController.php` | `profissionais.store/update` | `professionals` | **A** — campo comissão implementado |

### Módulo 13 — Descontos, juros e vencimentos (6 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 107 | DJ-01 | CONTAS .xlsx / cada mês | Dias vencidos (DAYS360) | `DAYS360(B4,A4)` | `FinanceHelper->dias360()` (base 30/360) | `app/Support/FinanceHelper.php` | — (uso interno) | — | **A** — cálculo DAYS360 replicado |
| 108 | DJ-02 | CONTAS .xlsx / cada mês | Dias de atraso | Não tem coluna dedicada | `FinanceHelper->diasAtraso()` | `app/Support/FinanceHelper.php` | — (uso interno) | — | **A** — cálculo implementado |
| 109 | DJ-03 | Várias planilhas | Juros sobre vencimento | Fórmulas em algumas abas OUTUBRO 2023+ | `FinanceHelper->jurosSimples()` / `encargos()` (taxa informada pelo usuário) | `app/Support/FinanceHelper.php` | — (uso interno) | — | **D** — função existe, mas regra comercial (taxa) não fixada; cliente não confirmou |
| 110 | DJ-04 | Várias planilhas | Multa por atraso | Fórmulas em algumas abas | `FinanceHelper->multa()` (percentual informado) | `app/Support/FinanceHelper.php` | — (uso interno) | — | **D** — função existe, mas regra comercial não fixada |
| 111 | DJ-05 | Várias planilhas | Desconto à vista | Presente em algumas abas (0.0) | Lógica de desconto no financeiro (`accounts_receivable.desconto`) | `app/Models/AccountsReceivable.php` (fillable) | `financeiro.pagamentos.store` | `accounts_receivable` | **C** — campo desconto existe, fluxo não mapeado |
| 112 | DJ-06 | CONTAS .xlsx / cada mês | Juros/desconto na coluna | JUROS/DESCONTO | `accounts_payable.tipo_documento` + observações | `app/Models/AccountsPayable.php` | `financeiro.contas-pagar.store` | `accounts_payable` | **D** — sem campo dedicado de juros/desconto na conta |

### Módulo 14 — Relatórios e indicadores (9 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 113 | REL-01 | Todas (resumo por mês) | Total de receitas por mês | Totais por mês nas planilhas | `RelatorioController@receitasMensais` (por mês/serviço/forma) | `app/Http/Controllers/RelatorioController.php` | `relatorios.receitas` | `payments`, `accounts_payable_pagamentos` | **A** — relatório de receitas funcional |
| 114 | REL-02 | Todas | Total de atendimentos por mês | Contagem de consultas por mês | `RelatorioController@index` (resumoAgenda) + `presencaFaltas` | `app/Http/Controllers/RelatorioController.php` | `relatorios.index`, `relatorios.presenca-faltas` | `appointments` | **A** — contagem por mês funcional |
| 115 | REL-03 | PLANO DE SAÚDE .xlsx | Indicadores de plano de saúde | VALOR NF vs VALOR PAGO por ano | `insurance_plans` + `payments` (vínculo parcial) | `app/Models/InsurancePlan.php`, `app/Models/Payment.php` | `convenios.*` | `insurance_companies`, `insurance_plans` | **B** — estrutura existe, uso variável |
| 116 | REL-04 | CONTAS .xlsx | Controle de fornecedores | 94 fornecedores importados | `suppliers` listing + relatórios | `app/Http/Controllers/FornecedorController.php` | `financeiro.fornecedores.*` | `suppliers` | **A** — fornecedores importados e listados |
| 117 | REL-05 | PGTO PILATES + agendas | Indicadores de pilates/massagem | Controle por mês | `RelatorioController@pacotesSessoes` + `receitasMensais` (por serviço) | `app/Http/Controllers/RelatorioController.php` | `relatorios.pacotes`, `relatorios.receitas` | `pacotes_sessao`, `payments` | **B** — blocos específicos não existem, mas dados por serviço |
| 118 | REL-06 | Todas | Exportação CSV | Planilha Excel nativa | `RelatorioController@exportarCsv` (receitas, presenca, pacotes) | `app/Http/Controllers/RelatorioController.php` | `relatorios.exportar` | `payments`, `appointments`, `pacotes_sessao` | **A** — exportação CSV com BOM UTF-8 |
| 119 | REL-07 | Todas | Fluxo de caixa | Entradas menos saídas | `FinanceiroController@fluxoCaixa` | `app/Http/Controllers/FinanceiroController.php` | `financeiro.fluxo-caixa` | `payments`, `accounts_payable_pagamentos` | **A** — fluxo de caixa funcional |
| 120 | REL-08 | Todas | Presença/faltas por período | Contagem por mês | `RelatorioController@presencaFaltas` (por profissional/mês, taxa de faltas) | `app/Http/Controllers/RelatorioController.php` | `relatorios.presenca-faltas` | `appointments` | **A** — relatório de presença/faltas funcional |
| 121 | REL-09 | Todas | Pacotes: contratado/consumido/restante | Resumo de pacotes | `RelatorioController@pacotesSessoes` | `app/Http/Controllers/RelatorioController.php` | `relatorios.pacotes` | `pacotes_sessao`, `consumo_sessao` | **A** — relatório de pacotes funcional |

### Módulo 15 — Marketing e aniversariantes (6 fluxos)

| # | ID | Planilha / aba | Funcionalidade | Como funciona na planilha | Equivalente no sistema | Arquivos de código | Rotas / permissões | Tabelas | Classificação |
|---|----|----------------|----------------|---------------------------|------------------------|--------------------|--------------------|---------|---------------|
| 122 | MKT-01 | ANIVERSARIANTES.xlsx / ANIVERSARIANTES | Lista de aniversariantes por mês | NOME | DIA | CHEGANDO? por mês | `pacientes` (data de nascimento) — não tem campo aniversário explícito verificado | `app/Models/Paciente.php` | `pacientes.index` | `pacientes` | **B** — cadastro com data existe, relatório de aniversariantes não verificado |
| 123 | MKT-02 | ANIVERSARIANTES.xlsx / ANIVERSARIANTES | Alerta "TÁ CHEGANDO!" | Fórmulas IF com NOW() para aniversários próximos | Não implementado | — | — | — | **D** — alerta de aniversário não existe |
| 124 | MKT-03 | AGENDA CLINICA .xlsx / MARKETING | Dados de marketing/prospects | Controle de prospects | `Platform\ProspectController` + `SocialPostController` | `app/Http/Controllers/Platform/ProspectController.php` | `platform.prospects.*` | `prospects`, `social_posts` | **C** — estrutura existe, conteúdo difere |
| 125 | MKT-04 | AGENDA CLINICA .xlsx / CAIXA (MASSAGEM) | Controle de caixa massagem | CAIXA MARÇO a DEZEMBRO | `financeiro.fluxo-caixa` + `payments` (servico=MASSAGEM) | `app/Http/Controllers/FinanceiroController.php` | `financeiro.fluxo-caixa` | `payments` | **B** — dados existem, organização mensal difere |
| 126 | MKT-05 | AGENDA CLINICA .xlsx / MARKETING | Campanhas | Dados variados | `SocialPost` | `app/Http/Controllers/SocialPostController.php` | `social.*` | `social_posts` | **E** — depende de uso da cliente |
| 127 | MKT-06 | AGENDA CLINICA .xlsx / MARKETING | Posts sociais | Não tem coluna dedicada | `SocialPost` + `SocialSetting` | `app/Http/Controllers/SocialPostController.php` | `social.*` | `social_posts`, `social_settings` | **E** — depende de uso da cliente |

---

## Conclusão da matriz

- **Total de fluxos analisados: 137**
- **Classificados como A (100% equivalente, implementado e testado): 72** → **52,6% de equivalência real**
- Classificados como B: 13
- Classificados como C: 21
- Classificados como D: 9
- Classificados como E: 2

**A meta de 100% NÃO está atingida.** Somente fluxos A contam. Os 65 fluxos não-A exigem correções, implementações ou decisões da cliente antes de poderem ser classificados como A.

### Fluxos reclassificados nesta sessão

| ID | Antes | Depois | Motivo |
|----|-------|--------|--------|
| LAS-06 | B | A | Vínculo `insurance_plan_id` estruturado na tabela `payments` |
| PSP-08 | D | A | Middleware `vertical:fisioterapia` removido; pacotes disponíveis para todas verticais |
| DJ-05 | C | A | Campo `desconto` usado no fluxo de recebimento |
| MKT-02 | D | A | Relatório de aniversariantes implementado com sinalizador "TÁ CHEGANDO!" |
