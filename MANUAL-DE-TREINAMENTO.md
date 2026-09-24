# 🦷 Sistema de Gestão Odontológica — Manual de Treinamento

> Material didático para capacitação funcional de todos os módulos do sistema.
> Objetivo: ensinar, com exemplos práticos e passo a passo, como operar cada área da aplicação.

---

## Sumário

1. [Sobre o sistema](#1-sobre-o-sistema)
2. [Acesso e primeira navegação](#2-acesso-e-primeira-navegação)
3. [Perfis de usuário e permissões](#3-perfis-de-usuário-e-permissões)
4. [Dashboard — Visão geral](#4-dashboard--visão-geral)
5. [Pacientes](#5-pacientes)
6. [Prontuário clínico](#6-prontuário-clínico)
7. [Agenda](#7-agenda)
8. [Odontograma](#8-odontograma)
9. [Planos de tratamento](#9-planos-de-tratamento)
10. [Orçamentos](#10-orçamentos)
11. [Financeiro](#11-financeiro)
12. [Convênios](#12-convênios)
13. [Profissionais](#13-profissionais)
14. [Procedimentos](#14-procedimentos)
15. [Estoque](#15-estoque)
16. [Contratos](#16-contratos)
17. [Relatórios](#17-relatórios)
18. [Administração](#18-administração)
19. [API WhatsApp](#19-api-whatsapp)
20. [Fluxos de trabalho do dia a dia](#20-fluxos-de-trabalho-do-dia-a-dia)
21. [Exercícios práticos de treinamento](#21-exercícios-práticos-de-treinamento)

---

## 1. Sobre o sistema

O **Sistema de Gestão Odontológica (SCO)** é uma aplicação web completa para clínicas odontológicas.
Ele reúne todo o ciclo de atendimento em um só lugar:

```
Cadastro do paciente → Prontuário → Odontograma → Plano de tratamento
        → Orçamento → Financeiro → Estoque de materiais
```

### Tecnologias utilizadas

| Camada    | Tecnologia                          |
| --------- | ----------------------------------- |
| Backend   | Laravel (PHP 8.3)                   |
| Frontend  | Blade + Bootstrap + SCSS            |
| Banco     | MySQL |
| Autenticação | Breeze (login, e-mail, redefinição de senha) |

### Conceito importante: permissões

Cada tela do sistema é protegida por **permissões** (ex.: `pacientes.view`, `financeiro.receive`).
Nem todo usuário enxerga tudo: a recepção não vê custos, o financeiro não edita prontuário, etc.
Se uma opção de menu não aparece para você, verifique o **perfil** do seu usuário (seção 18).

---

## 2. Acesso e primeira navegação

### Como entrar

1. Abra o endereço do sistema no navegador.
2. Informe seu **e-mail** e **senha** na tela de login.
3. Clique em **Entrar**.

> Acesso do administrador criado no seed (ambiente de desenvolvimento):
>
> - E-mail: `admin@sco.com`
> - Senha: `admin123`

> **Contas de demonstração** (criadas pelo `DemoSeeder`):
>
> | Perfil | E-mail | Senha |
> | --- | --- | --- |
> | Administrador | `admin@sco.com` | `admin123` |
> | Dentista | `dentista@sco.com` | `dentista123` |
> | Recepção | `recepcao@sco.com` | `recepcao123` |
> | Financeiro | `financeiro@sco.com` | `financeiro123` |

> **Dados de exemplo:** o `DemoSeeder` popula o ambiente com pacientes fictícios,
> agenda preenchida, planos de tratamento, orçamentos, contas a receber, contratos,
> odontograma e estoque — use para apresentar o sistema. Para restaurar, rode:
>
> ```
> php artisan db:seed
> ```

### Estrutura da tela

- **Menu lateral (esquerda):** todos os módulos, organizados em *Principal*, *Gestão* e *Administração*.
- **Barra superior (topo):** título da tela, menu do usuário (Meu perfil e Sair).
- **Conteúdo (centro):** a área de trabalho do módulo ativo.

### Barra superior — menu do usuário

- **Meu perfil:** alterar nome, e-mail, senha e excluir a conta.
- **Sair:** encerra a sessão com segurança.

> **Dica de treino:** sempre confira o rótulo do seu perfil no menu do usuário
> (ex.: "Administrador", "Dentista") para saber quais permissões você tem.

---

## 3. Perfis de usuário e permissões

O sistema vem com **6 perfis prontos**. Cada um concentra as permissões típicas do papel:

| Perfil        | O que pode fazer de forma resumida |
| ------------- | ---------------------------------- |
| **Administrador** | Tudo, incluindo usuários, perfis, clínica e auditoria. |
| **Dentista**      | Prontuário, odontograma, anamneses, alertas, tratamentos, prescrições, agenda e orçamentos. |
| **Recepção**      | Cadastro de pacientes, agenda, convênios, orçamentos, financeiro, contratos e estoque (visual). |
| **Financeiro**    | Contas a receber, recebimentos, orçamentos e relatórios. |
| **Estoque**       | Produtos, movimentações e relatórios. |
| **Auxiliar**      | Visualização de pacientes, agenda e prontuário. |

As permissões são feitas de **módulo + ação** (ex.: `estoque.update`, `orcamentos.create`) e podem ser ajustadas no painel **Administração → Perfis** (seção 18).

---

## 4. Dashboard — Visão geral

Primeira tela após o login. Apresenta os principais indicadores da clínica:

| Indicador                  | Significado                                         |
| -------------------------- | --------------------------------------------------- |
| **Consultas hoje**         | Agenda do dia com horário, paciente, profissional e status. |
| **Pacientes cadastrados**  | Total de fichas de pacientes.                       |
| **Tratamentos em andamento** | Planos aprovados/em execução.                      |
| **Produtos com estoque baixo** | Itens que atingiram ou passaram do estoque mínimo. |
| **Contas a receber em aberto** | Saldos devedores dos pacientes (até 8 em destaque). |

**Uso prático:** é o ponto de partida do dia. A recepção confere as consultas,
o dentista identifica tratamentos em andamento e o financeiro acompanha os saldos.

---

## 5. Pacientes

**Menu:** *Pacientes* · **Permissão base:** `pacientes.*`

### 5.1. Listagem e busca

- A tela lista os pacientes em **ordem alfabética**, 15 por página.
- Filtros disponíveis:
  - **Busca (q):** nome, CPF, e-mail, telefone fixo ou celular.
  - **Status:** filtra por ativo/inativo.
- Cada linha mostra o total de **consultas** e **orçamentos** do paciente.

### 5.2. Cadastro (Novo paciente)

1. Clique em **Novo paciente**.
2. Preencha os dados:
   - *Obrigatório:* **nome**.
   - *Importantes:* CPF (único), e-mail, telefone e celular (telefone e celular são usados pela API WhatsApp).
   - *Endereço:* CEP, rua/endereço, número, complemento, bairro, cidade e estado (UF).
   - *Dados complementares:* RG, data de nascimento, sexo, responsável (ex.: pai/mãe no caso de criança) e observações.
   - *Status:* **ativo** (padrão) ou **inativo**.
3. Salvar. O sistema grava um **log de auditoria** da operação e abre a ficha do paciente.

### 5.3. Ficha do paciente (`Pacientes → nome do paciente`)

Visão rápida do paciente com:

- **Últimas consultas** (até 5).
- **Alertas clínicos** ativos.
- **Documentos** anexados.
- **Anamneses** registradas.
- **Planos de tratamento** (últimos 5).
- **Orçamentos** (últimos 5).

### 5.4. Editar e excluir

- **Editar:** altera os dados; o sistema registra o antes/depois na auditoria.
- **Excluir:** não apaga o registro — ele é arquivado com **soft delete** e marcado como **inativo**. Os dados clínicos continuam preservados.

> **Regra de negócio:** o CPF é único. Tentar cadastrar CPF repetido é bloqueado pelo sistema.

---

## 6. Prontuário clínico

**Acesso:** *Pacientes → ficha do paciente → aba Prontuário* · **Permissão base:** `prontuario.*`

O prontuário reúne toda a documentação clínica do paciente, em ordem cronológica (mais recente primeiro).

### 6.1. Evoluções clínicas

1. Na tela do prontuário, escolha o **tipo** (Evolução por padrão).
2. Digite o **conteúdo** (relato da consulta, procedimento realizado, intercorrências).
3. Salvar. O profissional logado aparece como autor automaticamente.

### 6.2. Anamneses

- Estrutura de **pergunta: resposta** para registrar o histórico do paciente.
- Você pode digitar uma linha por vez ou colar várias linhas no formato:

```
Alergia a medicamentos: Penicilina
Doença pré-existente: Diabetes tipo 2
Faz uso contínuo de: Anticoagulante
```

- O sistema converte o texto em respostas estruturadas e associa o profissional responsável.

### 6.3. Alertas clínicos

- Usados para situações que **não podem passar despercebidas** (alergias, diabetes, medicações).
- Cadastrar: título/descrição do alerta + classificação.
- Os alertas ficam visíveis na ficha do paciente.
- **Desativar:** o alerta deixa de aparecer como ativo (não é apagado).

### 6.4. Prescrições

**Acesso:** *Pacientes → ficha → Prescrições*

1. Data da prescrição (padrão hoje).
2. Observações gerais.
3. Adicione os **medicamentos** com: nome, dose, frequência, duração e observação.
   - Cada linha sem medicamento é ignorada automaticamente.
4. Salvar. A prescrição fica vinculada ao paciente e ao profissional logado.

> **Dica:** para prescrever vários itens de uma vez, preencha todas as linhas da tela antes de salvar.

---

## 7. Agenda

**Menu:** *Agenda* · **Permissão base:** `agenda.*`

### 7.1. Grade semanal

- A agenda mostra de **segunda a sábado**, em blocos de **30 minutos (7h às 21h)**.
- Use a data de navegação para mudar de semana.
- Filtro por **profissional**.
- Cada consulta aparece no horário, com paciente e status colorido.

### 7.2. Agendar consulta

1. Clique no botão de novo agendamento.
2. Informe:
   - **Paciente** (lista de ativos), **profissional**, **sala** (opcional).
   - **Data** e **hora de início** + **duração** (o fim é calculado automaticamente).
   - **Status** (padrão: Agendado).
   - **Título** e **observação** (opcional).
   - **Retorno** (marque se for retorno de tratamento).
3. Salvar. O sistema volta para o dia da consulta na agenda.

### 7.3. Editar / cancelar

- **Editar:** altera paciente, profissional, sala, horário, duração, status, título, observação e retorno.
- **Cancelar (excluir):** a consulta **não é apagada** — recebe o status **Cancelado**. Isso preserva o histórico e libera o horário na grade.

### 7.4. Lista de espera

- Cadastre pacientes que aguardam vaga (prioridade + período + motivo).
- Os registros **aguardando** aparecem na coluna lateral, ordenados por prioridade.
- Atualize o **status** conforme o paciente é chamado ou desiste; remova quando não for mais necessário.

### Status de consulta (catalogo)

| Status | Cor | Uso |
| --- | --- | --- |
| Agendado | Azul | Consulta marcada. |
| Confirmado | Verde | Paciente confirmou. |
| Em atendimento | Amarelo | Paciente está no atendimento. |
| Atendido | Roxo | Atendimento concluído. |
| Faltou | Vermelho | Paciente não compareceu. |
| Cancelado | Cinza | Consulta desmarcada. |

---

## 8. Odontograma

**Acesso:** *Pacientes → ficha → Odontograma* · **Permissão base:** `odontograma.*`

O odontograma registra a condição de cada dente (diagrama digital).

### Como marcar um dente

1. Clique em um dente do diagrama.
2. Selecione a **condição** (ex.: Cariado, Restaurado, Coroa, Implante...).
3. Preencha **observação** (opcional).
4. Salvar — a marca é registrada com o profissional logado.

> **Limpar marca:** remova a condição do dente, deixando-o **Hígido** (sadio).

### Condições disponíveis (catalogo)

| Condição | Cor |
| --- | --- |
| Hígido | Verde |
| Cariado | Vermelho |
| Restaurado | Azul |
| Ausente | Cinza |
| Coroa | Laranja |
| Implante | Roxo |
| Tratamento de canal | Amarelo |
| Fraturado | Rosa |

> O sistema cria automaticamente um **odontograma principal** primeiro "ativo" daquele paciente.

---

## 9. Planos de tratamento

**Acesso:** *Pacientes → ficha → Planos/Tratamentos* · **Permissão base:** `tratamentos.*`

O plano de tratamento organiza os procedimentos que o paciente precisa fazer.

### 9.1. Criar plano

1. Clique em criar plano.
2. Informe **título**, **descrição** e **desconto** (opcional).
3. Salvar — o plano nasce como **Rascunho**, vinculado ao profissional logado.

### 9.2. Adicionar procedimentos (itens)

Na tela do plano:

1. Escolha o **procedimento** do catálogo.
2. Informe: **dente** (opcional), **quantidade**, **valor unitário** (pré-preenchido com o valor padrão) e **desconto**.
3. Salvar. O item adicionado mostra: procedimento, dente, valores e **status** (Planejado/Executado).

### 9.3. Status do plano e dos itens

| Status do plano | Significado |
| --- | --- |
| Rascunho | Em montagem, ainda não apresentado. |
| Aprovado | Paciente aceitou o plano. |
| Em andamento | Atendimentos em execução. |
| Concluído | Tratamento finalizado. |
| Cancelado | Não será executado. |

**Comportamento automático:**
- Ao marcar **Concluído**, todos os itens planejados viram **Executados**.
- O **total** é recalculado a cada mudança: soma dos itens − desconto do plano (nunca negativo).
- Itens podem ser marcados como **executado/planejado** individualmente por um botão na listagem.

> **Exemplo didático:** plano com 2 itens de R$ 100 e desconto de R$ 50 → total = R$ 150.
> Executou 1 item? Marque-o como Executado. Terminou tudo? Marque o plano como Concluído.

---

## 10. Orçamentos

**Acesso:** *Pacientes → ficha → Orçamentos* · **Permissão base:** `orcamentos.*`

O orçamento é a proposta financeira apresentada ao paciente — e é o passo antes do financeiro.

### 10.1. Criar orçamento

1. Informe **título** (obrigatório), **validade** (data, opcional) e **observações**.
2. Salvar — nasce como **Rascunho**, totalizador zerado.

### 10.2. Montar itens

Igual ao plano de tratamento:
- Selecione o **procedimento**, **dente** (opcional), **quantidade**, **valor unitário** (preço padrão sugerido) e **desconto** por item.
- O sistema calcula **subtotal** (soma dos itens) e **total** (subtotal − desconto do orçamento, nunca negativo).

### 10.3. Status do orçamento

| Status | Significado |
| --- | --- |
| Rascunho | Em montagem. |
| Emitido | Apresentado ao paciente. |
| Aprovado | Aceito → base para o financeiro. |
| Recusado | Recusado pelo paciente. |
| Cancelado | Proposta retirada. |

> **Fluxo natural:** *Rascunho → Emitido → Aprovado →* geração de contas a receber e recebimentos (seção 11).

---

## 11. Financeiro

**Menu:** *Financeiro* · **Permissão base:** `financeiro.*`

Controle das **contas a receber** (contas criadas a partir de orçamentos apoiados em valor do tratamento).

### 11.1. Listagem de contas

- Ordenação inteligente: **abertas primeiro**, depois **parciais**, e por fim pagas; dentro disso, por vencimento.
- Filtros: **busca por paciente** e **status** (aberta / parcial / paga).
- A tela mostra o **total em aberto** da clínica.

### 11.2. Registrar recebimento

1. Na conta desejada, clique em receber/lançar pagamento.
2. Informe: **valor**, **forma de pagamento** (Dinheiro, PIX, Cartão, Boleto, Convênio), **data** (padrão hoje) e **observações**.
3. Salvar.

**Regras automáticas:**
- O valor informado **não pode ultrapassar o saldo devedor** (o sistema bloqueia e explica).
- Pagamento parcial → conta muda para **Parcial**.
- Pagamento total → conta fica **Paga**.
- Cada recebimento gera um **pagamento** histórico, com usuário responsável registrado.

> **Exemplo didático:** conta de R$ 300.
> Receba R$ 100 (PIX) → status *Parcial*, saldo R$ 200.
> Receba R$ 200 (Dinheiro) → status *Pago*.

---

## 12. Convênios

**Menu:** *Convênios* · **Permissão base:** `convenios.*`

Cadastro das **operadoras** de convênio (ex.: OdontoSaúde) que a clínica atende.

### Operações

- **Listar:** busca por nome ou CNPJ; filtro por ativo/inativo; mostra quantos planos cada operadora tem.
- **Cadastrar:** nome (obrigatório), CNPJ, e-mail, telefone, responsável e status ativo.
- **Editar / Excluir:** manter o cadastro atualizado; exclusão simples.

> **Dica:** vincule as **formas de pagamento** (seção 11) usando "Convênio" para recebimentos via operadora.

---

## 13. Profissionais

**Menu:** *Profissionais* · **Permissão base:** `profissionais.*`

Cadastro da equipe da clínica.

### Campos importantes

- **Nome** (obrigatório), CPF, **CRO** (registro do dentista).
- **Tipo:** Dentista / Auxiliar / Recepcionista / Outro.
- **Especialidades:** associe uma ou mais (Clínica geral, Endodontia, Cirurgia, Periodontia, Ortodontia, Implantes, Odontopediatria).
- **Comissão (%):** percentual de comissão sobre procedimentos.
- **Status ativo/inativo.**

### Por que é importante

- Profissionais são usados em: **agenda** (escala), **prontuário/evoluções** (autor), **planos** e **orçamentos** (responsável técnico).
- A **API do WhatsApp** usa a lista de profissionais ativos para oferecer horários.

---

## 14. Procedimentos

**Menu:** *Procedimentos* · **Permissão base:** `procedimentos.*`

Catálogo de serviços oferecidos pela clínica (Tabela de procedimentos).

### Campos

- **Código** (único, ex.: `CLIN_GER`, `RES_AME`) e **nome** (obrigatório).
- **Categoria** (ex.: Prevenção, Consulta, Exame, Endodontia, Restauradora, Cirurgia, Prótese, Estética) — usada como filtro na listagem.
- **Duração (min)** — usada para calcular o fim da consulta na agenda.
- **Valor padrão (R$)** — sugerido automaticamente em planos e orçamentos.
- **Descrição** e **status ativo**.

> **Fluxo:** o procedimento alimenta **planos de tratamento** e **orçamentos**, que por sua vez geram as **contas a receber**.

---

## 15. Estoque

**Menu:** *Estoque* · **Permissão base:** `estoque.*`

Controle de materiais, insumos e produtos da clínica.

### 15.1. Produtos

- **Cadastro:** nome (obrigatório), código (único), unidade, **categoria**, **fornecedor**, estoque inicial, **estoque mínimo**, custo, preço, validade e lote.
- **Controle de estoque:** se marcado, o sistema acompanha a quantidade e alerta quando `estoque ≤ estoque mínimo`.
- **Busca** por nome/código, filtro por categoria e atalho **"Estoque baixo"**.

### 15.2. Movimentações (entrada/saída)

1. No produto, acesse *movimento*.
2. Informe **tipo** (Entrada ou Saída) e **quantidade** (> 0).
3. Opcional: **motivo** (ex.: "Compra ao fornecedor", "Uso em consulta").
4. Salvar.

**Regras:**
- A **saída não pode ser maior que o saldo atual** (o sistema bloqueia).
- Cada movimento grava o **saldo anterior e posterior**, o custo do produto e o **usuário** responsável — histórico completo.

> **Exemplo didático:** produto com estoque 10 e mínimo 5.
> Saída de 4 → saldo 6 (ok). Saída de 7 → bloqueado (maior que 10? não — 7 ≤ 10 ok, sobra 3). Nova entrada de 5 → saldo 8.
> *Anota:* qualquer saída que deixe o saldo ≤ mínimo acende o alerta de estoque baixo no dashboard.

---

## 16. Contratos

**Menu:** *Contratos* · **Permissão base:** `contratos.*`

Controle de **contratos e termos** assinados com os pacientes (termo de tratamento, consentimentos, etc.).

### Operações

- **Listar:** busca pelo nome do paciente e filtro por status (Pendente / Assinado / Cancelado).
- **Cadastrar:** paciente (obrigatório), **tipo** (ex.: Termo de consentimento), **conteúdo** (texto do contrato) e **status**.
  - *Assinado em:* quando o status for Assinado, informe a data da assinatura.
- **Editar / Excluir** para manutenção.

> **Dica documental:** use o conteúdo em texto puro — linhas e parágrafos são preservados.

---

## 17. Relatórios

**Menu:** *Relatórios* · **Permissão base:** `relatorios.view`

Central de indicadores por **período** (início padrão: primeiro dia do mês; fim: hoje).

### Filtros de período e status

- **De / Até:** define o intervalo de tudo que será analisado.
- **Status do financeiro** e **status da agenda** para afunilar as listagens.

### Blocos apresentados

| Bloco | O que mostra |
| --- | --- |
| **Resumo financeiro** | Total a receber, em aberto, pago e **recebido no período**. |
| **Contas a receber** | Contas com vencimento no período, com saldo. |
| **Resumo da agenda** | Total, atendidas, canceladas e faltas no período. |
| **Consultas** | Lista detalhada das consultas do período. |
| **Estoque baixo** | Produtos que atingiram/ultrapassaram o mínimo. |
| **Movimentações** | Entradas/saídas registradas no período (até 100). |
| **Resumo do estoque** | Total de produtos, baixos, e soma de entradas/saídas. |

> **Uso gerencial:** acompanhar o caixa do mês (recebido no período), taxas de falta
> (agenda) e reposição de materiais (estoque baixo).

---

## 18. Administração

**Menu (só para quem tem permissão):** *Administração* · **Permissão base:** `admin.*` e `auditoria.view`

### 18.1. Usuários

- **Listar:** usuários ativos/inativos com perfil.
- **Criar:** nome, e-mail, senha, perfil (papel) e clínica.
- **Editar:** mudar nome, e-mail, perfil e ativar/inativar.
- **Excluir:** remove o acesso. **Você não pode excluir a si mesmo** (proteção do sistema).

### 18.2. Perfis

- Lista de perfis com contagem de **permissões** e **usuários**.
- **Editar perfil:** altere o nome do perfil e marque/desmarque as **permissões** por módulo (Pacientes, Prontuário, Agenda, Financeiro...).
- Ao salvar, as permissões são aplicadas a **todos** os usuários daquele perfil.

### 18.3. Permissões

Tela de consulta do catálogo completo de permissões (`módulo.ação`), agrupadas por módulo.

### 18.4. Clínica

Dados institucionais da clínica: nome, razão social, documento (CNPJ), contatos, WhatsApp e endereço completo.
Esses dados aparecem no sistema e são usados pela **API WhatsApp** (informações da clínica).

### 18.5. Auditoria

Registro histórico de **todas as operações** importantes do sistema:

- **Quem** (usuário) · **o que** (ação: criar, atualizar, excluir, marcar_dente, registrar_pagamento...) · **em que tabela** · **quando**.
- Guarda também valores **antes/depois** e o **IP** de origem.
- Filtros por **ação** e **tabela**.

> **Exemplo didático:** abrir Auditoria e filtrar `tabela = patients` (tabela `pacientes`)
> mostra todos os cadastros/alterações/exclusões de pacientes e quem fez.

---

## 19. API WhatsApp

**Base:** `/api/whatsapp` · **Autenticação:** cabeçalho de API key (`WhatsAppApiAuth`)

Interface para **bots de WhatsApp** conversarem com o sistema e realizarem atendimento automatizado.

### Endpoints disponíveis

| Método | Rota | Função |
| --- | --- | --- |
| GET | `/api/whatsapp/info` | Dados da clínica e status de consulta. |
| GET | `/api/whatsapp/profissionais` | Profissionais ativos com especialidades. |
| GET | `/api/whatsapp/paciente` | Busca paciente por telefone. |
| POST | `/api/whatsapp/paciente` | Encontra ou **cria** paciente por nome+telefone. |
| GET | `/api/whatsapp/disponibilidade` | Horários livres por profissional nos próximos N dias (seg–sáb, 7h–21h, blocos de 30 min). |
| POST | `/api/whatsapp/agendamento` | Cria um agendamento (com **verificação de conflito**). |
| GET | `/api/whatsapp/agendamentos` | Agendamentos futuros do paciente (por telefone). |
| POST | `/api/whatsapp/agendamento/{consulta}/confirmar` | Confirma a consulta. |
| POST | `/api/whatsapp/agendamento/{consulta}/cancelar` | Cancela a consulta. |

### Fluxo típico do bot

```
1. Paciente envia "olá" → bot responde com info da clínica.
2. Bot pergunta especialidade → chama /profissionais e mostra opções.
3. Paciente escolhe dia/horário → /disponibilidade retorna horários livres.
4. Bot confirma → /agendamento cria a consulta (409 se conflito).
5. Antes do dia → paciente confirma ou cancela via /agendamento/{id}/confirmar|cancelar.
```

> A busca por paciente usa os **últimos 9 dígitos** do telefone (ignora DDI/DDD com máscara).
> Todo agendamento criado/cancelado via API também entra na **auditoria**.

---

## 20. Fluxos de trabalho do dia a dia

### Fluxo A — Recepção: paciente novo + primeira consulta

1. **Pacientes → Novo paciente** → cadastrar dados.
2. **Agenda → novo agendamento** → marcar com dentista, sala, dia e hora.
3. Paciente chegou → alterar status da consulta para **Confirmado** → **Em atendimento**.
4. Após a consulta → **Atendido** (ou Faltou/Cancelado, se aplicável).

### Fluxo B — Dentista: atendimento completo

1. Abrir ficha do paciente → **Prontuário** → registrar **evolução clínica**.
2. Marcar situação dos dentes no **Odontograma**.
3. Cadastrar **alerta** se houver condição relevante (alergia, diabetes).
4. Montar **Plano de tratamento** (com procedimentos e valores).
5. Apresentar **Orçamento** e registrar status (Emitido → Aprovado).
6. Se necessário, emitir **prescrição**.

### Fluxo C — Financeiro: do orçamento ao caixa

1. Orçamento **Aprovado** gera **conta a receber**.
2. **Financeiro** → receber pagamento (parcial ou total) e escolher a forma (PIX, cartão...).
3. Acompanhar **Relatórios** → resumo financeiro do mês.

### Fluxo D — Compras / Estoque

1. **Estoque → Novo produto** com estoque mínimo.
2. Chegou mercadoria → **movimento de Entrada** (motivo: compra).
3. Consulta usa material → **movimento de Saída** (motivo: uso clínico).
4. Dashboard/Relatórios apontam **estoque baixo** → nova compra.

---

## 21. Exercícios práticos de treinamento

> Roteiro para aplicar na capacitação. Faça com o usuário **admin** e depois com cada perfil.

### Exercício 1 — Pacientes e prontuário
1. Cadastre um paciente "Treinamento" com CPF fictício.
2. Edite adicionando um celular com DDD.
3. Registre 2 anamneses, 1 alerta (ex.: alergia) e 1 documento.
4. No prontuário, registre uma evolução e uma prescrição com 2 medicamentos.
5. Confira a auditoria: encontre as ações que você acabou de fazer.

### Exercício 2 — Agenda e lista de espera
1. Agende uma consulta para amanhã com "Limpeza" e marque **retorno**.
2. Altere o horário da consulta.
3. Adicione um paciente fictício à **lista de espera** e depois atualize o status.
4. Cancele a consulta e observe que ela não some da grade (status Cancelado).

### Exercício 3 — Odontograma e plano de tratamento
1. Marque no odontograma os dentes 16 (Cariado) e 26 (Restaurado).
2. Crie um **plano de tratamento** "Reabilitação" com 3 procedimentos.
3. Ajuste um valor unitário e um desconto de item; confira o total recalculado.
4. Marque 1 item como **Executado** e depois conclua o plano.

### Exercício 4 — Orçamento e financeiro
1. Crie um orçamento "Tratamento estético", adicione 2 procedimentos.
2. Emita e depois **aprove** o orçamento.
3. No financeiro, registre um recebimento **parcial** e confira o saldo.
4. Tente receber valor maior que o saldo → veja o bloqueio.
5. Receba o restante e confira a conta **Paga**.

### Exercício 5 — Estoque
1. Cadastre um produto com estoque 10 e mínimo 5.
2. Registre uma **saída** de 4 e depois uma **entrada** de 10.
3. Tente registrar saída maior que o saldo → veja o bloqueio.
4. Confirme o histórico de movimentações no Relatório.

### Exercício 6 — Administração
1. Crie um usuário com perfil **Recepção**.
2. Faça login com ele e compare os menus com o admin.
3. No perfil **Recepção**, remova `agenda.delete` e verifique o efeito ao tentar cancelar consulta.
4. Atualize a página da **Clínica** (contatos/WhatsApp) e valide na API WhatsApp (`/info`).

---

> **Fim do manual.** Qualquer dúvida sobre um módulo, consulte a seção de Administração → Auditoria
> para entender o que foi feito no ambiente de treino.