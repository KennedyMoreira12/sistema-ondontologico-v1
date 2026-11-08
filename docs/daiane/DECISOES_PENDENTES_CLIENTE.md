# DECISÕES PENDENTE — CLIENTE DAIANE

Este documento lista as decisões de negócio que dependem exclusivamente da
cliente Daiane. O sistema não deve inventar regras comerciais: enquanto a
cliente não confirmar, o comportamento permanece explícito, seguro e
documentado.

---

## 1. Regras financeiras (juros, multa, desconto)

### 1.1 Juros sobre vencimento

**Contexto:** A planilha CONTAS .xlsx aplica juros sobre contas vencidas em
algumas abas (a partir de OUTUBRO 2023). O sistema já possui a função
`FinanceHelper->jurosSimples()` que calcula juros simples proporcionais aos
dias de atraso, dada uma taxa percentual informada pelo usuário.

**Decisão necessária:** Qual a taxa de juros mensal (ou diária) a aplicar?
Exemplos: 2% ao mês? 0.07% ao dia? Juros apenas após X dias de atraso?

**Consequência:** Sem a taxa, o sistema não aplica juros automaticamente. O
usuário pode lançar juros manualmente no campo de observações ou desconto, mas
não há cálculo automático.

---

### 1.2 Multa por atraso

**Contexto:** Algumas abas da planilha preveem multa por pagamento em atraso.
O sistema já possui `FinanceHelper->multa()` que calcula multa sobre um valor,
dado um percentual informado.

**Decisão necessária:** Qual o percentual de multa? Exemplo: 2% sobre o valor
da conta? Multa fixa ou percentual?

**Consequência:** Sem o percentual, o sistema não aplica multa automaticamente.

---

### 1.3 Desconto à vista

**Contexto:** Algumas abas preveem desconto para pagamento à vista. O sistema
já possui o campo `desconto` na tabela `accounts_receivable` e o fluxo de
recebimento agora aceita um valor de desconto informado pelo usuário (veja
`FinanceiroController@receive` + `RecebimentoService`).

**Decisão necessária:** O desconto é livre (usuário digita o valor) ou segue
uma tabela pré-definida (ex: 5% para pagamento à vista, 10% para pagamento
antecipado)?

**Consequência:** Se for tabela pré-definida, é necessário adicionar lógica no
sistema. Se for livre, a implementação atual já atende.

---

### 1.4 Juros/desconto na coluna de contas a pagar

**Contexto:** A planilha CONTAS .xlsx tem uma coluna "JUROS/DESCONTO" por conta
a pagar. O sistema tem `accounts_payable.tipo_documento` e `observacoes`, mas
não um campo dedicado para juros/desconto da conta a pagar.

**Decisão necessária:** O sistema deve ter um campo dedicado `juros_desconto`
na tabela `accounts_payable`, ou o usuário continua usando `observacoes`?

**Consequência:** Sem o campo, o valor de juros/desconto da conta a pagar não é
estruturado, apenas textual.

---

## 2. Regras operacionais

### 2.1 Reposição de falta

**Contexto:** A planilha DEPILAÇÃO LASER.xlsx marca presença (PRESENTE) e
falta (AUSENTE), mas não tem regra explícita de reposição de sessão. O sistema
possui `consumo_sessao.reposicao` (booleano) e a `WaitingList` (lista de
espera), mas não mapeia falta → reposição automática.

**Decisão necessária:** Uma falta gera direito a reposição? Quantas sessões de
reposição? Qual o prazo para usar a reposição? A reposição consome sessão do
pacote ou é gratuita?

**Consequência:** Sem a regra, a reposição é um ato explícito do usuário
(marcar `reposicao=true` no consumo), não automático.

---

### 2.2 Tipo de relacionamento do profissional externo

**Contexto:** A planilha PROFISSIONAIS EXTERNOS.xlsx tem profissionais com
diferentes tipos de relacionamento (ex: nutricionista "DIÁRIA", valor fixo
R$ 150,00 por sessão). O sistema tem `professionals` com campo `tipo` e
`comissao`, mas não diferencia "diária" vs "mensal" vs "por sessão".

**Decisão necessária:** Qual a estrutura de tipos de relacionamento?
Exemplo: diária, mensal, por sessão, por valor fixo?

