# AUDITORIA DE PERSONALIZAÇÃO — CLÍNICA DAIANE TEIXEIRA

**Data da auditoria:** 01/11/2026  
**Clínica-alvo:** CLINICA DAIANE TEIXEIRA (`clinic_000005`, id 5)  
**Perfil de atendimento:** fisioterapia, pilates (individual e coletivo), depilação a laser e tratamentos com múltiplas sessões  
**Natureza desta etapa:** auditoria **somente leitura** — nenhum arquivo de código, migration, banco, configuração de clínica ou integração foi alterado  
**Objetivo:** levantar o que precisa ser adaptado antes de qualquer implementação

---

## Como ler este relatório

Existem **duas linhas de base** diferentes, e confundi-las é o maior risco desta etapa:

| Linha de base | O que é | Estado |
|---|---|---|
| **Produção atual** | VPS `root@179.236.238.61`, `/var/www/ljsmartclinic`, HEAD `7cb2f05` + alterações não commitadas | **100% odontológica.** Não possui vertical, avaliação, evolução nem pacotes |
| **Código local** | Branch `feature/fisioterapia` (HEAD `deef100`), que contém os commits da Fase C | Possui vertical, avaliação, evolução, pacotes e testes — **não está na VPS** |

A branch local `feature/fisioterapia` tem como merge-base exatamente o HEAD da VPS (`7cb2f05`). Ou seja: **o código de fisioterapia está à frente da produção, mas a árvore de trabalho da VPS contém trabalho próprio que não existe na branch local** (módulo fiscal novo, prospecção, social, WhatsApp). Ver seção 9, risco R2.

### Legenda de classificação

| Classe | Significado |
|---|---|
| **A** | Reutiliza como está (no máximo ajuste de dado, não de código) |
| **B** | Reutiliza com adaptação de código/tela |
| **C** | Não existe — precisa ser criado |
| **D** | Não se aplica / deve ficar indisponível para a Daiane |
| **E** | Não verificável nesta auditoria (exige execução, credencial ou decisão externa) |

---

## 1. Estado atual

### 1.1 Produção (VPS)

| Item | Estado verificado |
|---|---|
| Código da vertical | **Ausente.** `CatalogoModulos`, `ResolutorModulo`, `VerticalManager`, `EnsureVertical`, models de fisio, views de avaliação/evolução/pacotes e testes da vertical **não existem** na VPS |
| Alias `vertical` | **Não registrado** em `bootstrap/app.php` da VPS |
| Coluna `clinics.vertical` | **Não existe** no banco central (`information_schema` → 0 colunas) |
| Migrations de fisio | `2026_10_21_000002` a `2026_10_21_000006` **não aplicadas** no tenant da Daiane |
| Consequência para a Daiane | Sem a coluna, `VerticalManager` cairia no fallback `odontologia`; como o código nem existe, ela simplesmente usa o sistema **odontológico** |
| Estado da Malheiro | Intacta; nenhuma alteração de dado foi feita |

### 1.2 Código local (branch `feature/fisioterapia`)

Já implementado e coberto por testes locais (não executados nesta auditoria):

- flag de vertical no registro central da clínica;
- middleware que recusa no servidor o módulo de outra vertical;
- catálogo único de módulos, com situação `disponivel`/`planejado` por vertical;
- tela padrão “Funcionalidade planejada — ainda não disponível para uso.”;
- ficha de avaliação inicial (rascunho → finalizada, imutável depois de assinada);
- evolução por sessão (mesma regra de imutabilidade);
- pacotes de sessões com consumo transacional e saldo nunca negativo;
- registro profissional (CREFITO) sem renomear o `cro`;
- inventário de tabelas por vertical + comando `tenants:auditar-tabelas`;
- identidade visual (logo e cores) editável pela própria clínica.

### 1.3 Divergência entre as árvores (crítica)

| Item | Local | VPS |
|---|---|---|
| Módulo fiscal | `app/Services/Fiscal/{FiscalService,SendNfClient}.php` + migration `2026_10_22_000001_create_fiscal_tables` | Módulo novo: `FiscalManager`, providers, `NotaFiscalService`, `PayloadBuilder`, `XmlBuilder`, views `fiscal/`, migrations `2026_10_24_000000`, `2026_10_27_000000`, `2026_10_27_000100` |
| Filas/WhatsApp | Parcial | `app/Services/WhatsApp/*`, outbox, regras, comandos, testes próprios |
| Prospecção | Parcial | Controllers, services, views e `2026_10_11_20000x` (sem a `200007`) |
| Vertical/fisio | Completo | Inexistente |

**Não existe deploy seguro enquanto as duas árvores não forem reconciliadas.** Nenhuma ação de deploy foi executada nesta etapa.

---

## 2. Tenant confirmado

A clínica auditada é a **`clinic_000005`**, e não uma clínica nova.

