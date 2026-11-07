# PLANO DE IMPLEMENTAÇÃO — SUBSTITUÍÇÃO DE PLANILHAS DAIANE PELO LJ SMARTCLINIC

## Visão Geral

Este plano prevê a substituição integral das 8 planilhas utilizadas pela clínica da Daiane (clinic_000005) pelo LJ SmartClinic, baseado na auditoria técnica realizada em modo somente-leitura. O plano é dividido em 6 etapas, do diagnóstico à validação com a cliente.

**Restrições fundamentais (não alterar):**
- Nenhum dado de produção será alterado durante a auditoria
- Nenhum código-fonte da VPS será modificado
- Todas as consultas ao banco são somente leitura
- `clinic_000001` (Malheiro) permanece intocado
- Nenhuma migration será executada
- Não há importação de planilhas durante a auditoria

---

## Etapa 1 — Correções de Funcionalidades Já Existentes

### 1.1 Objetivo
Corrigir e finalizar funcionalidades que já existem no LJ SmartClinic mas que apresentam desvios em relação ao fluxo das planilhas da Daiane.

### 1.2 Itens de correção identificados

| Item | Planilha afetada | Funcionalidade atual no LJ | Correção necessária | Risco | Dependências |
|------|------------------|---------------------------|--------------------|-------|--------------|
| Status "faltou" vs "atendido" | DEPILAÇÃO LASER.xlsx | `appointments` status: `atendido`, `faltou` | Correção de 48 registros que estavam como `atendido` mas deveriam ser `faltou` (já identificado na fase 2) | Baixo | Acesso somente leitura; correção pode ser feita via tinker ou migration futura |
| Vinculação de pagamento com paciente | PAGAMENTOS.xlsx | `payments` table | Implementar vinculação FK `paciente_id` nos lançamentos; atualmente só tem nome textual | Médio | Alteração em `Payment` model + controller; risco de quebrar launches existentes |
| Tipos de validação de contas | CONTAS .xlsx | `suppliers` + `payments` | Criar regras de validação de status (PAGO/ATRASADO/NF/BOLETO/DUPLICATA/OUTROS) consistentes com planilha | Baixo | Novo enum ou validação no `Payment`/`Supplier` model |
| Fórmulas de desconto/juros | Várias planilhas | Nenhum fluxo específico | Implementar cálculo de juros sobre vencimento e desconto à vista, presentes em fórmulas da planilha | Médio | Nova lógica em `FinanceiroController` ou service dedicado |

### 1.3 Arquivos/Classes prováveismente alteradas
- `app/Models/Payment.php` — adicionar validações, campo `paciente_id` se não existir
- `app/Http/Controllers/FinanceiroController.php` — ajustes em store/atualização
- `app/Models/Supplier.php` — adicionar field `status` consistente com validações da planilha
- Migração futura (não executar durante auditoria)

### 1.4 Testes necessários
- Verificar se 6.163 pagamentos importados mantêm integridade após alterações
- Confirmar que 94 suppliers mantêm status consistentes
- Testes unitários nos controllers de financeiro

---

## Etapa 2 — Agenda e Horários Recorrentes

### 2.1 Objetivo
Substituir a grade semanal sem data da `AGENDA CLINICA .xlsx` pela estrutura de agendamento do LJ SmartClinic, permitindo agendamento por data específica.

### 2.2 Passos necessários

| Passo | Descrição | Componente afetado | Prioridade |
|-------|-----------|--------------------|------------|
| 2.1 | Remover/ocultar grade semanal (abas AGENDA ANTIGA, CALENDÁRIO 2026, PILOTES, etc. da planilha) | Frontend — tela de agenda | Alta |
| 2.2 | Implementar agendamento por data específica | `AgendaController@store`, rota `agendas.store` | Alta |
| 2.3 | Migrar horários da grade semanal para horários por data | Dados existentes + `professional_schedules` | Média |
| 2.4 | Criar view/calendar similar à planilha CALENDÁRIO 2026 | `DashboardController@index` + Livewire ou Blade | Alta |
| 2.5 | Manter acesso a histórico por semana/mês | Relatórios financeiros + agenda filtrada | Baixa |

