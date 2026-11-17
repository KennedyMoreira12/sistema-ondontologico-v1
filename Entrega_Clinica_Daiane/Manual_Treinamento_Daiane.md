# Manual de Treinamento — LJ SmartClinic

## Clínica Daiane — Fisioterapia e Pilates

**Versão:** 1.2.0
**Data:** 17/11/2026
**Sistema:** LJ SmartClinic (Versão 13.33.0)
**Ambiente:** Produção (`APP_ENV=production`, `APP_DEBUG=OFF`)
**Acesso ao sistema:** https://ljaltomao.com.br
**Treinamento interativo:** https://kennedyMoreira12.github.io/sco-apresentacao/treinamento-daiane.html

---

## 🎓 Como usar o treinamento interativo

O treinamento é uma página única, sem instalação e sem login. Ele simula a clínica com dados de exemplo para você praticar antes de mexer no sistema real.

| Recurso | Como funciona |
|---------|---------------|
| **Menu lateral** | 37 itens, agrupados em Principal, Gestão, Administração e Comunicação |
| **Busca de módulo** | Pressione `/` ou `Ctrl K`, digite o nome e use `↑ ↓` + `Enter` |
| **Meu progresso** | Contador `0/35` — somente os módulos **disponíveis** entram na conta |
| **Desafios** | Cada módulo tem tarefas práticas (ex.: cadastrar paciente, consumir sessão) |
| **Quiz** | 4 perguntas por módulo; é preciso acertar **70%** (3 de 4) |
| **Visita guiada** | Botão que destaca cada elemento da tela, passo a passo |
| **Como funciona** | Explicação em texto do que o módulo faz e das regras de negócio |
| **Apresentação** | Slides com a visão geral da clínica e dos módulos |
| **Abrir no sistema real** | Leva à tela equivalente em produção (quando o módulo já está publicado) |

> **Regra do progresso:** um módulo só é concluído quando **todos os desafios** foram feitos **e** o quiz atingiu 70%. O progresso fica salvo no próprio navegador (`localStorage`, chave `daianeTreinoV1`) — no botão **zerar** você recomeça.

> **Módulos planejados:** aparecem com a etiqueta `planejado` no menu. Eles **não entram** no total de 35 e não têm quiz; a página existe para explicar com honestidade o que ainda não está disponível e o que usar no lugar.

---

## 🧭 Visão geral do menu

**37 itens = 35 disponíveis + 2 planejados.**

### Principal (12 itens)

| Módulo | Ícone | Acesso real | Situação |
|--------|-------|-------------|----------|
| Dashboard | 📊 | `/` | ✅ Disponível |
| Pacientes | 👥 | `/pacientes` | ✅ Disponível |
| Agenda | 🗓️ | `/agenda` | ✅ Disponível |
| Atendimentos | ✅ | `/atendimentos` | ✅ Disponível |
| Turmas | 🧘 | `/turmas` | ✅ Disponível |
| Prontuário | 📋 | `/pacientes` | ✅ Disponível |
| Avaliação | 🧾 | `/pacientes` | ✅ Disponível |
| Evolução por sessão | 📈 | `/pacientes` | ✅ Disponível |
| Pacotes de sessões | 🎟️ | `/pacientes` | ✅ Disponível |
| Regiões a Laser | ✨ | `/laser/regioes` | ✅ Disponível (vertical laser) |
| Tratamentos | 🎯 | `/modulos/tratamentos` | 🟸 **Planejado** |
| Turmas de Pilates | 👥 | `/modulos/turmas_pilates` | 🟸 **Planejado** |

### Gestão (19 itens)

| Módulo | Ícone | Acesso real | Situação |
|--------|-------|-------------|----------|
| Orçamentos | 💰 | `/pacientes` | ✅ Disponível |
| Financeiro (Contas a receber) | 🏦 | `/financeiro` | ✅ Disponível |
| ↳ Pagamentos | 💵 | `/financeiro/pagamentos` | ✅ Disponível |
| ↳ Contas a pagar | 📤 | `/financeiro/contas-pagar` | ✅ Disponível |
| ↳ Fornecedores | 🏭 | `/financeiro/fornecedores` | ✅ Disponível |
| ↳ Fluxo de caixa | 🌊 | `/financeiro/fluxo-caixa` | ✅ Disponível |
| ↳ Nomes parecidos | 🔁 | `/financeiro/duplicatas` | ✅ Disponível |
| Notas fiscais | 🧾 | `/fiscal` | ✅ Disponível |
| Convênios | 🛡️ | `/convenios` | ✅ Disponível |
| Profissionais | 🧑‍⚕️ | `/profissionais` | ✅ Disponível |
| Procedimentos | 🩺 | `/procedimentos` | ✅ Disponível |
| Estoque | 📦 | `/estoque` | ✅ Disponível |
| Contratos | 📜 | `/contratos` | ✅ Disponível |
| Relatórios (Visão geral) | 📊 | `/relatorios` | ✅ Disponível |
| ↳ Receitas | 📈 | `/relatorios/receitas` | ✅ Disponível |
| ↳ Presença e faltas | ✅ | `/relatorios/presenca-faltas` | ✅ Disponível |
| ↳ Pacotes e sessões | 🎟️ | `/relatorios/pacotes` | ✅ Disponível |
| ↳ Aniversariantes | 🎂 | `/relatorios/aniversariantes` | ✅ Disponível |
| Documentos | 📁 | `/pacientes` | ✅ Disponível |

### Administração (4 itens)

| Módulo | Ícone | Acesso real | Situação |
|--------|-------|-------------|----------|
| Usuários | ⚙️ | `/admin/usuarios` | ✅ Disponível |
| Perfis & Permissões | 🔐 | `/admin/perfis` | ✅ Disponível |
| Clínica | 🏥 | `/admin/clinica` | ✅ Disponível |
| Auditoria | 🔍 | `/admin/auditoria` | ✅ Disponível |

### Comunicação (2 itens)