| Verificação | Resultado |
|---|---|
| Registro central | `saas_central.clinics` id **5**, `CLINICA DAIANE TEIXEIRA`, `status=trial`, `is_active=1`, `timezone=America/Sao_Paulo`, não excluída |
| Conexão do tenant | `tenant_connections` id 5 → `clinic_id=5`, driver `mysql`, database **`clinic_000005`**, ativa |
| Migração do tenant | `last_migrated_at = 2026-10-30 23:09:20` |
| Usuário | `saas_central.users` id **2**, `clinicadaianeteixeira@gmail.com`, `clinic_id=5`, ativo |
| Papel no tenant | `user_roles` → papel **admin** |
| Assinatura | `subscriptions` id 2 → plano **Starter**, status **trialing**, criada em 28/10/2026 |
| Banco do tenant | **54 tabelas**, **56 migrations**, **0 pacientes**, **0 profissionais**, **0 agendamentos** |
| Ficha da clínica | `clinic_profile`: `CLINICA DAIANE TEIXEIRA LTDA`, CNPJ `41.824.797/0001-29`, telefone `(38) 9103-8454`, Januária/MG, cor `#2563eb`, **sem logo** |
| Configurações | `clinic_settings`: **0 linhas** (expediente e textos de atendimento ainda não configurados) |

### 2.1 O que já existe no banco da Daiane

| Item | Quantidade | Observação |
|---|---|---|
| Procedimentos | 10 | **Todos odontológicos** (limpeza, canal, restauração, extração, coroa, clareamento) |
| Status de agendamento | 6 | `agendado`, `confirmado`, `em_atendimento`, `atendido`, `faltou`, `cancelado` — **reutilizáveis** |
| Formas de pagamento | 6 | dinheiro, PIX, crédito, débito, boleto, convênio — **reutilizáveis** |
| Salas | 3 | “Sala 1/2/3” — genéricas, reutilizáveis |
| Especialidades | 7 | **Todas odontológicas** (Endodontia, Ortodontia etc.) |
| Convênios | 1 | `OdontoSaúde` — odontológico |
| Horários de profissional | **0** | Sem `professional_schedules`, não há disponibilidade calculada |
| Permissões | 68 | **Sem** `avaliacao`, `evolucao`, `pacotes` |
| Papéis | 6 | admin, dentista, recepcao, financeiro, estoque, auxiliar — **sem** `fisioterapeuta` |
| Tabelas de fisio | **0** | `avaliacoes`, `evolucoes_sessao`, `pacotes_sessao`, `consumo_sessao` não existem |

### 2.2 Limites do plano Starter

O plano da Daiane **não inclui** `whatsapp`, `external_api`, `appointment_ai` nem `advanced_automations`. Recursos do plano Starter (id 1):

| Recurso | Limite |
|---|---|
| `ai_agent` | 0 mensagens/mês |
| `facebook`, `instagram` | sem limite declarado |
| `scheduled_posts` | 15/mês |

A descrição comercial do plano Starter no banco central está escrita como **“Sistema de gestão odontológica…”**. Isso é dado comercial, não código: **não foi alterado** e precisa de decisão da plataforma (ver 4.6).

*Observação:* a flag `whatsapp_outbox` está ligada para a clínica 5, mas a rota de integração exige `plan:whatsapp`, que o Starter não possui. A flag sozinha não libera a integração.

---

## 3. Arquivos e módulos analisados

### 3.1 Núcleo da vertical (local)

- `app/Support/Modulos/CatalogoModulos.php`
- `app/Services/Tenant/VerticalManager.php`
- `app/Services/Modulos/ResolutorModulo.php`
- `app/Http/Middleware/EnsureVertical.php`
- `app/Services/Tenant/InventarioTabelas.php`
- `app/Console/Commands/AuditarTabelasCommand.php`
- `bootstrap/app.php` (alias `vertical`)
- `resources/views/components/menu-modulos.blade.php`
- `resources/views/modulos/planejado.blade.php`
- `routes/web.php`

### 3.2 Fisioterapia (local)

- `app/Http/Controllers/AvaliacaoController.php`
- `app/Http/Controllers/EvolucaoSessaoController.php`
- `app/Http/Controllers/PacoteSessaoController.php`
- `app/Services/Fisioterapia/PacoteSessaoService.php`
- `app/Models/{Avaliacao,EvolucaoSessao,PacoteSessao,ConsumoSessao}.php`
- `database/migrations/tenant/2026_10_21_000002..000006`
- `database/migrations/central/2026_10_21_000001_add_vertical_to_clinics_table.php`
- `resources/views/{avaliacoes,evolucoes,pacotes}/`
- `tests/Feature/{VerticalTest,CatalogoModulosTest,AvaliacaoTest,EvolucaoSessaoTest,PacoteSessaoTest,MigrationsFisioterapiaTest,AuditoriaTabelasTest}.php`
- `tests/Concerns/PreparaClinicaFisioterapia.php`

### 3.3 Módulos clínicos e de gestão

- `app/Http/Controllers/{Paciente,Agenda,Dashboard,Financeiro,Relatorio,Profissional,Procedimento,TratamentoPlano,Orcamento,Prescricao,Odontograma}Controller.php`
- `app/Http/Controllers/Admin/{User,Role,Permission,Clinica,Fiscal}Controller.php`
- `app/Models/{Paciente,Appointment,Professional,TreatmentPlan,TreatmentPlanItem,BudgetItem,Payment,Installment,AccountsReceivable}.php`
- `app/Services/Agenda/{AgendamentoService,DisponibilidadeService}.php`
- `resources/views/{dashboard,agenda,pacientes,planos,orcamentos,profissionais,financeiro,relatorios}/`
- `database/seeders/tenant/RolePermissionSeeder.php`
- `routes/web.php`, `routes/api.php`, `routes/platform.php`
- `config/tenancy.php`

### 3.4 Banco e produção (leitura)