### 2.3 Componentes técnicos
- `app/Http/Controllers/AgendaController.php` — ajustes em store e index
- `app/Models/Appointment.php` — garantir fields `data`, `hora`, `professional_id`, `area`
- `app/Models/ProfessionalSchedule.php` — se ainda não existir
- Rotas: `agenda.*`, `professors.*` (verificar nomes reais)

### 2.4 Riscos
- Usuários da Daiane acostumados com grade SEGUNDA..SÁBADO sem data específica podem ter dificuldade de adaptação
- Necessidade de inventar datas para 51+ linhas da aba AGENDA ANTIGA (não recomendado; recadastramento direto é melhor)

### 2.5 Testes necessários
- Agendamento de nova consulta por data específica
- Visualização calendar por semana/mês
- Filtro de agendamentos por profissional/data

---

## Etapa 3 — Atendimento, Sessões e Pacotes

### 3.1 Objetivo
Garantir que o fluxo de atendimento, presença/falta e controle de pacotes de sessões corresponda ao que as planilhas exigem.

### 3.2 Itens de implementação

| Item | Planilha origem | Funcionalidade LJ | Implementação necessária | Prioridade |
|------|-----------------|-------------------|-------------------------|------------|
| Registro de presença (PRESENTE/AUSENTE) | DEPILAÇÃO LASER.xlsx | `appointments` status `atendido`/`faltou` | Já existe; confirmar que 48 correções foram aplicadas | Alta (já parcialmente feito) |
| Controle de pacotes de sessão | PGTO PILATES.xlsx + PAGAMENTOS.xlsx | `pacotes_sessao` + `consumo_sessao` | Ya existem (369 pacotes, consumos registrados); ajustar formatos de relatório | Média |
| Registro de evolução/anotações | PAGAMENTOS.xlsx (serviços diversos) | `evolucoes_sessao` + `avaliacoes` | Estender campos para capturar área do tratamento, observações, plano de saúde | Baixa |
| Histórico de atendimentos por cliente | Todas as planilhas | `appointments` + `evolucoes_sessao` + `avaliacoes` | Melhorar tela de histórico do cliente; unificar visualização | Média |

### 3.3 Componentes técnicos
- `app/Http/Controllers/EvolucaoSessaoController.php`
- `app/Http/Controllers/AvaliacaoController.php`
- `app/Models/EvolucaoSessao.php` + `avaliacoes` table
- Tela de histórico no dashboard do cliente

### 3.4 Riscos
- Dados históricos (1.362 consultas) já importados podem precisar de ajuste de status (já corrigido 48 faltas)
- Variações de nome (GISELE/GISELE ALVES/GISELE LVES) podem causar confusão na busca; sugerir endurecer validação de nome no cadastro

### 3.5 Testes necessários
- Registrar presença/falta em nova consulta
- Consultar histórico de um cliente específico
- Lançar consumo de sessão de pacote
- Buscar cliente pelo nome variado e verificar resultados

---

## Etapa 4 — Contas a Pagar e Receber

### 4.1 Objetivo
Implementar estrutura específica de contas a receber/pagar, substituindo a atual situação onde CONTAS .xlsx virou apenas `suppliers` (94) sem controle de "a pagar".

### 4.2 Itens de implementação