**Consequência:** Sem a estrutura, o profissional externo é cadastrado como um
profissional comum, com comissão livre. O tipo "diária" não é distinguido.

---

### 2.3 Validade e liberação de pacotes

**Contexto:** O sistema tem `pacotes_sessao.validade_em` (data de validade) e
`PacoteSessao->estaAtivo()` que verifica status + saldo + validade. Pacotes
foram liberados para todas as verticais (removido middleware
`vertical:fisioterapia`).

**Decisão necessária:** O pacote expira automaticamente na data de validade?
Há bloqueio de consumo após o vencimento? O cliente quer pacotes separados por
modalidade (pilates, massagem, fisioterapia) ou um único tipo de pacote?

**Consequência:** Sem decisão, o pacote funciona com validade opcional e
status ativo/concluído/cancelado, sem regras adicionais de expiração.

---

### 2.4 Alerta de aniversariante "TÁ CHEGANDO!"

**Contexto:** A planilha ANIVERSARIANTES.xlsx tem fórmulas `IF(NOW()-DATE(...)
>=-2 ...)` que mostram "TÁ CHEGANDO!" para aniversários em até 2 dias. O
sistema agora tem um relatório de aniversariantes
(`relatorios.aniversariantes`) com sinalizador "chegando", mas não dispara
alertas nem notificações automáticas.

**Decisão necessária:** A cliente deseja alerta automático (WhatsApp, e-mail,
tela do dashboard) para aniversariantes próximos? Ou apenas consulta o
relatório manualmente?

**Consequência:** Sem a decisão, não há notificação automática. A cliente
consulta o relatório quando quiser.

---

## 3. Dados históricos

### 3.1 Migração de contas a pagar (CONTAS .xlsx)

**Contexto:** A tabela `accounts_payable` já existe no sistema (migration
`2026_11_05_081450_create_accounts_payable_tables.php`), com telas de
cadastro, listagem, baixa, cancelamento e idempotência. Porém, os dados
históricos de CONTAS .xlsx (50 abas, 2023-2026) não foram importados.

**Decisão necessária:** A cliente deseja importar os lançamentos históricos de
contas a pagar? Qual o período (todos os meses ou apenas em aberto)?
Quem fará a conferência dos valores importados?

**Consequência:** Sem a importação, as contas a pagar históricas permanecem
apenas na planilha. O sistema começa do zero para contas novas.

---

### 3.2 Pacotes de pilates/massagem fora da vertical fisioterapia

**Contexto:** As rotas de pacotes foram liberadas para todas as verticais
(removido middleware `vertical:fisioterapia`). Agora clínicas odontológicas
(pilates, massagem) podem criar e consumir pacotes de sessão.

**Decisão necessária:** A cliente confirma que deseja usar pacotes de sessão
para pilates e massagem, não apenas para fisioterapia? Há alguma restrição
de uso por modalidade?

**Consequência:** Sem confirmação, o recurso está disponível mas pode não ser
utilizado pela cliente.

---

## 4. Resumo das decisões e impacto

| Decisão | Impacto se não decidida | Prioridade |
|---------|------------------------|------------|
| Taxa de juros | Sem cobrança automática de juros | Alta |
| Percentual de multa | Sem cobrança automática de multa | Alta |
| Tabela de desconto | Desconto sempre manual (valor livre) | Média |
| Campo juros/desconto em contas a pagar | Valor apenas em observações | Média |
| Regra de reposição de falta | Reposição é ato explícito, não automático | Alta |
| Tipo de profissional externo | Sem diferenciação diária/mensal/por sessão | Média |
| Validade/bloqueio de pacotes | Pacote expira sem bloqueio automático | Média |
| Alerta aniversariante | Sem notificação, apenas relatório manual | Baixa |
| Importação histórica contas a pagar | Contas a pagar começam do zero no sistema | Alta |
| Uso de pacotes não-FT | Recurso disponível, uso a critério da cliente | Média |

---

## 5. Como prosseguir

Para cada decisão acima, a cliente deve informar:
1. A regra comercial desejada (valores, percentuais, prazos)
2. Se deseja implementação no sistema ou processo manual
3. Prazo para implementação

Enquanto as decisões não forem tomadas, o sistema mantém o comportamento
explícito e seguro descrito, sem inventar regras.