- `saas_central`: clauses, conexões, usuários, assinaturas, planos, `plan_features`, `clinic_feature_flags`, `migrations`
- `clinic_000005` e `clinic_000001`: tabelas, colunas, migrations, permissões, papéis, contagens e dados de catálogo
- VPS: `git status`, presença de arquivos-chave, `bootstrap/app.php`, migrations aplicadas

---

## 4. Classificação A–E

### 4.1 Quadro geral

| # | Funcionalidade | Produção hoje | Código local | Classe | Observação |
|---|---|---|---|---|---|
| 1 | Dashboard | Existe, com indicadores odontológicos | Idem | **B** | Cartão “Tratamentos em andamento” conta `TreatmentPlan`; não há sessões/pacotes |
| 2 | Agenda (fisio, pilates individual, laser) | Existe | Idem | **A** | Semana, sala, profissional, status, lista de espera e título livre |
| 3 | Agenda: validação de conflito no formulário web | Não valida | Não valida | **B** | O lock existe só no fluxo de WhatsApp (`AgendamentoService`); a tela pode sobrepor horário |
| 4 | Agenda: turma de pilates coletivo | Não existe | Não existe | **C** | Não há entidade “turma” nem “aluno” |
| 5 | Agenda: limite de participantes por turma | Não existe | Não existe | **C** | — |
| 6 | Agenda: horário recorrente / grade fixa | Não existe | Não existe | **C** | Só consulta avulsa; `professional_schedules` é expediente, não recorrência de sessão |
| 7 | Lista de alunos por turma | Não existe | Não existe | **C** | — |
| 8 | Presença / falta | Status `atendido` e `faltou` existem | Idem | **A** | Cobre o registro, mas não a regra de reposição |
| 9 | Reposição de aula/sessão | Não existe | Não existe | **C** | — |
| 10 | Pacientes | Existe | Idem | **A** | CRUD, documentos, soft delete, histórico |
| 11 | Prontuário | Existe | Idem | **B** | `prontuario()` filtra `Professional::where('tipo','dentista')`; a tela não oferece atalhos para avaliação/evolução/pacotes |
| 12 | Avaliação inicial | Não existe | Existe | **A** *(local)* / **D** *(hoje)* | Só funciona depois de deploy + migration + permissão |
| 13 | Evolução por sessão | Não existe | Existe | **A** *(local)* / **D** *(hoje)* | Idem |
| 14 | Pacote de sessões (saldo, extrato) | Não existe | Existe | **A** *(local)* / **D** *(hoje)* | Consumo manual; não é debitado pela agenda |
| 15 | Consumo automático do pacote ao concluir a sessão | Não existe | Não existe | **B** | Hoje exige ação separada em “Pacotes” |
| 16 | Odontograma | Existe e **aparece** para a Daiane | Existe, oculto pela vertical | **D** | Não se aplica à Daiane |
| 17 | Prescrições | Existe | Existe | **B** | `PrescricaoController` filtra `tipo='dentista'`; a tela oferece atalho para odontograma |
| 18 | Procedimentos (catálogo) | Existe | Existe | **A** | Cadastro serve às duas verticais; os **dados** atuais são odontológicos |
| 19 | Planos de tratamento (base) | Existe | Existe | **B** | Item liga procedimento a `dente`; `planos/show` exibe “Dente” |
| 20 | Múltiplos tratamentos ativos com serviço, quantidade, valor, forma de pagamento e validade | Parcial (título, itens, valor, status) | Parcial | **C** | Falta `forma_pagamento`, `validade`, `sessões previstas` e vínculo com pacote |
| 21 | Próxima sessão prevista | Não existe | Não existe | **C** | Pode ser derivada da agenda, mas não há frequência contratada |
| 22 | Orçamentos | Existe | Existe | **B** | `orcamentos/show` tem campo e coluna “Dente” |
| 23 | Profissionais | Existe, com CREFITO no código local | Idem | **A** | Tipo `fisioterapeuta`, `registro_profissional` e `conselho_regional` já estão na tela local; só o placeholder de busca fala em CRO |
| 24 | Financeiro — contas a receber (listar e baixar) | Existe | Existe | **B** | **Nada no sistema cria** um `AccountsReceivable`; só o seeder de demonstração cria |
| 25 | Financeiro — gerar cobrança a partir de plano/orçamento | Não existe | Não existe | **C** | — |
| 26 | Pagamentos e parcelas | Pagamento existe; parcela é só model | Idem | **B**/ **C** | `Installment` não tem tela nem geração |
| 27 | Contas a pagar | Não existe | Não existe | **C** | Existe `suppliers`, mas nenhuma conta a pagar |
| 28 | Convênios | Existe | Existe | **A** | Serve para plano de saúde; hoje só há “OdontoSaúde” cadastrado |
| 29 | Relatórios | Existe (financeiro, agenda, estoque) | Idem | **B** | Não há relatório de sessões, pacotes a vencer ou produção por profissional |
| 30 | Permissões e papéis | Existem, sem os de fisio | Seeder local inclui | **B** | Tenant precisa de reseed; papel `fisioterapeuta` local não tem `pacotes.create/update` |
| 31 | Laser: regiões corporais, parâmetros e histórico por região | Não existe | Não existe | **C** | — |
| 32 | Estoque (consumíveis de laser/pilates) | Existe | Existe | **A** | — |
| 33 | Contratos e documentos | Existem | Existem | **A** | Termo de consentimento do laser é conteúdo, não código novo |
| 34 | Identidade visual (logo, cores) | Existe | Existe | **B** | Daiane tem cor e não tem logo; login é neutro (não tem texto odontológico) |
| 35 | WhatsApp / IA / agenda automática | Fora do plano Starter | Fora do plano | **D** | Exige upgrade de plano e configuração de Evolution/n8n |