| Módulo | Ícone | Acesso real | Situação |
|--------|-------|-------------|----------|
| Social | 📣 | `/social` | ✅ Disponível |
| Notificações | 🔔 | `/notificacoes` | ✅ Disponível |

---

## 📊 Dashboard

### Indicadores gerais

| Indicador | O que mostra |
|-----------|--------------|
| Sessões de hoje | Agendamentos do dia |
| Pacientes | Total de pacientes cadastrados |
| Tratamentos em andamento | Planos em curso (quando o módulo estiver liberado) |
| Estoque baixo | Produtos com saldo igual ou abaixo do mínimo |

### Agenda de hoje

Lista dos próximos atendimentos com horário, paciente, profissional, procedimento e status.

### Contas a receber

Saldos em aberto que precisam de cobrança, com atalho para o Financeiro.

> **No treinamento:** o desafio do Dashboard é concluir a **Visita guiada** até o último passo.

---

## 👥 Pacientes

### Objetivo

Cadastro e localização dos pacientes da clínica, com acesso à ficha completa.

### O que fazer

1. Use o campo de busca — o filtro funciona em tempo real.
2. Clique em um paciente para abrir a **ficha**.
3. Use **+ Novo paciente** para cadastrar.

### Regras

| Regra | Comportamento |
|-------|---------------|
| CPF duplicado | Cadastro bloqueado |
| Exclusão | **Soft delete** — inativa o cadastro e preserva o histórico |
| Nomes parecidos | Conferência em **Financeiro → Nomes parecidos**, sem mesclagem automática |

### Abas da ficha do paciente

| Aba | Conteúdo |
|-----|----------|
| Dados | Identificação, contato, convênio e endereço |
| Prontuário | Anamnese, evoluções, alertas e prescrições |
| Avaliação | Fichas de avaliação inicial |
| Evolução | Evoluções por sessão |
| Pacotes | Pacotes de sessões contratados |
| Laser | Histórico de laser (vertical laser) |
| Documentos | Arquivos e modelos da clínica |

### Permissões

| Ação | Permissão |
|------|-----------|
| Ver pacientes | `pacientes.view` |
| Cadastrar | `pacientes.create` |
| Editar | `pacientes.update` |
| Excluir (soft delete) | `pacientes.delete` |

---

## 🗓️ Agenda

### Objetivo

Grade semanal de sessões com controle de horários, profissionais e lista de espera.

### Como funciona

- Grade de **7h às 21h**, em blocos de **30 minutos**.
- Filtro por profissional.
- Clicar em um espaço vazio agenda naquele horário.
- A **duração do procedimento** define automaticamente o fim da sessão — não é digitada.
- A **lista de espera** fica ao lado, ordenada por prioridade, para encaixar pacientes.

### Status e cores

| Status | Cor | Quando usar |
|--------|-----|-------------|
| Agendado | 🔵 Azul | Sessão marcada, ainda não aconteceu |
| Confirmado | 🟢 Verde | Paciente confirmou presença |
| Em atendimento | 🟧 Laranja | Profissional já com o paciente |
| Atendido | 🟪 Roxo | Sessão realizada |
| Faltou | 🟥 Vermelho | Paciente não compareceu |
| Cancelado | ⬜ Cinza | Sessão cancelada |

### Permissões

| Ação | Permissão |
|------|-----------|
| Ver agenda | `agenda.view` |
| Agendar / editar | `agenda.create`, `agenda.update` |
| Registrar presença | `agenda.presenca` |
| Cancelar | `agenda.delete` |

---

## ✅ Atendimentos

### Objetivo

Recepção do dia: confirmar presença, registrar falta e consumir sessão do pacote.

### Passo a passo

| Passo | Ação |
|-------|------|
| 1 | Abra **Atendimentos** (a data padrão é hoje) |
| 2 | Filtre por profissional ou data, se necessário |
| 3 | Localize o paciente na lista |
| 4 | Clique em **Presença** ou **Falta** |
| 5 | Se houver pacote, escolha qual pacote consumir |
| 6 | Confirme a operação |

### Consumo de sessão do pacote

- O consumo é **transacional**: cada sessão é debitada **uma única vez**.
- O saldo **nunca fica negativo** — sem saldo, o consumo é **recusado**.
- A **falta** não consome sessão e libera o horário para reposição.
- Ao repor uma falta, marque **Reposição de falta** no atendimento.

### Alertas automáticos

- Pacote com poucas sessões restantes.
- Paciente sem pacote ativo.
- Pacote vencido ou perto do vencimento.

### Permissões

| Ação | Permissão |
|------|-----------|
| Ver atendimentos | `atendimentos.view` |
| Registrar presença/falta | `agenda.presenca` |
| Consumir sessão do pacote | `consumir.sessao` |

---

## 🧘 Turmas (Pilates em grupo)

### Objetivo

Este é o módulo **disponível** para o Pilates em grupo: grade semanal, capacidade, matrícula e chamada.

### Como funciona

| Recurso | Regra |
|---------|-------|
| Cadastro da turma | Nome, professor, sala, dias da semana, horário, duração e **capacidade** |
| Capacidade | Controla as vagas — turma lotada **recusa** matrícula |
| Grade semanal | Mostra a distribuição dos horários |
| + Aluno | Matricula um paciente na turma |
| Chamada | Registra presença e falta por aluno |
| Relatórios | Alimentam **Relatórios → Presença e faltas** |

### Passo a passo

1. Abra **Turmas** no menu Principal.
2. Clique em **+ Nova turma** e informe dias, horário, duração e capacidade.
3. Use **+ Aluno** para matricular os pacientes.
4. Abra **Chamada** e desmarque quem faltou.
5. Confira o total de alunos e as vagas no topo da tela.

### Permissões

| Ação | Permissão |
|------|-----------|
| Ver turmas | `turmas.view` |
| Criar / editar turma | `turmas.create`, `turmas.update` |
| Matricular aluno | `turmas.alunos` |
| Marcar presença | `turmas.presenca` |
| Excluir turma | `turmas.delete` |

