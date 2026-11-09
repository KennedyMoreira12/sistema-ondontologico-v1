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
`FinanceHelper->jurosSimples()` e, na baixa da conta a pagar, o campo `juros`
é **informado** por quem faz a baixa. O principal abate o saldo e os juros
ficam registrados no extrato.

**Decisão necessária (opcional):** A cliente quer que o sistema calcule os
juros automaticamente? Se sim, qual a taxa mensal/diária e a partir de quantos
dias de atraso? Se não, o valor informado na baixa já atende.

**Consequência:** Sem a taxa, o sistema não aplica juros automaticamente. O
usuário informa o valor na baixa, como na planilha.

**Comportamento atual testado:** `test_baixa_conta_pagar_com_juros_e_multa_informados`
(fluxos DJ-03/DJ-04, classificados como **A**).

---

### 1.2 Multa por atraso

**Contexto:** Algumas abas da planilha preveem multa por pagamento em atraso.
O sistema possui `FinanceHelper->multa()` e, na baixa da conta a pagar, o campo
`multa` é **informado** por quem faz a baixa, separado dos juros.

**Decisão necessária (opcional):** A cliente quer cálculo automático de multa?
Se sim, qual o percentual e a base de cálculo? Se não, o valor informado já
atende.

**Consequência:** Sem o percentual, o sistema não aplica multa automaticamente;
o valor é informado na baixa.

**Comportamento atual testado:** `test_baixa_conta_pagar_com_juros_e_multa_informados`
(fluxos DJ-03/DJ-04, classificados como **A**).

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

### 1.4 Desconto na conta a pagar

**Contexto:** A planilha CONTAS .xlsx tem uma coluna "JUROS/DESCONTO" por conta
a pagar. A parte de **juros e multa** já está implementada: são informados na
baixa e ficam no extrato (`accounts_payable_pagamentos.juros/multa`), sem taxa
fixa inventada. O que falta é a regra de **desconto** em conta a pagar.

**Decisão necessária:** O desconto em conta a pagar é livre (quem paga digita o
valor) ou segue uma tabela pré-definida? Ele reduz o principal da conta ou é
registrado à parte, como os juros?

**Consequência:** Sem a decisão, o desconto em conta a pagar não é aplicado
pelo sistema; o valor pago é o principal informado na baixa. Juros e multa
continuam funcionando normalmente.

**Comportamento atual testado:** `test_baixa_conta_pagar_com_juros_e_multa_informados`
(fluxos DJ-03/DJ-04, classificados como **A**). O fluxo DJ-06 (desconto)
permanece **D** até esta decisão.

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

**Comportamento atual testado:** `test_reposicao_de_sessao_registrada_e_contada`
(fluxo PRE-05, classificado como **A** na matriz).

---

### 2.2 Modelo de remuneração do profissional externo

**Contexto:** A planilha PROFISSIONAIS EXTERNOS.xlsx tem profissionais com
diferentes modelos de remuneração (ex: nutricionista **DIÁRIA**, dentista
**PORCENTAGEM**). O sistema já possui `professionals.tipo` e, na conta a pagar
do profissional, o campo `tipo_remuneracao` (`diaria`, `porcentagem`,
`valor_fixo`), além do valor informado.

**Decisão necessária (opcional):** A cliente quer que o sistema **calcule** a
diária ou a porcentagem automaticamente? Se sim, qual a base de cálculo e o
percentual de cada profissional?

**Consequência:** Sem a regra de cálculo, o modelo é registrado e o valor é
sempre informado por quem lança a conta. O sistema não inventa percentual nem
diária.

**Comportamento atual testado:** `test_profissional_externo_cadastro_e_pagamento`,
`test_profissional_externo_pagamento_sai_do_caixa`,
`test_profissional_externo_tipo_porcentagem` e
`test_total_mensal_por_profissional_externo`
(fluxos PE-01..PE-05, classificados como **A** na matriz).

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

### 3.3 Importação de pacientes (PAC-10)

**Contexto:** A matriz cita uma planilha de pacientes que **não está no
repositório**. Sem o arquivo não é possível saber colunas, volume nem
duplicidades. Também não há regra definida para o que fazer quando o mesmo
paciente aparece mais de uma vez.

**Decisão necessária:**
1. A cliente deve entregar o arquivo de pacientes (ou confirmar que a base
   histórica já está coberta pelas outras planilhas).
2. Qual a regra de deduplicação? Comparar por nome + telefone, por CPF, por
   data de nascimento, ou revisar caso a caso?
3. Quando dois registros forem considerados a mesma pessoa, qual prevalece?

**Consequência:** Sem o arquivo e sem a regra, nenhuma importação de pacientes
é executada e o fluxo PAC-10 permanece **E** na matriz. Nenhum cadastro é
mesclado automaticamente.

---

## 4. Resumo das decisões e impacto

| Decisão | Impacto se não decidida | Prioridade |
|---------|------------------------|------------|
| Cálculo automático de juros | Juros continuam informados na baixa (já funcional) | Baixa |
| Cálculo automático de multa | Multa continua informada na baixa (já funcional) | Baixa |
| Tabela de desconto à vista | Desconto sempre manual (valor livre) | Média |
| Desconto em conta a pagar | Desconto não aplicado; juros/multa já funcionam | Média |
| Regra de reposição de falta | Reposição é ato explícito, não automático | Alta |
| Cálculo de diária/porcentagem do profissional externo | Modelo e valor informados; sistema não calcula sozinho | Média |
| Validade/bloqueio de pacotes | Pacote expira sem bloqueio automático | Média |
| Alerta aniversariante | Sem notificação, apenas relatório manual | Baixa |
| Importação histórica contas a pagar | Contas a pagar começam do zero no sistema | Alta |
| Uso de pacotes não-FT | Recurso disponível, uso a critério da cliente | Média |
| Importação/deduplicação de pacientes (PAC-10) | Fluxo permanece E; nenhum cadastro é mesclado | Alta |

---

## 5. Como prosseguir

Para cada decisão acima, a cliente deve informar:
1. A regra comercial desejada (valores, percentuais, prazos)
2. Se deseja implementação no sistema ou processo manual
3. Prazo para implementação

Enquanto as decisões não forem tomadas, o sistema mantém o comportamento
explícito e seguro descrito, sem inventar regras.