### 4.2 Itens que já podem ser reutilizados (A)

Sem escrever código novo, a Daiane consegue usar:

- **Cadastro de pacientes** e documentos;
- **Agenda** para sessões individuais de fisioterapia, pilates individual e laser;
- **Status de agendamento** (inclusive `atendido` e `faltou`);
- **Catálogo de procedimentos**, formas de pagamento, salas e convênios (com dados novos);
- **Estoque** de consumíveis;
- **Contratos e documentos**;
- **Convênios**;
- **Profissionais**, inclusive com CREFITO (após migration);
- **Avaliação, evolução e pacotes** — no código local, após deploy.

### 4.3 Itens que precisam de adaptação (B)

| Item | O que está errado hoje | Adaptação |
|---|---|---|
| Prontuário | `PacienteController::prontuario()` consulta `Professional::where('tipo','dentista')` e a variável `$dentistas` nem é usada na view; não há botões de avaliação/evolução/pacotes | Filtrar por vertical/todos os profissionais e incluir os atalhos de fisio |
| Prescrições | `PrescricaoController` filtra dentista; view aponta para odontograma | Filtrar por profissional habilitado da clínica e remover o atalho odontológico |
| Planos de tratamento | Campo e coluna “Dente”; placeholder “Reabilitação dentária”; item com FDI 11–48 | Esconder “Dente” na vertical fisioterapia e trocar o vocabulário |
| Orçamentos | Campo e coluna “Dente” | Idem |
| Dashboard | “Tratamentos em andamento” via `TreatmentPlan`; nenhum indicador de sessão/pacote | Cartões e rótulos por vertical |
| Financeiro | Lista e baixa funcionam, mas **nada gera a conta** | Criar a origem da cobrança (ver C) e amarrar ao plano/pacote |
| Relatórios | Só financeiro, agenda e estoque | Incluir sessões realizadas, pacotes a vencer e produção por profissional |
| Permissões | Tenant sem `avaliacao`, `evolucao`, `pacotes`; sem papel `fisioterapeuta` | Reseed do tenant e revisão do papel |
| Agenda (conflito) | Formulário web não valida sobreposição | Reutilizar a validação/lock do `AgendamentoService` na tela |
| Identidade visual | Sem logo; cor padrão azul | Upload de logo e definição de cores |

### 4.4 Itens que precisam ser criados (C)

1. **Turmas de pilates coletivo** — entidade “turma” com modalidade, profissional, sala, capacidade, dia/horário e vigência.
2. **Limite de participantes** — validação de capacidade por turma.
3. **Horários recorrentes** — geração de sessões semanais a partir da turma.
4. **Lista de alunos** — vínculo paciente ↔ turma, com entrada/saída.
5. **Presença por sessão de turma** — presente, falta, falta justificada.
6. **Reposição** — regra e registro de reposição (prazo, limite e cobrança são decisão da clínica).
7. **Laser: regiões corporais** — catálogo de regiões (ex.: axila, perna, buço) e vínculo com o procedimento.
8. **Laser: histórico por região** — sessões aplicadas por região, com data, profissional e parâmetros.
9. **Próxima sessão prevista** — frequência contratada ou cálculo a partir da agenda.
10. **Múltiplos tratamentos ativos** — serviço, quantidade de sessões, valor, forma de pagamento e validade no mesmo tratamento.
11. **Geração de contas a receber** — a partir de plano, pacote ou orçamento.
12. **Parcelamento** — geração e controle de parcelas.
13. **Contas a pagar** — despesas da clínica.
14. **Relatórios de fisioterapia** — sessões, faltas, reposições, pacotes e produção.

> **Não criar** tabela/entidade nova para “paciente”, “sessão individual” ou “profissional”: o que existe atende.

### 4.5 Itens indisponíveis por decisão (D)

- **Odontograma** — não se aplica; deve ficar invisível e recusado na URL para a Daiane.
- **Prescrição odontológica / campos de dente** — devem desaparecer da experiência dela, mas o código continua existindo para a Malheiro.
- **WhatsApp, IA de atendimento, API externa e agendamento por IA** — fora do plano Starter; exigem upgrade e configuração. Não é trabalho de código desta personalização.

### 4.6 Itens não verificáveis nesta auditoria (E)

- Renderização real das telas locais de avaliação/evolução/pacotes no navegador (não houve execução do app).
- Resultado da suíte de testes local (não foi executada; a instrução desta etapa proíbe alterar testes, e a auditoria é de leitura).
- Comportamento do n8n/Evolution ao chamar `POST api/whatsapp/mensagem-interativa` (depende de serviço externo).
- Migração dos dados históricos da Daiane — não há dados (0 pacientes).
- Efeito de um deploy sobre o módulo fiscal da VPS — exige reconciliação de árvores e janela autorizada.
- Valores, frequências e políticas comerciais (ver 4.7).

### 4.7 Decisões a confirmar (não inferidas)