> **Atenção:** o módulo **Turmas de Pilates** (`/pilates/turmas`) é **planejado** e ainda não tem tela adaptada. Use o módulo **Turmas** para o dia a dia.

---

## 📋 Prontuário clínico

### Objetivo

Registro clínico do paciente: anamnese, evoluções, alertas e prescrições.

### O que contém

| Bloco | Conteúdo |
|-------|----------|
| Anamnese | Perguntas e respostas do histórico clínico |
| Evoluções | Registros por sessão (texto, tipo e data) |
| Alertas | Informações críticas (ex.: alergia a medicamento) |
| Prescrições | Orientações e prescrições do profissional |
| Histórico de laser | Aplicações de laser do paciente (vertical laser) |

### Regras

- Alertas ficam visíveis no topo da ficha.
- Evolução finalizada fica **imutável**.
- O histórico de laser aparece também no prontuário, com atalho **Ver tudo**.

### Permissões

| Ação | Permissão |
|------|-----------|
| Ver prontuário | `prontuario.view` |
| Registrar evolução | `prontuario.create` |
| Editar (enquanto rascunho) | `prontuario.update` |
| Gerenciar alertas | `prontuario.alertas` |

---

## 🧾 Avaliação

### Objetivo

Registrar a avaliação inicial do paciente com queixa principal, nota de dor e achados.

### Campos

| Campo | Regra |
|-------|-------|
| Paciente | Obrigatório |
| Data | Obrigatória |
| Queixa principal | Texto livre |
| Nota de dor | 0 a 10 |
| Profissional | Obrigatório |
| Achados | Texto livre |

### Regras

- O sistema **não sugere diagnóstico** — registra o que o profissional descreveu.
- Enquanto está em **Rascunho**, a ficha pode ser editada.
- Depois de **Finalizar**, a ficha fica **imutável** (preserva a auditoria).
- Só um rascunho pode ser finalizado.

### Permissões

| Ação | Permissão |
|------|-----------|
| Ver avaliações | `avaliacoes.view` |
| Criar | `avaliacoes.create` |
| Editar rascunho | `avaliacoes.update` |
| Finalizar | `avaliacoes.finalizar` |

---

## 📈 Evolução por sessão

### Objetivo

Documentar cada atendimento com dor antes/depois, conduta e assinatura de presença.

### Campos

| Campo | Regra |
|-------|-------|
| Paciente | Obrigatório |
| Procedimento | Obrigatório |
| Número da sessão | **Calculado pelo sistema** por paciente — nunca digitado |
| Dor antes | 0 a 10 |
| Dor depois | 0 a 10 |
| Conduta | Texto livre |
| Data | Obrigatória |

### Regras

- Ao **Finalizar**, o conteúdo fica **imutável**.
- A **assinatura de presença** pode ser colhida **depois** da finalização.
- A evolução alimenta o relatório de presença e o histórico do paciente.

### Permissões

| Ação | Permissão |
|------|-----------|
| Ver evoluções | `evolucoes.view` |
| Criar | `evolucoes.create` |
| Editar (rascunho) | `evolucoes.update` |
| Finalizar | `evolucoes.finalizar` |
| Colher assinatura | `evolucoes.assinatura` |

---

## 🎟️ Pacotes de sessões

### Objetivo

Controlar o que foi contratado: quantidade de sessões, valor, validade e consumo.

### Campos

| Campo | Regra |
|-------|-------|
| Paciente | Obrigatório |
| Procedimento | Obrigatório |
| Nome do pacote | Obrigatório |
| Sessões | Quantidade contratada |
| Valor | Referência de venda |
| Validade | Referência de controle (não bloqueia automaticamente) |

### Regras de consumo

- O consumo é **transacional** e **nunca deixa saldo negativo**.
- Cada sessão é debitada uma única vez.
- Sem saldo, o consumo é **recusado**.
- Pacote sem saldo passa a **Esgotado**.

### Permissões

| Ação | Permissão |
|------|-----------|
| Ver pacotes | `pacotes.view` |
| Criar | `pacotes.create` |
| Editar | `pacotes.update` |
| Consumir sessão | `consumir.sessao` |

---

## 🎯 Tratamentos — 🟸 planejado

> **Funcionalidade planejada — ainda não disponível para uso.**

**Descrição oficial no sistema:** *"Plano de tratamento do paciente, com itens, valores e evolução do status."*

O módulo **Tratamentos** (`planos`) existe na estrutura do sistema, mas a tela de plano atual é **odontológica** (liga o procedimento a um dente) e ainda **não foi adaptada** para esta clínica. No sistema real, a URL `/modulos/tratamentos` abre a tela padrão de módulo planejado — o mesmo comportamento para qualquer usuário da clínica. Por isso ele aparece com a etiqueta `planejado` e **não entra** no total de 35 módulos.

### O que usar enquanto isso

| Necessidade | Use |
|-------------|-----|
| Controlar o que foi contratado | **Pacotes de sessões** |
| Registrar cada atendimento | **Evolução por sessão** |
| Registrar o histórico clínico | **Prontuário** |
| Cobrar e receber | **Orçamentos** + **Financeiro** |

### Estrutura já existente no sistema

| Funcionalidade | Situação |
|----------------|----------|
| Cadastro de planos | Implementado (estrutura) |
| Itens/procedimentos do plano | Implementado (estrutura) |
| Status (rascunho → em andamento → concluído) | Implementado (estrutura) |
| Tela adaptada para fisioterapia | 🟸 Planejado |

### Permissões previstas

| Ação | Permissão |
|------|-----------|
| Ver planos | `tratamentos.view` |
| Criar plano | `tratamentos.create` |
| Editar plano | `tratamentos.update` |
| Mudar status | `tratamentos.status` |

---

## 👥 Turmas de Pilates — 🟸 planejado

> **Funcionalidade planejada — ainda não disponível para uso.**

**Descrição oficial no sistema:** *"Cadastro de turmas de Pilates, horários recorrentes, capacidade e controle de vagas."*

