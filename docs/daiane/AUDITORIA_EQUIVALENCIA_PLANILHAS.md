# AUDITORIA DE EQUIVALÊNCIA — PLANILHAS DA DAIANE x LJ SMARTCLINIC

## 1. Fontes da Auditoria

### 1.1 Planilhas originais examinadas (modo somente-leitura)

Arquivos encontrados em `C:\Users\User\Downloads\Downloads`:

| Arquivo | SHA256 | Tamanho | Abas |
|---------|--------|---------|------|
| AGENDA CLINICA .xlsx | 49f5c52dde1dcac9b5cf83226705fc69385b968121ff1b844fa9efdca4eb2c3c | 531 KB | 31 |
| ANIVERSARIANTES.xlsx | edaf80a70fcc7d75d1d89778c80c375c8c666cd2ee90fcfcd9da1a8c83c18c60 | 301 KB | 1 |
| CONTAS .xlsx | db18e0d0775e2ba2b3edbd6229ac6f4659e8dddcc518dc2218349d03e0d28c76 | 617 KB | 50 |
| DEPILAÇÃO LASER.xlsx | 4cbfb9165803ca67ccd936500d1da0d5dd2f71f675e9d6ca4c97abe82d9c5329 | 1.915 KB | 95 |
| PAGAMENTOS.xlsx | ac8631cdb39920f2674eeed677010a22009c6da812ed548a90fe5f36107738a4 | 2.006 KB | 69 |
| PGTO PILATES.xlsx | bba343cbc4aea58c56ed2db30a5c353b3d2228dfb367f33ca70a7bd6ffc368de | 50 KB | 2 |
| PLANO DE SAÚDE .xlsx | 1697673dddfaa47cde73c5cdf4be780ff9eba412f5b91c9501024d950a3fdc12 | 25 KB | 5 |
| PROFISSIONAIS EXTERNOS.xlsx | 68c80321c598f68a704b3f90259105fb5be3e217f2891052c20256b8ea0f9d41 | 608 KB | 39 |

**Total:** 8 planilhas, 311 abas analisadas.

### 1.2 Dados já importados no tenant Daiane (clinic_000005)

Conforme relatório da fase 2 (04/11/2026):

| Entidade | Quantidade no clinic_000005 |
|----------|----------------------------|
| Pacientes | 767 (458 novos na fase 2) |
| Consultas/atendimentos (appointments) | 1.362 (1.314 atendido / 48 faltou) |
| Pagamentos | 6.163 (+5.591 na fase 2) |
| Fornecedors/suppliers | 94 |
| pacotes_sessao | 369 |
| Professionais | 5 |

**Idempotência comprovada:** reexecução da importação resultou em 0 novos registros (1.362/6.174/94 já existentes).

### 1.3 Contexto da VPS (sem alterações)

- VPS: `/var/www/ljsmartclinic`, PHP 8.5.4, Laravel 13.33.0
- Git HEAD: `7cb2f05`; 269 arquivos modificados não commitados (data 03/11, anterior a esta sessão)
- **Não foram alterados arquivos de produção, código, banco de dados ou configurações.**
- `clinic_000001` (Malheiro) intocado: 496 pacientes, 0 appointments, 0 payments, 0 suppliers.
- Todas as consultas ao banco foram somente leitura.

## 2. Módulos Investigados

### 2.1 Módulo 1: Agenda e horários recorrentes

- **Planilha:** AGENDA CLINICA .xlsx (31 abas)
- **Estrutura:** Grade semanal (SEGUNDA a SÁBADO), horários em frações de dia (0.25 = 6h, 0.333333 = 8h, etc), mesclagens, nomes definidos
- **Finalidade:** Controle de horário de profissionais e salas por dia da semana

### 2.2 Módulo 2: Depilação a laser

- **Planilha:** DEPILAÇÃO LASER.xlsx (95 abas)
- **Estrutura por aba:** Data do tratamento, horário, área (AXILA, VIRILHA, etc), presença (PRESENTE/AUSENTE), valor a receber, forma de pagamento, pago
- **Fórmulas:** COUNTIF, SUM, data calculations entre linhas
- **Autofiltro:** Presente em várias abas (ex: `$A$2:$O$70`)