Estas decisões **não foram tomadas** e não devem ser inventadas na implementação:

1. **Pilates coletivo:** quantas turmas, quantos alunos por turma, duração e dias?
2. **Pilates individual:** é sessão avulsa, pacote mensal ou plano com frequência fixa?
3. **Faltas e reposição:** prazo para repor, limite por mês, cobrança ou não da falta?
4. **Laser:** quais regiões, quais equipamentos/parâmetros precisam ser registrados e se há termo de consentimento obrigatório?
5. **Pacotes:** o saldo é por sessão (como o código local já faz) ou mensal por turma?
6. **Tratamentos com várias sessões:** o controle é por `pacotes_sessao`, por `treatment_plans` ou pelos dois?
7. **Financeiro:** quem gera a cobrança (recepção, profissional ou automaticamente ao aprovar o plano)? Há parcelamento? Há contas a pagar?
8. **Convênios:** a Daiane atende plano de saúde? Se não, o menu de convênios pode ficar oculto.
9. **Prescrições:** fisioterapeuta emite prescrição no sistema?
10. **Assinatura:** a “finalização” atual (botão + auditoria) basta, ou é necessária assinatura digital com certificado?
11. **NFS-e:** a Daiane precisa emitir nota? O módulo fiscal que está na VPS é mais novo que o local e precisa ser preservado.
12. **Plano comercial:** a descrição do Starter fala em “gestão odontológica”; a Daiane deve continuar no Starter ou migrar para um plano com WhatsApp?
13. **Quem opera:** somente a Daiane, ou também recepção e outros profissionais? Isso define os papéis.

---

## 5. O que é reaproveitável

### 5.1 Núcleo clínico

- **Pacientes** — cadastro completo, soft delete, documentos, histórico.
- **Agenda** — semana, sala, profissional, status, lista de espera, título e observação livres. Serve para fisio individual, pilates individual e laser.
- **Prontuário** — anamnese, alertas clínicos, notas e evolução em texto livre.
- **Profissionais** — tipos, CREFITO, comissão, especialidades, vínculo com usuário.
- **Procedimentos** — catálogo por clínica, com duração e valor; é o lugar certo para cadastrar os serviços da Daiane.
- **Contratos, documentos e estoque** — prontos.

### 5.2 Fisioterapia (código local)

- **Avaliação** com rascunho/finalizada e imutabilidade após assinatura.
- **Evolução por sessão** com o mesmo padrão.
- **Pacote de sessões** com consumo transacional (`lockForUpdate` + `UPDATE` condicional), sem saldo negativo.
- **Extrato de consumo** (`consumo_sessao`), com vínculo opcional à evolução.
- **Vertical** decidida no servidor, com fallback seguro para odontologia.
- **Catálogo de módulos** que já sabe dizer “disponível” ou “planejado” por vertical.
- **Inventário de tabelas** que quebra em vermelho se uma tabela odontológica sumir.

### 5.3 Infraestrutura

- Multi-tenant já resolvido (`TenantDatabaseManager`, `ResolveTenant`).
- Auditoria (`AuditLogger`) em criação, alteração, finalização e consumo.
- Permissões por módulo/ação e papéis por clínica.
- Marca por clínica (logo e cores) no registro central.
- Testes de vertical, catálogo, isolamento e migrations.

---

## 6. O que precisa ser desenvolvido

### 6.1 Turmas, alunos, presença e reposição

**Problema:** a agenda atual agenda **um paciente por horário**. Pilates coletivo tem **uma sessão e vários alunos**, com capacidade e lista.

**Desenho sugerido (a validar):**

- `turmas` — nome, modalidade, profissional_id, room_id, capacidade, dia_semana, hora_inicio, hora_fim, vigência (início/fim), ativa.
- `turma_alunos` — turma_id, paciente_id, início, fim, status.
- Sessões da turma: reutilizar `appointments` como “aula” (título = turma, sem paciente obrigatório) **ou** criar `turma_sessoes`. A primeira opção reaproveita a agenda; a segunda é mais limpa para presença. **Decisão pendente.**
- `presencas` — sessão, paciente, status (presente/falta/falta justificada/reposição), observação.
- `reposicoes` — origem (falta), destino (sessão), status, validade.

**Cuidados:** capacidade validada no servidor; exclusão de aluno não apaga histórico; presença não pode ser editada depois de fechada sem auditoria.

### 6.2 Laser

**Problema:** a evolução atual é texto livre; não há região corporal nem parâmetros.

**Desenho sugerido:**

- `regioes_corporais` — catálogo por clínica (nome, ativo).
- `aplicacoes_laser` — paciente_id, profissional_id, evolucao_sessao_id (opcional), região, equipamento, parâmetros (JSON ou colunas), data, observação.
- Histórico por região = consulta agrupada, não tabela nova.
- Termo de consentimento = documento/contrato da clínica (conteúdo), com status e assinatura.

**Cuidado:** parâmetros clínicos são dados sensíveis; o sistema registra, não sugere.

### 6.3 Tratamentos com múltiplas sessões

**Problema:** `treatment_plans` tem título, itens, valor e status, mas não tem forma de pagamento, validade nem frequência; o item tem `dente`.

**Desenho sugerido (escolher um):**