O módulo **Turmas de Pilates** (`/modulos/turmas_pilates`) é exclusivo da vertical **Pilates** e ainda não tem tela adaptada. No sistema real, a URL abre a tela padrão de módulo planejado.

### O que o módulo fará

| Recurso previsto | Descrição |
|------------------|-----------|
| Grade própria de Pilates | Visão específica da vertical, além do módulo Turmas |
| Planos de aula | Progressão por nível dentro da turma |
| Relatórios da vertical | Indicadores próprios de Pilates |

### O que usar enquanto isso

Use o módulo **Turmas** — ele já cobre grade semanal, capacidade, matrícula, chamada e relatório de presença.

---

## ✨ Regiões a Laser

### Objetivo

Controlar as **regiões corporais** da depilação a laser, os **pacotes-modelo** por região e o **histórico de aplicações** de cada paciente.

> Módulo **exclusivo da vertical Laser**. A clínica odontológica não abre este módulo nem digitando a URL.

> **Importante:** nenhuma cobrança é gerada automaticamente. O pacote é referência de venda; o histórico mostra o que já foi aplicado.

### Como acessar

1. Entre no sistema com o usuário da clínica.
2. No menu **Principal**, clique em **Regiões a Laser**.
3. Use as abas **Regiões**, **Pacotes** e **Histórico**.

URL direta: `/laser/regioes`

### 🗂️ Aba 1 — Regiões

Cadastro das áreas do corpo atendidas (axilas, buço, pernas, virilha...).

| Campo | Regra |
|-------|-------|
| Nome | Obrigatório e **único** |
| Descrição | Opcional |
| Ordem | Define a ordem de exibição |
| Ativa | Região inativa não aparece para novas aplicações |

**Excluir região:** o sistema recusa quando existem **pacotes** ou **aplicações** vinculadas. Nesse caso, **desative** a região — o histórico continua íntegro.

### 📦 Aba 2 — Pacotes

Modelos de venda por região.

| Campo | Regra |
|-------|-------|
| Região | Obrigatória |
| Nome | Obrigatório e único **dentro da região** |
| Sessões | 1 a 100 |
| Valor | Referência de venda (não gera cobrança) |
| Validade (dias) | Opcional |

**Excluir pacote:** recusado quando já existe aplicação no histórico. Desative em vez de excluir.

### 🕘 Aba 3 — Histórico

Registro do que foi feito em cada paciente.

| Campo | Regra |
|-------|-------|
| Paciente | Obrigatório |
| Região | Obrigatória |
| Pacote | Opcional (sessão avulsa) |
| Data | Obrigatória |
| Profissional | Opcional (padrão: profissional do usuário) |
| Observações | Parâmetros, intercorrências, orientações |

**Número da sessão:** calculado pelo **sistema**, por paciente + região, dentro de uma transação. **Nunca é digitado.** Ex.: a 3ª sessão de axilas da Maria será sempre `nº 3`.

**Pacote x sessão:** se a sessão passar do total do pacote, aparece o selo **"além do pacote"** — não é erro: a clínica pode vender sessões avulsas ou um novo bloco.

**Onde mais aparece:** o histórico de laser também é exibido no **prontuário do paciente** (bloco "Histórico de laser"), com atalho **Ver tudo**.

### Permissões

| Ação | Permissão |
|------|-----------|
| Ver regiões/pacotes/histórico | `regioes_laser.view` |
| Cadastrar região/pacote/aplicação | `regioes_laser.create` |
| Editar região/pacote | `regioes_laser.update` |
| Excluir | `regioes_laser.delete` |

Perfil sugerido: **laser_operador**.

### Checklist do dia (laser)

- [ ] Conferir a agenda e separar os pacientes do dia
- [ ] Registrar cada aplicação no **Histórico** (região + data)
- [ ] Anotar intercorrências nas observações
- [ ] Conferir no prontuário se o histórico apareceu
- [ ] Não excluir regiões/pacotes com vínculos — desativar

---

## 💰 Orçamentos

### Objetivo

Montar propostas financeiras para o paciente antes do fechamento.

### Como funciona

| Recurso | Regra |
|---------|-------|
| Itens | Procedimento, quantidade, valor unitário e desconto |
| Total | Recalculado a cada item adicionado |
| Validade | Data-limite da proposta |
| Status | Emitido → Aprovado (base para o financeiro) |

### Passo a passo

1. Abra **Orçamentos**.
2. Adicione os itens e confira o total.
3. Emita a proposta.
4. Clique em **Aprovar** quando o paciente aceitar.

### Permissões

| Ação | Permissão |
|------|-----------|
| Ver orçamentos | `orcamentos.view` |
| Criar / editar | `orcamentos.create`, `orcamentos.update` |
| Aprovar | `orcamentos.aprovar` |
| Excluir | `orcamentos.delete` |

---

## 🏦 Financeiro

O Financeiro é uma área com **6 abas**. O item **Financeiro** do menu abre em **Contas a receber**.

| Aba | URL | O que faz |
|-----|-----|-----------|
| Contas a receber | `/financeiro` | Saldos em aberto dos pacientes |
| Pagamentos | `/financeiro/pagamentos` | Livro de entradas efetivamente recebidas |
| Contas a pagar | `/financeiro/contas-pagar` | Despesas da clínica |
| Fornecedores | `/financeiro/fornecedores` | Quem abastece a clínica |
| Fluxo de caixa | `/financeiro/fluxo-caixa` | Entradas − saídas no período |
| Nomes parecidos | `/financeiro/duplicatas` | Revisão de cadastros duplicados |

### Contas a receber

| Recurso | Regra |
|---------|-------|
| Receber | Total ou parcial |
| Valor acima do saldo | **Bloqueado** pelo sistema |
| Recebimento parcial | Conta vira **Parcial** |
| Recebimento total | Conta vira **Paga** |

### Pagamentos

| Recurso | Regra |
|---------|-------|
| Lançamento manual | Paciente, serviço, forma de pagamento e valor |
| Formas | PIX, dinheiro, cartão, boleto e convênio |
| Confirmado | Entra no total recebido e no fluxo de caixa |
| Pendente | Fica visível, mas **fora** do caixa |