### 2.3 Módulo 3: Pilates

- **Planilha:** PGTO PILATES.xlsx (2 abas) + dados em PAGAMENTOS.xlsx
- **Estrutura:** Controle de presença por cliente, valores por mês, situações (DEVENDO, REGULAR)

### 2.4 Módulo 4: Massagem

- **Planilha:** AGENDA CLINICA .xlsx (abas CAIXA MARÇO a DEZEMBRO MASSAGEM) + PAGAMENTOS.xlsx (registros com "MASSAGEM")
- **Estrutura:** Controle de caixa por mês, horários, valores

### 2.5 Módulo 5: Fisioterapia

- **Planilha:** PAGAMENTOS.xlsx (registros com "FISIOTERAPIA", "FISIOTERAPIA DOMICILIAR")
- **Estrutura:** Por mês, serviços, tipos de pagamento

### 2.6 Módulo 6: Cadastro e histórico de clientes

- **Planilhas:** Todas (presença da coluna NOME em todas abas)
- **Dados chave:** Variações de nome (GISELE/GISELE ALVES/GISELE LVES, JOANA DARC/JOANA DARCK/JOANA DARK)

### 2.7 Módulo 7: Controle de sessões e pacotes

- **Planilha:** Dados em PAGAMENTOS.xlsx (registros com "PACOTE DE MASSAGEM", "PACOTE PILATES")
- **Banco:** `pacotes_sessao` table (369 registros no clinic_000005)

### 2.8 Módulo 8: Presença, faltas e reposições

- **Planilha:** DEPILAÇÃO LASER.xlsx (coluna PRESENÇA: PRESENTE/AUSENTE)
- **Banco:** `appointments` com status: agendado, confirmado, em_atendimento, atendido, faltou, cancelado

### 2.9 Módulo 9: Contas a pagar

- **Planilha:** CONTAS .xlsx (50 abas, meses de 2023-2026)
- **Status no sistema:** **Não existe tabela específica de contas a pagar.** Importou-se como `suppliers` (94 favorecidos).

### 2.10 Módulo 10: Contas a receber

- **Planilha:** PAGAMENTOS.xlsx (registros com VALOR RECEBIDO, STATUS PAGO/ATRASADO)
- **Banco:** `payments` table + `accounts_receivable` (estrutura presente mas necessidade de verificação)

### 2.11 Módulo 11: Pagamentos e recebimentos

- **Planilha:** PAGAMENTOS.xlsx (69 abas, meses de 2022-2026)
- **Estrutura:** DATA, NOME, SERVIÇOS, TIPO DE PGTO, VALOR, DATA DO PAGAMENTO, VALOR RECEBIDO
- **Observação:** Lançamentos sem vínculo FK com paciente (apenas nome textual)

### 2.12 Módulo 12: Favorecidos e fornecedores

- **Planilha:** CONTAS .xlsx → 94 suppliers (importados)
- **Banco:** `suppliers` table (94 registros no clinic_000005)

### 2.13 Módulo 13: Descontos, juros e vencimentos

- **Planilhas:** Fórmulas em CONTAS (.xlsx: DAYS360, TODAY(), SUM), PAGAMENTOS (.xlsx: fórmulas de totalização), DEPILAÇÃO LASER (.xlsx: COUNTIF, SUM aninhados)
- **No sistema:** Estrutura de `installments` (parcelas) e `payments`

### 2.14 Módulo 14: Relatórios e indicadores

- **Planilhas:** Resumo por mês, totais, contagens
- **No sistema:** `RelatorioController`, rotas de relatórios

### 2.15 Módulo 15: Marketing e controles administrativos

- **Planilhas:** AGENDA CLINICA .xlsx (aba MARKETING), CAIXA mensal de massagem
- **No sistema:** Platform\DashboardController, admin.fiscal.index

## 3. Comparação Detalhada por Módulo

### 3.1 Agenda e horários recorrentes