- **Opção 1 (recomendada):** manter `treatment_plans` como “plano clínico” e usar `pacotes_sessao` como “contrato de sessões”, ligando um ao outro (`treatment_plan_id` em `pacotes_sessao`). O que já existe de saldo transacional é reaproveitado.
- **Opção 2:** evoluir `treatment_plans` com `forma_pagamento_id`, `validade_em`, `sessoes_previstas` e `sessoes_realizadas`.

**Decisão pendente:** qual das duas representa o negócio da Daiane.

### 6.4 Financeiro que fecha o ciclo

**Problema:** o sistema **lê e baixa** contas a receber, mas **não cria** nenhuma. Sem isso, a Daiane vende um pacote e não tem cobrança.

**Desenho sugerido:**

- botão “Gerar cobrança” no plano/pacote/orçamento aprovado;
- geração de `accounts_receivable` (à vista ou parcelado em `installments`);
- baixa continua no `FinanceiroController::receive`, que já funciona;
- contas a pagar: nova tabela + tela (fornecedor, categoria, vencimento, valor, status).

### 6.5 Relatórios de fisioterapia

- sessões realizadas por período/profissional;
- faltas e reposições;
- pacotes ativos, concluídos e a vencer;
- produção por serviço (fisio, pilates, laser);
- laser por região.

### 6.6 Ajustes de tela (B)

- prontuário com atalhos de avaliação/evolução/pacotes e sem filtro “dentista”;
- planos e orçamentos sem “Dente” na vertical fisio;
- dashboard com sessões/pacotes;
- permissões e papel `fisioterapeuta` revisados;
- agenda com validação de conflito.

---

## 7. Arquitetura recomendada

### 7.1 Princípios que já estão no código e devem ser mantidos

1. **A vertical é decidida no servidor**, a partir de `clinics.vertical`. O navegador nunca informa a vertical.
2. **Módulo fora da vertical não abre nem por URL digitada** (`EnsureVertical`).
3. **Nada é removido do banco por causa da vertical.** As tabelas odontológicas continuam existindo na clínica de fisioterapia; o que muda é o que a clínica enxerga.
4. **Dado clínico assinado é imutável.**
5. **Toda ação relevante passa pelo `AuditLogger`.**
6. **Cada clínica tem seu banco.** Nenhuma tabela nova deve ir para o banco central, exceto a flag de vertical.

### 7.2 Onde cada coisa entra

| Necessidade | Onde entra |
|---|---|
| Novos módulos (turmas, laser, contas a pagar) | `CatalogoModulos` + rotas + `EnsureVertical`/permissão |
| Novas tabelas clínicas | `database/migrations/tenant` + classificação em `InventarioTabelas` |
| Novas permissões | `RolePermissionSeeder` + reseed do tenant |
| Novas telas | `resources/views/` seguindo o padrão Bootstrap atual |
| Relatórios | `RelatorioController` + view |
| Cobrança | `AccountsReceivable` + `Installment` (já existem) |
| Marca | registro central da clínica |

### 7.3 Ordem de dependência

```
vertical aplicada
  → tabelas de fisio aplicadas
    → permissões semeadas
      → telas B (prontuário, planos, dashboard)
        → C1 turmas/presença
        → C2 laser
        → C3 tratamentos/pacotes
          → financeiro (cobrança/parcelas)
            → relatórios
```

---

## 8. Visual e identidade

| Item | Situação | Ação |
|---|---|---|
| Login | Neutro (“Entrar no sistema”), sem texto odontológico | Nenhuma |
| Layout | Lê logo e cores da clínica | Manter |
| Logo da Daiane | **Não cadastrada** | Upload em `admin/clinica/edit` |
| Cor principal | `#2563eb` (azul) | Confirmar com a clínica |
| Nome da clínica | `CLINICA DAIANE TEIXEIRA` | Confirmar nome fantasia |
| Menu | Hoje, na VPS, é odontológico; no código local vem do catálogo | Deploy do catálogo + vertical |
| Vocabulário | “Dente”, “CRO”, “Reabilitação dentária”, “Odontograma” em telas comuns | Esconder por vertical (não apagar o código da odontologia) |
| Plano Starter | Descrição diz “gestão odontológica” | Decisão comercial da plataforma |
| Termos da fisio | Textos de “sessão”, “pacote”, “turma”, “região” | Definir com a Daiane |

---

## 9. Riscos

| # | Risco | Gravidade | Mitigação |
|---|---|---|---|
| R1 | **Daiane entra hoje e vê o sistema odontológico** (sem vertical, com permissão de odontograma) | Alta | Aplicar vertical + migrations + permissões antes de liberar o acesso |
| R2 | **Árvores divergentes**: a VPS tem fiscal/social/prospecção/WhatsApp não commitados que não existem na branch local; a branch local tem fisio que não existe na VPS | **Crítica** | Backup, commit/reconciliação da VPS, merge controlado; nunca `checkout`/`pull` cego |
| R3 | Aplicar vertical antes das migrations → telas de fisio com tabela ausente (500) | Alta | Ordem: central → tenant → permissões → smoke test |
| R4 | Permissões ausentes → 403 depois do deploy | Alta | Rodar `RolePermissionSeeder` no tenant e revisar o papel `fisioterapeuta` |
| R5 | Plano Starter sem WhatsApp/API/IA; flag `whatsapp_outbox` ligada pode confundir | Média | Deixar claro que a integração exige upgrade |
| R6 | Dados odontológicos na Daiane (procedimentos, especialidades, convênio, salas) | Média | Substituir com dados da clínica; hoje há 0 pacientes, então a janela é segura |
| R7 | Agenda web permite sobreposição de horário | Média | Reutilizar o lock/validação do `AgendamentoService` |
| R8 | Papel `fisioterapeuta` local não tem `pacotes.create/update` | Média | Revisar antes de liberar |
| R9 | Relação com a Malheiro | Alta | A migration de vertical tem padrão `odontologia`; nenhuma migration remove/renomeia coluna odontológica; validar com `tenants:auditar-tabelas` |
| R10 | Registro clínico e LGPD (laser, fisio) | Alta | Auditoria, imutabilidade, controle de acesso; termo de consentimento |
| R11 | Deploy sem build de front (`npm run build`) | Média | Seguir o checklist de publicação já existente |
| R12 | Mudanças de regra comercial não documentadas | Média | Fechar as decisões de 4.7 antes de codar |