### Contas a pagar

| Recurso | Regra |
|---------|-------|
| Cadastro | Fornecedor, descrição, vencimento e valor |
| Pagar | Registra a saída e reflete no fluxo de caixa |
| Contas pagas | Ficam sem ação, separadas das abertas |

### Fornecedores

| Recurso | Regra |
|---------|-------|
| Cadastro | Razão social, CNPJ/CPF, contato, categoria e status |
| Categorias | Insumos, equipamentos, aluguel e serviços |
| Inativo | Permanece no histórico, mas não deve ser usado em novas compras |

### Fluxo de caixa

| Recurso | Regra |
|---------|-------|
| Cálculo | Entradas recebidas − saídas pagas |
| Saldo acumulado | Calculado linha a linha, a partir do saldo inicial |
| Contas em aberto | Aparecem separadas — ainda não passaram pelo caixa |
| Período | De/Até recalcula entradas, saídas e saldo |

### Nomes parecidos

| Recurso | Regra |
|---------|-------|
| Objetivo | Apontar possíveis duplicidades para revisão **humana** |
| Mesclagem | **Nada é mesclado automaticamente** |
| Antes de excluir | Confira o histórico clínico e financeiro |
| Exclusão | **Soft delete** — inativa e preserva o histórico |

### Permissões do Financeiro

| Ação | Permissão |
|------|-----------|
| Ver financeiro | `financeiro.view` |
| Receber / lançar pagamento | `financeiro.receber` |
| Contas a pagar | `financeiro.pagar` |
| Fornecedores | `financeiro.fornecedores` |
| Fluxo de caixa | `financeiro.fluxo` |
| Exportar relatórios | `relatorios.exportar` |

---

## 🧾 Notas fiscais

### Objetivo

Emitir e controlar as NFS-e da clínica.

### Status

| Status | Significado |
|--------|-------------|
| Rascunho | Nota criada, ainda não enviada |
| Autorizada | Nota emitida com sucesso |
| Rejeitada | Emissão recusada — pode ser corrigida e reenviada |
| Cancelada | Nota autorizada e depois cancelada |

### Ações

- **Emitir** → rascunho vira **Autorizada**.
- **PDF** e **XML** → download dos arquivos fiscais.
- **Cancelar** → cancela uma nota autorizada.
- **Rejeitada** → corrija os dados e reenvie.

> **Ambiente atual:** **homologação** (testes). Produção exige certificado digital e configuração do emissor.

### Permissões

| Ação | Permissão |
|------|-----------|
| Ver notas | `fiscal.view` |
| Criar / emitir | `fiscal.create` |
| Cancelar | `fiscal.cancelar` |
| Configurar emissor | `fiscal.configurar` |

---

## 🛡️ Convênios

### Objetivo

Cadastrar as operadoras e seus planos.

| Campo | Regra |
|-------|-------|
| Nome | Obrigatório |
| CNPJ | Opcional, validado quando informado |
| Contato | Telefone/e-mail |
| Planos | Um convênio pode ter vários planos |

### Permissões

| Ação | Permissão |
|------|-----------|
| Ver convênios | `convenios.view` |
| Criar / editar | `convenios.create`, `convenios.update` |
| Excluir | `convenios.delete` |

---

## 🧑‍⚕️ Profissionais

### Objetivo

Cadastrar a equipe da clínica e suas regras de atendimento.

| Campo | Regra |
|-------|-------|
| Nome | Obrigatório |
| CREFITO | Registro profissional |
| Tipo | Fisioterapeuta, instrutor, etc. |
| Especialidades | Múltipla escolha |
| Comissão (%) | Percentual usado no financeiro |
| Status | Ativo/inativo |

### Permissões

| Ação | Permissão |
|------|-----------|
| Ver profissionais | `profissionais.view` |
| Criar / editar | `profissionais.create`, `profissionais.update` |
| Excluir | `profissionais.delete` |

---

## 🩺 Procedimentos

### Objetivo

Catálogo de serviços da clínica.

| Campo | Regra |
|-------|-------|
| Código | **Único** — duplicar é bloqueado |
| Nome | Obrigatório |
| Categoria | Avaliação, sessão, terapia manual, etc. |
| Duração | Define o fim da sessão na agenda |
| Valor padrão | Sugestão para planos e orçamentos |
| Status | Ativo/inativo |

### Permissões

| Ação | Permissão |
|------|-----------|
| Ver procedimentos | `procedimentos.view` |
| Criar / editar | `procedimentos.create`, `procedimentos.update` |
| Excluir | `procedimentos.delete` |

---

## 📦 Estoque

### Objetivo

Controlar produtos, saldo mínimo e movimentações.

### Regras

| Recurso | Regra |
|---------|-------|
| Estoque baixo | Saldo ≤ estoque mínimo |
| Entrada | Compra ao fornecedor |
| Saída | Uso clínico |
| Saída maior que o saldo | **Bloqueada** |
| Movimentação | Grava saldo anterior/posterior, usuário e custo |

### Permissões

| Ação | Permissão |
|------|-----------|
| Ver estoque | `estoque.view` |
| Criar / editar produto | `estoque.create`, `estoque.update` |
| Movimentar | `estoque.movimentar` |
| Excluir | `estoque.delete` |

---

## 📜 Contratos

### Objetivo

Registrar termos e consentimentos por paciente.

| Campo | Regra |
|-------|-------|
| Paciente | Obrigatório |
| Tipo | Texto livre (termo, consentimento, etc.) |
| Conteúdo | Texto do contrato |
| Status | Pendente → Assinado (com data) ou Cancelado |

### Permissões

| Ação | Permissão |
|------|-----------|
| Ver contratos | `contratos.view` |
| Criar / editar | `contratos.create`, `contratos.update` |
| Assinar / cancelar | `contratos.status` |
| Excluir | `contratos.delete` |

---

## 📊 Relatórios