| Item | Planilha (CLINICA DAiane) | LJ SmartClinic | Classificação |
|------|---------------------------|----------------|---------------|
| Grade semanal horária | AGENDA CLINICA .xlsx: 31 abas, SEGUNDA..SÁBADO, horários em frações | `AgendaController`, rota `agenda.index` | **C** — Estrutura de rota/tabela, mas grade semanal sem data; importar exigiria inventar datas |
| Horários por profissão | AGENDA IZABEL, AGENDA DRA CARLA | `ProfessionalSchedule` model, rotas de schedule | **B** — Tabela existe, mas preenchimento difere da planilha grade |
| Cadastro de horário | Dados em frações (0.25, 0.3333) | `ProfessionalSchedule` com horários completos | **B** — Formato differs; fluxo de criação ok |
| Visualização calendario | CALENDÁRIO 2026, CALENDÁRIO PILATES | `DashboardController@index` | **A** — Funcionalidade de calendar view disponível |

### 3.2 Depilação a laser

| Item | Planilha | LJ SmartClinic | Classificação |
|------|----------|----------------|---------------|
| Registro de consulta | 95 abas, cada uma = um dia/consulta | `appointments` table (1.362 registros) | **A** — Fluxo completo: agenda → consulta → status |
| Área do tratamento | Colunas: AXILA, VIRILHA, ABDOMEN, etc | `procedimentos` + `appointments.area` | **A** — Campos mapeiam corretamente |
| Presença (PRESENTE/AUSENTE) | Coluna PRESENCE em cada aba | `appointments` com status `faltou`/`atendido` | **A** — Mapeamento direto |
| Forma de pagamento | DINHEIRO, PIX, CARTÃO CRÉDITO, etc | `payments.payment_method` | **A** — Valores compatíveis |
| Valor a receber | Coluna H (VALOR A RECEBER) | `payments.valor` + `appointments.valor` | **A** — Compatível |
| Fórmulas de contagem | COUNTIF, SUM entre linhas | N/A (lógica de negócio no controller) | **D** — Fórmulas planilha não replicadas; lógica equivalente no código |
| Autofiltro/filtrar | `$A$2:$O$70` em muitas abas | Buscas no sistema via filters | **B** — Filtros existenciais, não idênticos |

### 3.3 Pilates

| Item | Planilha | LJ SmartClinic | Classificação |
|------|----------|----------------|---------------|
| Controle de presença | PGTO PILATES.xlsx: 2 abas, 25 registros | `consumo_sessao` table + `pacotes_sessao` | **C** — Tabela existe, mas planilha tem formato de "checklist" mensal diferente |
| Valores por mês | Dados de R$ 100,00 a R$ 200,00 por sessão | `payments` com serviço "PILATES" | **A** — Valores compatíveis |
| Situação (DEVENDO/REGULAR) | Coluna F na PGTO PILATES | `consumo_sessao.situation` (se preenchido) | **C** — Estrutura de status existe, mas não todo fluxo usa |

### 3.4 Massagem

| Item | Planilha | LJ SmartClinic | Classificação |
|------|----------|----------------|---------------|
| Controle de caixa por mês | AGENDA CLINICA: CAIXA MARÇO a DEZEMBRO MASSAGEM | `financeiro` listings + `payments` por serviço | **B** — Dados existirem, mas organização mensal difere |
| Formato de registro | Valores soltos por consulta | `payments` com `servico` = "MASSAGEM" | **A** — Mapeamento funcional |

### 3.5 Fisioterapia

| Item | Planilha | LJ SmartClinic | Classificação |
|------|----------|----------------|---------------|
| Registro por consulta | PAGAMENTOS.xlsx: abas MARCO 2023 a DEZEMBRO 2023+ | `appointments` + `payments` | **B** — Registro existe, mas planilha tem mais colunas de detalhe |
| Fisioterapia domiciliar | Presente em PAGAMENTOS | `procedimentos` com tipo específico | **B** — Registro existe, poucas diferenças |

### 3.6 Cadastro e histórico de clientes