---

## 10. Plano por etapas

> Nenhuma etapa abaixo foi executada. Todas dependem de autorização explícita.

### Etapa 0 — Reconciliação e backup

- comparar local × VPS arquivo a arquivo (fiscal, social, prospecção, WhatsApp);
- commitar o trabalho da VPS ou gerar patch de backup;
- garantir que a branch de fisio incorpora o que a VPS tem de único;
- backup do banco central e dos tenants 1 e 5.

**Aceite:** árvores reconciliadas, backup íntegro, nenhuma alteração de dado.

### Etapa 1 — Vertical na Daiane (sem telas novas)

- aplicar `2026_10_21_000001` no central;
- rodar `tenants:migrate` para o tenant 5 (`000002..000006`);
- marcar `clinics.vertical = fisioterapia` na clínica 5;
- rodar `RolePermissionSeeder` no tenant 5;
- conferir `tenants:auditar-tabelas --clinica=5`.

**Aceite:** menu da Daiane sem odontograma, com avaliação/evolução/pacotes; URL manual de odontograma → 403; Malheiro inalterada.

### Etapa 2 — Ajustes B de tela

- prontuário (filtro de profissional + atalhos);
- prescrições;
- planos e orçamentos sem “Dente” na fisio;
- dashboard;
- agenda com validação de conflito;
- revisão do papel `fisioterapeuta`.

**Aceite:** nenhuma menção a dente/CRO nas telas da Daiane; fluxos atuais da Malheiro inalterados.

### Etapa 3 — Dados operacionais (com a Daiane)

- logo, cores, nome fantasia;
- profissionais (com CREFITO) e horários;
- procedimentos e valores (fisio, pilates individual/coletivo, laser);
- salas, formas de pagamento, status;
- remoção/renomeação dos 10 procedimentos odontológicos e do convênio “OdontoSaúde”.

**Aceite:** catálogo da clínica reflete os serviços reais.

### Etapa 4 — Turmas, alunos, presença e reposição (C1)

**Aceite:** criar turma com capacidade; matricular alunos; gerar sessões; marcar presença/falta; registrar reposição.

### Etapa 5 — Laser (C2)

**Aceite:** registrar região, data, profissional e parâmetros; consultar histórico por região.

### Etapa 6 — Tratamentos com múltiplas sessões (C3)

**Aceite:** mais de um tratamento ativo; serviço, quantidade, valor, forma de pagamento e validade; saldo consumido por sessão.

### Etapa 7 — Financeiro (C4)

**Aceite:** gerar cobrança de plano/pacote; parcelar; baixar pagamento; contas a pagar.

### Etapa 8 — Relatórios (C5)

**Aceite:** sessões, faltas, reposições, pacotes e produção por profissional/serviço.

### Etapa 9 — Treinamento e go-live

- manual curto por perfil;
- treino com a equipe;
- checklist de fumaça em produção;
- acompanhamento na primeira semana.

---

## 11. Testes

### 11.1 Cobertura existente (local, não executada nesta etapa)

- `VerticalTest` — vertical decide no servidor; URL manual recusada.
- `CatalogoModulosTest` — módulo disponível, planejado e de outra vertical.
- `AvaliacaoTest`, `EvolucaoSessaoTest`, `PacoteSessaoTest` — CRUD, imutabilidade e saldo.
- `MigrationsFisioterapiaTest` — migrations da fisio.
- `AuditoriaTabelasTest` — inventário por vertical.
- `IsolamentoCacheTest`, `IsolamentoTenantTest` (padrão) — uma clínica não vê a outra.

### 11.2 Testes a acrescentar na implementação

| Área | Teste mínimo |
|---|---|
| Vertical | Clínica de fisio não abre odontograma; clínica odonto não abre avaliação |
| Menu | Situação `planejado` aparece para a vertical errada |
| Permissões | Tenant sem `pacotes.view` recebe 403; reseed corrige |
| Turmas | Capacidade não pode ser excedida; aluno inativo não recebe sessão |
| Presença | Falta gera reposição; reposição consome a falta |
| Laser | Região obrigatória; histórico agrupa por região |
| Tratamentos | Dois tratamentos ativos no mesmo paciente; validade e forma de pagamento |
| Financeiro | Cobrança gerada a partir do pacote; parcela baixada; saldo correto |
| Isolamento | Dados de Daiane e Malheiro nunca se cruzam |
| Inventário | `tenants:auditar-tabelas` sem tabela faltando no tenant 5 |

### 11.3 Critério de aceite global