| Item | Planilha origem | Funcionalidade atual | Implementação necessária | Prioridade |
|------|-----------------|----------------------|-------------------------|------------|
| Tabela `accounts_payable` | CONTAS .xlsx (50 abas) | Não existe; só `suppliers` 94 | Criar migration/table `accounts_payable` com fields: `fornecedor_id`, `valor`, `data_vencimento`, `data_pagamento`, `status` (PAGO/ATRASADO/NF/BOLETO/DUPLICATA/OUTROS) | Alta |
| Tabela `accounts_receivable` (se não existir) | PAGAMENTOS.xlsx | Estrutura parcial | Verificar existência; se não existir, criar tabela analogous | Alta |
| Vinculação payment → fornecedor/cliente | CONTAS .xlsx + PAGAMENTOS.xlsx | `payments` sem FK clara | Implementar `payment_type` discriminando serviço/fornecedor; `supplier_id` opcional em payments | Média |
| Validações de lista (PAGO, ATRASADO, etc) | CONTAS .xlsx | Apenas em `suppliers` | Estender validações para `accounts_payable` e `payments` | Baixa |

### 4.3 Componentes técnicos
- Nova migration: `accounts_payable` table
- `app/Models/AccountsPayable.php` — novo model
- `app/Http/Controllers/FinanceiroController.php` — ajustes para novo fluxo
- `app/Http/Requests` — validações de formulário de conta a pagar

### 4.4 Riscos
- Dados já importados (94 suppliers) precisam ser migrados/transformados para nova tabela
- Cuidado para não quebrar relatórios existentes que usam `suppliers` table
- Histórico de pagamentos passados pode precisar de reclassificação

### 4.5 Testes necessários
- Criar nova conta a pagar e verificar persistência
- Lançar pagamento e associar a fornecedor/cliente
- Filtrar contas a pagar por status (PAGO/ATRASADO)
- Verificar consistência entre `suppliers` e nova `accounts_payable`

---

## Etapa 5 — Relatórios e Controles Administrativos

### 5.1 Objetivo
Garantir que relatórios e indicadores do LJ SmartClinic correspondam ao que a cliente via nas planilhas (totais por mês, indicadores de receita, atendimento, etc).

### 5.2 Itens de implementação

| Item | Planilha origem | Indicador LJ | Implementação necessária | Prioridade |
|------|----------------|--------------|-------------------------|------------|
| Total de receitas por mês | PAGAMENTOS.xlsx (69 abas) | Dashboard `financeiro` + relatórios | Confirmar que totais mensais exibidos correspondem; ajustar formatação se necessário | Alta |
| Total de atendimentos por mês | AGENDA CLINICA + PAGAMENTOS | `appointments` com filtro por mês | Garantizar contagem consistente; plano de saúde separação | Alta |
| Indicadores de plano de saúde | PLANO DE SAÚDE .xlsx (5 abas) | `insurance_plans` + relatórios financeiros | Estruturar relatório de valores NF vs pago por mês; campos "atrasados" | Média |
| Controle de fornecedores | CONTAS .xlsx | `suppliers` listing + relatórios | Melhorar tela de fornecedores com status e totais mensais | Baixa |
| Indicadores de pilates/massagem | PGTO PILATES + agendas | Dashboards específicos | Adicionar blocos de indicadores específicos (pilates, massagem) por mês | Baixa |

### 5.3 Componentes técnicos
- `app/Http/Controllers/RelatorioController.php` — ajustes ou criação de novos métodos
- Vistas Blade/ Livewire para relatórios mensais
- Filtros de data nos relatórios existentes
- Novas rotas se necessárias (`relatorios.mensal`, etc.)

### 5.4 Riscos
- Dados históricos de 1+ ano de planilhas precisam ser "reproduzidos" nos relatórios do sistema
- Formatação de valores (ex: R$ 1.000,00 vs 45323.0 internamente) pode causar diferenças de exibição
- Usuário pode esperar exatamente os totais que via na planilha; pequenos arredondamentos podem gerar dúvidas

### 5.5 Testes necessários
- Gerar relatório mensal e comparar totais com planilha correspondente
- Filtrar por plano de saúde e verificar valores
- Exportar relatório e comparar com printscreen da planilha
- Verificar indicadores de pilates e massagem isoladamente

---

## Etapa 6 — Validação com a Cliente