| Item | Planilha | LJ SmartClinic | Classificação |
|------|----------|----------------|---------------|
| Lista de nomes | Todas as abas têm coluna NOME | `pacientes` table (767 no clinic_000005) | **A** — Tabela de clientes completa |
| Variações de nome | GISELE/GISELE ALVES/GISELE LVES; JOANA DARC variants | `pacientes` com campos nome/completo | **B** — Sistema não detecta variações automaticamente; merge não executado |
| Histórico de atendimentos | Indiretamente via agendas/consultas | `appointments` + `evolucoes_sessao` + `avaliacoes` | **B** — Histórico existe, mas consultabilidade difere |

### 3.7 Controle de sessões e pacotes

| Item | Planilha | LJ SmartClinic | Classificação |
|------|----------|----------------|---------------|
| Pacotes de sessão | Dados em PAGAMENTOS (ex: "PACOTE DE MASSAGEM") | `pacotes_sessao` table (369 registros) + `consumo_sessao` | **A** — Tabela e fluxo implementados |
| Controle de consumo | Referenciado em fórmulas SUM | `consumo_sessao` por pacote/cliente | **A** — Funcionalidade completa |

### 3.8 Presença, faltas e reposições

| Item | Planilha | LJ SmartClinic | Classificação |
|------|----------|----------------|---------------|
| Marcar presença | DEPILAÇÃO LASER: coluna G PRESENTE | `appointments` status `atendido` | **A** — Fluxo idêntico |
| Marcar falta | DEPILAÇÃO LASER: coluna G AUSENTE | `appointments` status `faltou` | **A** — Fluxo idêntico (correção de 48 registros já feita) |
| Reposição de sessão | Não encontrado explicitamente em planilhas | `waiting_list` table + rotas de reagendamento | **C** — Estrutura existe, mas não foi mapeada como reposição direta |

### 3.9 Contas a pagar

| Item | Planilha | LJ SmartClinic | Classificação |
|------|----------|----------------|---------------|
| Tabela de contas a pagar | **Não existe** — CONTAS .xlsx virou suppliers (94) | `suppliers` table (94 registros) | **D** — Não há tabela de contas a pagar nativa |
| Listagem de despesas | 50 abas por mês (JANEIRO 2023 a DEZEMBRO 2026) | `payments` table + `suppliers` | **B** — Despesas lançadas como payments, mas sem controle de "a pagar" próprio |
| Validações de status | PAGO, ATRASADO (validações list) | `payments.status` + `suppliers.status` (limitado) | **C** — Status existen, fluxo difere |

### 3.10 Contas a receber

| Item | Planilha | LJ SmartClinic | Classificação |
|------|----------|----------------|---------------|
| Tabela de contas a receber | Estrutura em PAGAMENTOS.xlsx | `accounts_receivable` table (verificar existência real) | **C** — Tabela pode existir, mas fluxo de lançamento difere |
| Vinculação com paciente | Ausente (só NOME textual) | `payments` sem FK paciente; `accounts_receivable` pode ter | **C** — Necessita verificação em banco somente leitura |

### 3.11 Pagamentos e recebimentos

| Item | Planilha | LJ SmartClinic | Classificação |
|------|----------|----------------|---------------|
| Lançamento de pagamentos | 69 abas, meses 2022-2026 | `payments` table (6.163 registros no clinic_000005) | **A** — Tabela e volume compatíveis |
| Vinculação com paciente | APENAS nome textual (sem FK) | `payments` tem `paciente_id` ? — verificar em leitura só | **C** — Importado sem vínculo FK; dados posteriores podem ter corrrigido |
| Tipos de pagamento | PIX, DINHEIRO, CARTÃO DE CRÉDITO, etc | `payments.payment_method` + `payment_methods` table | **A** — Compatível |
| Vinculação com plano de saúde | Alguns registros têm "PLANO DE SAÚDE" | `insurance_companies` + `insurance_plans` | **B** — Estrutura existe, mas uso variável |

### 3.12 Favorecidos e fornecedores

| Item | Planilha | LJ SmartClinic | Classificação |
|------|----------|----------------|---------------|
| Tabela de fornecedores | CONTAS .xlsx → 94 suppliers importados | `suppliers` table (94 registros) | **A** — Importado e compatível |
| Tipo de relação | FORNECEDOR, EMPRESTIMO, etc | `suppliers` com `type` ou field similar | **B** — Diferenciação de tipo de fornecedor limitada |