1. Suíte existente continua verde (incluindo `DemoSeederTest`, `IdentidadeVisualTest`, `WhatsAppApiTest`).
2. Nenhum teste da Malheiro muda de resultado.
3. `tenants:auditar-tabelas --clinica=5` sem problemas.
4. Smoke test manual: login Daiane, menu correto, 403 no odontograma, pacote consumido, avaliação finalizada imutável.

> **Nesta etapa, nenhum teste foi criado ou alterado.**

---

## 12. Arquivos a alterar

### 12.1 Central e infraestrutura

- `database/migrations/central/2026_10_21_000001_add_vertical_to_clinics_table.php` — aplicar (já existe local)
- `app/Models/Central/Clinic.php` — já pronto
- `app/Http/Requests/Platform/ClinicStoreRequest.php` — já pronto
- `resources/views/platform/clinicas/{create,edit,show}.blade.php` — já prontos

### 12.2 Vertical e catálogo

- `app/Support/Modulos/CatalogoModulos.php` — incluir novos módulos (turmas, laser, contas a pagar) com vertical e situação
- `app/Services/Tenant/VerticalManager.php` — sem mudança estrutural
- `app/Http/Middleware/EnsureVertical.php` — sem mudança
- `app/Services/Modulos/ResolutorModulo.php` — sem mudança
- `resources/views/components/menu-modulos.blade.php` — sem mudança
- `bootstrap/app.php` — alias `vertical` (já existe local)
- `routes/web.php` — rotas novas com `vertical:` e permissões

### 12.3 Ajustes B

- `app/Http/Controllers/PacienteController.php` — `prontuario()` (filtro de profissional)
- `app/Http/Controllers/PrescricaoController.php` — filtro por vertical
- `app/Http/Controllers/DashboardController.php` + `resources/views/dashboard.blade.php` — indicadores
- `app/Http/Controllers/AgendaController.php` — validação de conflito
- `resources/views/pacientes/prontuario.blade.php` — atalhos de fisio
- `resources/views/pacientes/show.blade.php` — já tem os atalhos; conferir
- `resources/views/planos/{index,show}.blade.php` — esconder “Dente” na fisio
- `resources/views/orcamentos/show.blade.php` — idem
- `resources/views/prescricoes/index.blade.php` — remover atalho odontológico
- `resources/views/profissionais/index.blade.php` — placeholder
- `database/seeders/tenant/RolePermissionSeeder.php` — revisar `fisioterapeuta` (`pacotes.create/update`)

### 12.4 Desenvolvimento C

| Item | Arquivos novos |
|---|---|
| Turmas | migrations `create_turmas`, `create_turma_alunos`, `create_presencas`, `create_reposicoes`; models; `TurmaController`; views `turmas/` |
| Laser | migration `create_regioes_corporais`, `create_aplicacoes_laser`; models; controller; views `laser/` |
| Tratamentos | migration de campos (`forma_pagamento_id`, `validade_em`, `sessoes_previstas`) **ou** vínculo `treatment_plan_id` em `pacotes_sessao`; ajustes em `TratamentoPlanoController` |
| Financeiro | geração de `AccountsReceivable`/`Installment`; tela de contas a pagar; `ContaPagarController` + migrations |
| Relatórios | novos métodos em `RelatorioController`; views |
| Inventário | classificar as tabelas novas em `app/Services/Tenant/InventarioTabelas.php` |
| Testes | `tests/Feature/TurmaTest.php`, `LaserTest.php`, `TratamentoSessoesTest.php`, `ContaPagarTest.php`, extensão de `VerticalTest` |

### 12.5 Banco (quando autorizado)

- `saas_central`: coluna `vertical` (padrão `odontologia`)
- `clinic_000005`: migrations `000002..000006` e as novas tabelas
- `clinic_000001`: **nenhuma alteração de dado**; apenas as migrations aditivas, se o deploy for global

---

## Anexo A — Evidências da auditoria

| Evidência | Como foi obtida |
|---|---|
| Clínica 5 = Daiane, trial, ativa | consulta somente leitura em `saas_central.clinics`, `users`, `subscriptions` |
| Conexão `mysql` → `clinic_000005` | `saas_central.tenant_connections` |
| 54 tabelas / 56 migrations / 0 pacientes | `information_schema.tables` e `clinic_000005.migrations` |
| Tabelas de fisio ausentes | `information_schema.tables` do tenant 5 |
| 68 permissões / 6 papéis | `clinic_000005.permissions`, `roles` |
| Plano Starter sem WhatsApp/API/IA | `saas_central.plan_features` |
| Código da vertical ausente na VPS | checagem de existência de 23 arquivos-chave + `grep` |
| `clinics.vertical` ausente na VPS | `information_schema.columns` |
| Divergência fiscal | `git status` da VPS + `ls database/migrations/tenant` |
| Branch local com Fase C | `git log`, commits `9c1e14e`…`8f4f821`, merge-base `7cb2f05` |

### Comandos úteis para a próxima etapa (não executados)

```bash
php artisan migrate:status
php artisan tenants:migrate --force --no-interaction
php artisan tenants:auditar-tabelas --clinica=5
php artisan test --compact
vendor/bin/pint --dirty --format agent
```

---

**Fim da auditoria.** Nenhum código, migration, banco, configuração de clínica, integração ou dado operacional foi alterado. A implementação depende de autorização explícita.