O módulo abre em **Visão geral** e tem **5 abas**. Todos respeitam o período **De/Até** (padrão: mês vigente) e os relatórios detalhados exportam **CSV** com os mesmos filtros da tela (`relatorios.exportar`).

| Aba | URL | O que mostra |
|-----|-----|--------------|
| Visão geral | `/relatorios` | Resumo financeiro, de agenda e de estoque |
| Receitas | `/relatorios/receitas` | Faturamento por serviço, % do total e ticket médio |
| Presença e faltas | `/relatorios/presenca-faltas` | Atendidas, faltas, canceladas e taxa de presença |
| Pacotes e sessões | `/relatorios/pacotes` | Consumidas, restantes e validade |
| Aniversariantes | `/relatorios/aniversariantes` | Pacientes do mês para campanhas de relacionamento |

### Visão geral

| Bloco | Indicadores |
|-------|-------------|
| Financeiro | A receber, em aberto, pago e **recebido no período** |
| Agenda | Atendidas, canceladas e **faltas** |
| Estoque | Produtos, produtos baixos e entradas/saídas |

### Receitas

- Receita por serviço e **% do total**.
- **Ticket médio** = receita ÷ atendimentos pagos.
- Exportação CSV para a contabilidade.

### Presença e faltas

- Comparação por profissional.
- **Falta não consome** sessão do pacote.
- Reposição deve ser marcada como **Reposição de falta** no atendimento.

### Pacotes e sessões

- Sessões consumidas, restantes e validade.
- Filtro **vencendo em 30 dias** para antecipar renovações.
- Exportação CSV para cobrança.

### Aniversariantes

- Lista do **mês** escolhido, com dia, idade e contato.
- Depende da **data de nascimento** cadastrada na ficha.
- Botão **Preparar mensagem** para a campanha de retorno.

### Permissões

| Ação | Permissão |
|------|-----------|
| Ver relatórios | `relatorios.view` |
| Exportar CSV | `relatorios.exportar` |

---

## 📁 Documentos

### Objetivo

Reunir os arquivos do paciente e os modelos da clínica.

| Recurso | Regra |
|---------|-------|
| Anexos | Laudos, termos, atestados e exames, vinculados à ficha |
| Download | Exige permissão; cada arquivo pertence à clínica |
| Exclusão | Remove o download para todos os usuários |
| Modelos | Papel timbrado, guia de cuidados e atestado de comparecimento |

### Permissões

| Ação | Permissão |
|------|-----------|
| Ver documentos | `documentos.view` |
| Anexar | `documentos.create` |
| Baixar | `documentos.download` |
| Excluir | `documentos.delete` |
| Gerar modelo | `documentos.modelos` |

---

## ⚙️ Administração

A área de Administração tem **4 telas** e concentra o controle de acesso (RBAC).

| Tela | URL | O que faz |
|------|-----|-----------|
| Usuários | `/admin/usuarios` | Cadastro de usuários e vínculo com perfil |
| Perfis & Permissões | `/admin/perfis` | Permissões por **módulo + ação** |
| Clínica | `/admin/clinica` | Dados institucionais e cor primária |
| Auditoria | `/admin/auditoria` | Trilha de quem fez o quê, quando e como |

### Usuários

| Recurso | Regra |
|---------|-------|
| Perfil | Todo usuário tem um perfil |
| Exclusão | O sistema **impede excluir a si mesmo** |
| Auditoria | Toda operação gera registro |

### Perfis & Permissões

| Recurso | Regra |
|---------|-------|
| Permissões | Ficam no **perfil**, por módulo + ação (ex.: `estoque.update`, `orcamentos.create`) |
| Alteração | Vale para **todos** os usuários daquele perfil |
| Matriz | A tela mostra a matriz **módulo × perfil** |
| Auditoria | Toda mudança de permissão é registrada |

### Clínica

| Campo | Uso |
|-------|-----|
| Razão social / nome fantasia | Telas e documentos |
| CNPJ | Documentos e configuração fiscal |
| Telefones / WhatsApp | Contato e **API do WhatsApp** |
| Endereço | Documentos |
| Logo e cor primária | Identidade visual do sistema |

### Auditoria

| Recurso | Regra |
|---------|-------|
| O que registra | Usuário, ação, tabela, registro, data/hora e IP |
| Detalhe | Valores **antes/depois** |
| Filtros | Por ação e por tabela |
| Exemplos de ação | `criar`, `atualizar`, `excluir`, `registrar_evolucao`, `registrar_pagamento` |

### Permissões administrativas

| Ação | Permissão |
|------|-----------|
| Gerenciar usuários | `admin.usuarios` |
| Gerenciar perfis | `admin.perfis` |
| Editar clínica | `admin.clinica` |
| Ver auditoria | `admin.auditoria` |

---

## 📣 Social

### Objetivo

Organizar as publicações da clínica para Instagram e Facebook.

### Fluxo

`Pendente → Aprovado → Publicado`

| Recurso | Regra |
|---------|-------|
| Novo post | Nasce **Pendente** |
| Aprovar | Libera para publicação |
| Publicar | Só depois de aprovado |
| Rejeitar | Volta para revisão |
| Contas | Instagram e Facebook configurados em um único lugar |

> Nada é publicado sem aprovação.

### Permissões

| Ação | Permissão |
|------|-----------|
| Ver posts | `social.view` |
| Criar | `social.create` |
| Aprovar | `social.aprovar` |
| Publicar | `social.publicar` |
| Configurar contas | `social.configurar` |

---

## 🔔 Notificações

### Objetivo

Central de avisos da operação.

| Tipo de aviso | Exemplo |
|---------------|---------|
| Pacote | "Maria tem 4 sessões restantes" |
| Financeiro | "Conta a pagar vence hoje" |
| Clínico | "Evolução pendente de finalização" |

### Recursos

- O **sino no topo** mostra quantas notificações não foram lidas.
- **Marcar como lida** reduz o contador.
- **Marcar todas como lidas** limpa o contador.
- **Preferências** definem quais avisos você quer receber.