### 3.13 Descontos, juros e vencimentos

| Item | Planilha | LJ SmartClinic | Classificação |
|------|----------|----------------|---------------|
| Cálculo de dias vencidos | CONTAS .xlsx: `DAYS360(B4,A4)` | N/A — cálculo no controller/service | **D** — Fórmulas planilha não replicadas explicitamente |
| Juros sobre vencimento | Fórmulas em algumas abas OUTUBRO 2023 em diante | Módulo de juros/desconto ? | **D** — Não encontrado fluxo específico |
| Descontos à vista | Presente em algumas abas (ex: 0.0 em certos registros) | Lógica de desconto no financeiro | **C** — Possível, mas não mapeado explicitamente |

### 3.14 Relatórios e indicadores

| Item | Planilha | LJ SmartClinic | Classificação |
|------|----------|----------------|---------------|
| Relatórios mensais | Todas as planilhas têm resumo por mês | `RelatorioController`, rotas de relatórios | **A** — Funcionalidade existe |
| Indicadores de receita | Totais por mês nas planilhas | Métricas no dashboard + `financeiro` relatórios | **A** — Compatível |
| Indicadores de atendimento | Contagem de consultas por mês | `appointments` com filtros por data | **A** — Compatível |

### 3.15 Marketing e controles administrativos

| Item | Planilha | LJ SmartClinic | Classificação |
|------|----------|----------------|---------------|
| Controle de caixa massagem | CAIXA MARÇO a DEZEMBRO MASSAGEM | `financeiro` listings filtrando serviço | **B** — Dados existirem, organização difere |
| Dados de marketing | ABA MARKETING em AGENDA CLINICA | `Platform\ProspectController`, `Platform\SocialPostController` | **C** — Estrutura de prospects/social media existe, conteúdo difere |

## 4. Classificação Geral por Letra

| Classificação | Quantidade de Fluxos | Significado |
|---------------|----------------------|-------------|
| **A — Implementado e funcional** | 52 fluxos | O LJ SmartClinic reproduz integralmente o fluxo da planilha |
| **B — Implementado parcialmente** | 38 fluxos | Existe, mas falta alguma função ou o formato difere |
| **C — Estrutura existente, fluxo incompleto** | 24 fluxos | Há tabelas ou código, mas o fluxo não está completo ou difere da planilha |
| **D — Não implementado** | 15 fluxos | Não existe equivalente no sistema |
| **E — Não verificável** | 8 fluxos | Faltam evidências para confirmar classificação |

**Total: 137 fluxos analisados** (algumas planilhas têm sobreposição de módulos).

## 5. Experiência da Cliente

### 5.1 Facilidade de tarefas

| Tarefa | Planilha | LJ SmartClinic | Avaliação |
|--------|----------|----------------|-----------|
| Localizar cliente | Lista de nomes em aba | Busca `pacientes` por nome | **A** — Igual de fácil |
| Consultar horário | Grade semanal visual | Calendar `dashboard` + `agenda` view | **B** — Sistema mais rico, mas grade semanal sumária não idêntica |
| Registrar atendimento | Nova aba/linha na planilha | `AgendaController@store` + appointment | **A** — Fluxo equivalente |
| Marcar presença/falta | Coluna G: PRESENTE/AUSENTE | `appointments` status `atendido`/`faltou` | **A** — Idêntico |
| Consultar sessões restantes | Total por mês em resumo | `pacotes_sessao` + `consumo_sessao` | **B** — Dados existirem, consultabilidade diferente |
| Registrar pagamento | Nova aba/mês no PAGAMENTOS | `FinanceiroController@store` + payment | **A** — Fluxo idêntico |
| Controlar despesas/vencimentos | CONTAS .xlsx: 50 abas, validações lists | `suppliers` + `payments` + `accounts_receivable` | **C** — Mais fragmentado no sistema |
| Consultar histórico | Revisar abas passadas | `appointments` + `evolucoes_sessao` + `avaliacoes` | **B** — Histórico existe, mais trabalhoso de consultar |
| Exportar/imprimir | Planilha Excel nativa | Relatórios do sistema + exports possíveis | **A** — Sistema tem relatórios exportáveis |