### 6.1 Objetivo
Confirmar que a cliente Daiane consegue realizar todas as tarefas cotidianas no LJ SmartClinic com o mesmo ou melhor nível de facilidade das planilhas.

### 6.2 Protocolo de validação

| Tarefa | Planilha | LJ SmartClínic | Critério de aceitação | Responsável |
|--------|----------|----------------|----------------------|-------------|
| Localizar cliente por nome | Lista em aba qualquer | Busca `pacientes` por nome | Encontrar cliente sem diferença significativa | Desenvolvedor + Daiane |
| Consultar horário de atendimento | Grade SEGUNDA..SÁBADO | Calendar + agenda por data | Agendar nova consulta por data específica | Desenvolvedor + Daiane |
| Registrar atendimento/consulta | Nova linha na agenda | `AgendaController@store` | Criar novo agendamento sem erros | Desenvolvedor + Daiane |
| Marcar presença/falta | Coluna G: PRESENTE/AUSENTE | `appointments` status | Marcar como faltou/atendido corretamente | Desenvolvedor + Daiane |
| Consultar sessões de pilates restantes | Controle por mês | `pacotes_sessao` + `consumo_sessao` | Ver quantas sessões restantes do pacote | Desenvolvedor + Daiane |
| Registrar pagamento de consulta | Nova aba/mês no PAGAMENTOS | `FinanceiroController@store` | Lançar pagamento com serviço, valor, forma PGTO | Desenvolvedor + Daiane |
| Controlar despesas/vencimentos | CONTAS .xlsx: 50 abas | `suppliers` + `accounts_payable` (após Etapa 4) | Registrar nova despesa e consultar listagem | Desenvolvedor + Daiane |
| Consultar histórico de atendimentos | Revisar abas passadas | `appointments` + `evolucoes_sessao` + `avaliacoes` | Encontrar todas consultas de um cliente | Desenvolvedor + Daiane |
| Exportar/imprimir informações | Planilha Excel | Relatórios do sistema + export | Gerar relatório e comparar com planilha | Desenvolvedor + Daiane |

### 6.3 Critério de aprovação
- Pelo menos 80% das tarefas do protocolo devem ser classificadas como "sem dificuldades" ou "melhor que planilha" pela cliente
- Tarefas classificadas como "difícil" mas necessárias devem ter workaround ou explicação
- Nenhuma tarefa crítica deve ser classificada como "impossível" sem alterações significativas

### 6.4 Atividades pós-validação
- Registrar feedback da cliente em formulário ou lista
- Identificar quais telas/sistemas precisam de simplificação adicional
- Priorizar correções na próxima sprint/backlog
- Confirmar assinatura da cliente na substituição das planilhas

### 6.5 Entregáveis finais
- Relatório de validação preenchido (cliente + desenvolvedor)
- Lista de telas a simplificar ou ajustar
- Backlog prioritado de correções pendentes
- Documentação de "como fazer" as tarefas mais comuns (caso haja necessidade de treinamento)

---

## 7. Estimativa de Esforço por Etapa

| Etapa | Esforço estimado | Complexidade | Dependências externas |
|-------|-----------------|--------------|----------------------|
| 1. Correções existentes | 40 horas | Média | Acesso ao banco de produção (somente leitura durante auditoria; alterações posteriores) |
| 2. Agenda/horários | 80 horas | Alta | Necessário consenso com cliente sobre novo formato de agenda |
| 3. Atendimento/sessões/pacotes | 60 horas | Média | Dados históricos já importados; ajuste de status já parcialmente feito |
| 4. Contas a pagar/receber | 80 horas | Alta | Criação de nova tabela; migração de 94 suppliers; cuidado com dados existentes |
| 5. Relatórios/controles | 60 horas | Média | Dados históricos; formatação de valores pode diferir levemente |
| 6. Validação com cliente | 40 horas | Média (dependente) | Disponibilidade da cliente Daiane para testes presenciais/remotos |

**Total estimado: ~360 horas (aproximadamente 2 meses de trabalho dedicado por 1 desenvolvedor)**