### Permissões

| Ação | Permissão |
|------|-----------|
| Ver notificações | `notificacoes.view` |
| Marcar como lida | `notificacoes.marcar` |
| Editar preferências | `notificacoes.preferencias` |

---

## 👑 Perfis e permissões (visão geral)

### Perfil: Fisioterapia (Clínica Daiane)

| Área | Ver | Criar | Editar | Excluir |
|------|-----|-------|--------|---------|
| Pacientes | ✅ | ✅ | ✅ | ✅ |
| Agenda | ✅ | ✅ | ✅ | ❌ |
| Atendimentos | ✅ | ✅ | ✅ | ❌ |
| Turmas | ✅ | ❌ | ❌ | ❌ |
| Prontuário / Avaliação / Evolução | ✅ | ✅ | ✅ | ❌ |
| Pacotes de sessões | ✅ | ✅ | ✅ | ❌ |
| Financeiro | ✅ | ❌ | ❌ | ❌ |
| Relatórios | ✅ | — | — | — |
| Tratamentos | 🟸 | 🟸 | 🟸 | 🟸 |

### Perfil: Administrador

Acesso total a todos os módulos disponíveis, incluindo Administração e Auditoria.

### Perfil: laser_operador (vertical laser)

| Ação | Permissão |
|------|-----------|
| Ver regiões, pacotes e histórico | `regioes_laser.view` |
| Cadastrar | `regioes_laser.create` |
| Editar | `regioes_laser.update` |
| Excluir (sem vínculos) | `regioes_laser.delete` |

### Permissões específicas mais usadas

| Permissão | Área | Descrição |
|-----------|------|-----------|
| `turmas.view` | Turmas | Ver grade e alunos |
| `turmas.presenca` | Turmas | Marcar presença/falta |
| `atendimentos.view` | Atendimentos | Ver a recepção do dia |
| `agenda.presenca` | Atendimentos | Registrar presença |
| `consumir.sessao` | Atendimentos / Pacotes | Debitar sessão do pacote |
| `pacotes.view` | Pacotes | Ver pacotes e saldo |
| `tratamentos.view` | Tratamentos | Ver planos (planejado) |
| `financeiro.receber` | Financeiro | Registrar recebimento |
| `financeiro.pagar` | Financeiro | Baixar conta a pagar |
| `fiscal.create` | Notas fiscais | Criar e emitir NFS-e |
| `relatorios.exportar` | Relatórios | Exportar CSV |
| `admin.perfis` | Administração | Gerenciar perfis e permissões |
| `admin.auditoria` | Administração | Consultar a auditoria |
| `regioes_laser.*` | Laser | Regiões, pacotes e aplicações |

---

## 📅 Checklist operacional diário

### 🌅 Ao iniciar o turno

- [ ] Logar no sistema com as credenciais da clínica
- [ ] Conferir o **Dashboard** (sessões de hoje, contas em aberto)
- [ ] Confirmar a **Agenda** do dia
- [ ] Verificar **Notificações** (pacotes, contas, evoluções pendentes)
- [ ] Conferir **Estoque** de materiais

### ⏱️ Durante o expediente

- [ ] Registrar **Presença** ou **Falta** em **Atendimentos**
- [ ] Consumir a sessão do **pacote** quando houver
- [ ] Fazer a **Chamada** das turmas de Pilates
- [ ] Registrar a **Evolução por sessão**
- [ ] Finalizar **Avaliações** e **Evoluções** do dia
- [ ] Anexar **Documentos** entregues pelo paciente
- [ ] Lançar **Pagamentos** e baixar **Contas a pagar**

### 🏁 Ao final do turno

- [ ] Conferir **Relatórios → Presença e faltas**
- [ ] Conferir **Financeiro → Fluxo de caixa**
- [ ] Verificar **Relatórios → Pacotes e sessões** (vencendo em 30 dias)
- [ ] Atualizar o **Estoque** de materiais/produtos
- [ ] Fechar a sessão do sistema com segurança

### 📊 Revisão semanal

- [ ] Total de atendimentos e taxa de faltas
- [ ] Pacotes consumidos e a vencer
- [ ] Receitas por serviço (CSV para a contabilidade)
- [ ] Produtos em estoque baixo
- [ ] Comparação com a semana anterior

---

## ⚠️ Solução de problemas

| Problema | Causa provável | Solução |
|----------|----------------|---------|
| Tela vazia em **Atendimentos** | Sem consultas na data selecionada | Trocar a data ou verificar a agenda |
| Botão **Presença** desabilitado | Falta de permissão | Solicitar `agenda.presenca` ao administrador |
| Pacote não aparece para consumir | Sem pacote ativo ou saldo zerado | Verificar o cadastro do paciente e o saldo |
| Consumo de sessão recusado | Pacote esgotado | Vender novo pacote ou lançar sessão avulsa |
| **Tratamentos** aparece como planejado | Catálogo da vertical (tela odontológica) | Comportamento esperado — `/modulos/tratamentos` mostra a tela padrão; use Pacotes + Evolução |
| **Turmas de Pilates** aparece como planejado | Módulo da vertical Pilates | Comportamento esperado — `/modulos/turmas_pilates` mostra a tela padrão; use **Turmas** |
| Erro ao salvar dados | Conexão instável ou dado inválido | Recarregar, limpar o cache e tentar novamente |
| Nota fiscal **Rejeitada** | Dados fiscais incorretos | Corrigir os dados e reenviar |
| Não consigo excluir região/pacote de laser | Existem vínculos | **Desativar** em vez de excluir |
| Link **Abrir no sistema real** dá 404 (laser) | Módulo ainda não publicado em produção | Usar o treinamento até a publicação |

---

## 🆘 Suporte e contatos

| Necessidade | Canal |
|-------------|-------|
| Acesso negado a um módulo | Administrador do sistema |
| Sistema lento ou erro técnico | Suporte técnico |
| Sugestão de melhoria | Registro no painel administrativo |
| Dúvida de funcionalidade | Este manual ou o treinamento interativo |
| Atualização do sistema | Changelog no painel administrativo |