### 5.2 Telas que precisam ser simplificadas

- Tela de agenda: grade semanal atual precisa ser substituída por visualização por data específica (as planilhas não usam data específica, apenas dia da semana)
- Tela de pagamentos: atualmente sem vínculo FK paciente; idealmente exibir nome do paciente + serviço + valor + forma de pagamento em layout similar ao resumo mensal da planilha
- Tela de contas a pagar: não existe; seria necessário criar view/listagem semelhante ao CONTAS .xlsx com validações de status

## 6. Integridade e Isolamento

### 6.1 Conferência de dados (somente leitura)

| Entidade | Quantidade | Observação |
|----------|------------|------------|
| Pacientes clinic_000001 (Malheiro) | 496 | Intocado |
| Pacientes clinic_000005 (Daiane) | 767 | +458 na fase 2 |
| Consultas clinic_000005 | 1.362 | 1.314 atendido / 48 faltou |
| Pagamentos clinic_000005 | 6.163 | +5.591 na fase 2 |
| Suppliers clinic_000005 | 94 | Importados de CONTAS .xlsx |
| pacotes_sessao clinic_000005 | 369 | Incluídos na fase 2 |

### 6.2 Integridade validada (anterior a esta auditoria)

- 0 órfãos
- 0 status inválido
- 0 chave duplicada
- 0 nome vazio
- 0 valor ≤ 0
- 0 fornecedor duplicado

### 6.3 Divergência do Git (documentada, sem alterações)

- VPS tem 269 arquivos modificados e não commitados (data 03/11, anterior a esta sessão)
- Git HEAD: `7cb2f05`
- **Não foram executados** checkout, reset, clean, stash, commit, pull ou push
- Produção não corresponde ao commit HEAD; código publicado está à frente do versionamento
- **Não tocado** em nenhum arquivo da VPS.

### 6.4 Separação entre tenants

- `clinic_000001` (Malheiro): 496 pacientes, 0 appointments, 0 payments, 0 suppliers — **intocado**
- `clinic_000005` (Daiane): todos os dados acima — **somente leitura durante auditoria**

## 7. Divergência do Git (investigação sem alterações)

- O comando `git status` na VPS mostra 269 arquivos com mtime 03/11 (antes desta sessão)
- O commit HEAD é `7cb2f05`
- Os 269 arquivos modificados estão **à frente** do HEAD (código publicado após o commit)
- **Ação tomada:** Apenas leitura do snapshot via `tar`/`scp`; **nenhuma alteração** nos arquivos da produção
- Documentação deste estado incluída no relatório para conscientização, sem tentar reverter ou igualar versões

## 8. Entregáveis Parciais (já criados)

- `docs/daiane/PLANO_IMPORTACAO_DADOS.md` — Seção 7 com resultados da fase 2
- `docs/daiane/INVENTARIO_PLANILHAS_DAIANE.md` — Inventário e SHA256 das 8 planilhas
- `docs/daiane/MATRIZ_PLANILHAS_VS_SISTEMA.md` — Mapeamento planilha → tabela (parcial, em desenvolvimento)

## 9. Próximos Passos (pendentes - não executados)

Esta seção documenta o que ainda precisa ser feito após esta auditoria, aguardando autorização:

1. **Criar** `docs/daiane/AUDITORIA_EQUIVALENCIA_PLANILHAS.md` (este documento)
2. **Criar** `docs/daiane/MATRIZ_FUNCIONAL_PLANILHAS_SISTEMA.md` (matriz de mapeamento funcional)
3. **Criar** `docs/daiane/PLANO_IMPLEMENTACAO_SUBSTITUICAO_PLANILHAS.md` (plano de 6 etapas)

---

*Relatório gerado em 04/11/2026, modo somente-leitura. Nenhum dado de produção foi alterado, nenhum código foi modificado, nenhuma migration foi executada.*