**Observações importantes:**
- Esforço não inclui: compra de novos servidores, migração de banco de dados massiva (já realizada na fase 2), ou aquisição de novos módulos/extensões
- Esforço considera trabalho em código existente; novas contratações ou extensões de pacote podem alterar esses números
- Etapa 4 (contas a pagar) tem maior risco de quebrar funcionalidades existentes; deve ser feita com backups e testes exaustivos
- A cliente Daiane deve estar envolvada nas Etapas 2 e 6 principalmente; seu feedback é crítico para aceitação do sistema
- Após a Etapa 6 com aprovação da cliente, as planilhas podem ser oficialmente descartadas (já foram movidas para arquivamento em `/root/daiane-importacao-20261104-235412` com modo 700)

### 8. Riscos Globais (todas as etapas)

| Risco | Gravidade | Mitigação |
|-------|-----------|-----------|
| Quebra de funcionalidades existentes ao adicionar novas tabelas/fields | Alta | Fazer em ambiente de teste primeiro; backups completos antes de produzir; migrations de "add column" apenas, nunca "drop table" |
| Resistência da cliente em mudar de fluxo familiar (planilhas) | Alta | Envolvimento precoce (Etapa 6); mostrar benefícios; treinamento; permitir que cliente leve tempo de adaptação |
| Dados históricos perdidos ou alterados indevidamente | Média | Todos os dados da fase 2 já estão no banco; auditoria confirma integridade; qualquer nova migration deve ser scriptada e testada |
| VPS divergir do git HEAD (269 arquivos modificados) | Média (já documentado) | Não tocar em produção; todo trabalho em código deve ser feito em réplica/development, nunca diretamente na VPS produção |
| Precisão de totais (planilha vs sistema) | Média | Etapas 5 e 6 focam exatamente nisso; comparativo mês a mês entre planilha e relatório gerado |

### 9. Cronograma Sugerido (apenas para planejamento, não como compromisso de data)

| Semana | Atividade |
|--------|-----------|
| 1-2 | Etapa 1: Correções de funcionalidades já existentes (status faltou, validações, PKs) |
| 3-5 | Etapa 2: Agenda e horários recorrentes (redesign da tela de agenda) |
| 6-8 | Etapa 3: Atendimento, sessões e pacotes (histórico, presença, pacotes) |
| 9-11 | Etapa 4: Contas a pagar/receber (nova tabela, migração de 94 suppliers) |
| 12-14 | Etapa 5: Relatórios e controles administrativos (ajustes finos nos relatórios) |
| 15-16 | Etapa 6: Validação com cliente (testes, feedback, ajustes finais) |
| 17 | Apresentação final e aprovação da cliente |
| -- | Descarte oficial das planilhas (já arquivado) |

---

## 10. Conferência de Não-Alteração de Produção

Este plano de implementação **não deve ser executado** sem autorização expressa após a conclusão da auditoria. As seguintes garantias se aplicam:

- [x] Nenhum arquivo da VPS `/var/www/ljsmartclinic` será alterado durante a auditoria
- [x] Nenhuma migration será executada na produção durante a auditoria
- [x] Todas as consultas ao banco `clinic_000005` foram somente leitura
- [x] Otenant `clinic_000001` (Malheiro) permaneceu intocado durante todo o processo
- [x] Os 269 arquivos modificados não commitados da VPS foram apenas lidos (via tar/sc); nenhum checkout, reset, clean, stash, commit, pull ou push foi executado
- [x] Os SHA256 das 8 planilhas foram conferidos contra os originais em `C:\Users\User\Downloads\Downloads`; confirmação de integridade mantida
- [x] O relatório final será entregue em formato de documento (Markdown) sem modificar a aplicação

**Apenas após aprovação expressa do cliente e da equipe técnica, as etapas 1-6 podem ser iniciadas em ambiente de desenvolvimento/ staging, nunca diretamente na VPS produção sem plano de rollback e testes exaustivos.**