### Horário de atendimento

- **Segunda a sexta:** 08:00 às 18:00
- **Sábado:** 08:00 às 12:00
- **Domingo:** plantão remoto (via ticket)

### Ambiente

| Item | Valor |
|------|-------|
| Sistema | LJ SmartClinic 13.33.0 |
| Ambiente | Produção (`APP_ENV=production`, `APP_DEBUG=OFF`) |
| Backend | Laravel (PHP 8.3) |
| Frontend | Blade + Tailwind CSS |
| Banco | MariaDB |
| URL | https://ljaltomao.com.br |

---

## 📚 Referências rápidas

| Tema | Atalho / URL |
|------|--------------|
| Treinamento interativo | `https://kennedyMoreira12.github.io/sco-apresentacao/treinamento-daiane.html` |
| Dashboard | `/` |
| Pacientes | `/pacientes` |
| Agenda | `/agenda` |
| Atendimentos | `/atendimentos` |
| Turmas | `/turmas` |
| Regiões a Laser | `/laser/regioes` |
| Tratamentos (planejado) | `/modulos/tratamentos` |
| Turmas de Pilates (planejado) | `/modulos/turmas_pilates` |
| Financeiro | `/financeiro` |
| Pagamentos | `/financeiro/pagamentos` |
| Contas a pagar | `/financeiro/contas-pagar` |
| Fornecedores | `/financeiro/fornecedores` |
| Fluxo de caixa | `/financeiro/fluxo-caixa` |
| Nomes parecidos | `/financeiro/duplicatas` |
| Notas fiscais | `/fiscal` |
| Relatórios | `/relatorios` |
| Receitas | `/relatorios/receitas` |
| Presença e faltas | `/relatorios/presenca-faltas` |
| Pacotes e sessões | `/relatorios/pacotes` |
| Aniversariantes | `/relatorios/aniversariantes` |
| Usuários | `/admin/usuarios` |
| Perfis & Permissões | `/admin/perfis` |
| Clínica | `/admin/clinica` |
| Auditoria | `/admin/auditoria` |
| Social | `/social` |
| Notificações | `/notificacoes` |

---

## ✅ Conclusão do treinamento

Ao concluir os **35 módulos disponíveis**, você terá praticado:

- ✅ Cadastro e ficha de pacientes
- ✅ Agenda, atendimentos, presença e falta
- ✅ Turmas de Pilates (grade, capacidade, chamada)
- ✅ Prontuário, avaliação e evolução por sessão
- ✅ Pacotes de sessões e consumo transacional
- ✅ Regiões a Laser (regiões, pacotes-modelo e histórico)
- ✅ Orçamentos e todo o Financeiro (6 abas)
- ✅ Notas fiscais, convênios, profissionais, procedimentos, estoque e contratos
- ✅ Relatórios (5 abas) e exportação CSV
- ✅ Documentos e modelos da clínica
- ✅ Administração (usuários, perfis, clínica e auditoria)
- ✅ Social e notificações
- ✅ Entendimento honesto dos módulos **planejados**: Tratamentos e Turmas de Pilates

### Próximos passos recomendados

1. Praticar no treinamento até fechar `35/35` e acertar os quizzes (≥ 70%).
2. Repetir as tarefas no sistema real com um paciente de teste.
3. Revisar o checklist operacional diário.
4. Tirar dúvidas com o suporte antes de mudar permissões.
5. Enviar sugestões de melhoria pelo painel administrativo.

---

## 📋 Relatório técnico (histórico)

### Versão 1.1.0 — 09/11/2026

| Item | Detalhe |
|------|---------|
| Objetivo | Adequar o sistema à Clínica Daiane (fisioterapia), preservando estabilidade e segurança |
| Catálogo | 4 verticais: odontologia, fisioterapia, pilates e laser |
| Tratamentos | `PLANEJADO` para fisioterapia/pilates/laser |
| Turmas de Pilates | `PLANEJADO` |
| Permissão adicionada | `pacotes.view` (id=71) para os perfis admin (1) e auxiliar (6), clínica 5 |
| Testes | 764 testes, 3106 asserções, 0 falhas |
| Deploy | VPS `179.236.238.61`, branch `release/daiane-vps-20261107`, commit `a537c1b` |
| Backup | `20261109-142351` (69 MB) |
| URL | https://ljaltomao.com.br |

### Versão 1.2.0 — 17/11/2026

| Item | Detalhe |
|------|---------|
| Treinamento | Expandido de 16 para **37 itens** de menu (**35 disponíveis + 2 planejados**) |
| Novos módulos | Atendimentos, Turmas, Avaliação, Evolução, Pacotes, Financeiro completo (6 abas), Notas fiscais, Relatórios (5 abas), Documentos, Administração (4 telas), Social, Notificações |
| Módulo laser | Regiões, pacotes-modelo e histórico, com `regioes_laser.*` |
| Progresso | `0/35`, com desafios e quiz (4 perguntas, 70% para aprovação) |
| Planejados | Tratamentos (`planos`) e Turmas de Pilates (`turmas_pilates`) — fora do total e sem quiz |
| Publicação | GitHub Pages: `treinamento-daiane.html` |

### Pendências

| ID | Item | Situação |
|----|------|----------|
| TRAT-01 | Adaptar a tela de Tratamentos para fisioterapia | 🟸 Planejado |
| PILAT-01 | Tela própria de Turmas de Pilates | 🟸 Planejado |
| FISC-01 | Notas fiscais em produção (certificado + emissor) | ⏳ Pendente |
| LASER-01 | Publicar o módulo laser em produção (pacotes + histórico) | ⏳ Autorizado; aguardando acesso SSH |
| ATEND-01 | Lembretes automáticos de sessão | ⏳ Pendente |

---

**Fim do Manual de Treinamento — LJ SmartClinic** 🎓

*Documento atualizado em 17/11/2026 · Versão 1.2.0 · Sistema LJ SmartClinic*
