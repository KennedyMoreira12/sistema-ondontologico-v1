# Homologação manual — PIL-06 e PE-05

Roteiro para a cliente Daiane validar, na tela, os dois fluxos que fecham a
substituição das planilhas. Os testes automatizados já provam o comportamento;
esta homologação prova que a tela mostra o mesmo resultado para quem opera.

- **Data do roteiro:** 09/11/2026
- **Branch:** `feature/daiane-planilha-substituicao`
- **Base automatizada:** commit local desta entrega (sem push e sem deploy)
- **Ambiente:** clínica de demonstração/local. **Não usar a base real da
  Malheiro (`clinic_000001`) nem a VPS.**
- **Perfis necessários:** um usuário com `pacotes.view`, `pacotes.update`,
  `relatorios.view`, `profissionais.view`, `profissionais.create`,
  `financeiro.view`, `financeiro.create` e `financeiro.receive`.

> Nada neste roteiro grava em banco de produção. Todos os passos são feitos em
> uma clínica de teste, com dados fictícios.

---

## 1. PIL-06 — lista de presença da campanha Outubro Rosa

**Origem na planilha:** `PGTO PILATES.xlsx` / aba `LISTA OUTUBRO ROSA`.
A aba é uma lista esparsa de presença: cada linha é um paciente e o que importa
é quem compareceu à campanha. No sistema, a presença é o **consumo de sessão**
do paciente, e o relatório de pacotes lista um consumo por paciente.

### 1.1 Dados de teste

Criar dois pacientes fictícios, por exemplo:

| Paciente | Serviço | Data da sessão |
|---|---|---|
| Outubro Rosa 1 | PILATES | 10/10/2026 08:00 |
| Outubro Rosa 2 | PILATES | 11/10/2026 08:00 |

Cada paciente recebe um pacote de PILATES com 4 sessões e saldo inicial 4.

### 1.2 Passos

1. Acessar **Agenda** e confirmar as duas consultas de PILATES nas datas acima.
2. Abrir cada atendimento e registrar o consumo do pacote correspondente.
3. Acessar **Relatórios → Pacotes** com o período `01/10/2026` a `31/10/2026`.
4. Conferir a lista de consumos do período.

### 1.3 Resultado esperado

- Os dois atendimentos ficam com status **atendido**.
- Cada paciente tem **1 consumo** registrado no período (2 no total).
- O relatório mostra **2 consumos**, de **2 pacientes diferentes**, um por
  paciente — sem linha repetida e sem paciente faltando.
- O saldo de cada pacote cai de 4 para 3.
- A tela não depende de pacote exibido no cadastro do paciente: a lista é
  montada pelos consumos reais do período.

### 1.4 Evidência automatizada

- Teste: `test_campanha_pilates_lista_consumo_por_paciente`
  (`tests/Feature/EquivalenciaPlanilhasDaianeTest.php`).
- O que ele prova: 2 consultas consumidas, 2 pacientes distintos, 2 consumos no
  relatório.

### 1.5 Critério de aprovação

A cliente aprova se o relatório mostrar exatamente os pacientes que
compareceram, com um consumo por paciente, e se o saldo dos pacotes refletir
cada presença. Se a lista da planilha tiver algum nome que não deve entrar na
campanha, a cliente deve dizer antes da aprovação (não inferir pela planilha).

---

## 2. PE-05 — profissionais externos (cadastro e pagamento)

**Origem na planilha:** `PROFISSIONAIS EXTERNOS.xlsx`, uma aba por mês.
A planilha registra a nutricionista **Apoena** por **DIÁRIA** e o dentista por
**PORCENTAGEM**. O sistema cadastra o profissional externo e paga por **conta a
pagar** (dinheiro que sai), com o modelo de remuneração informado.

### 2.1 Dados de teste

| Profissional | Tipo | Registro | Remuneração | Valor do teste |
|---|---|---|---|---|
| Nutricionista Apoena | nutricionista | CRN 12345 | DIÁRIA | R$ 150,00 |
| Dentista Parceiro | dentista | CRO 99999 | PORCENTAGEM | R$ 300,00 |

### 2.2 Passos — cadastro

1. Acessar **Profissionais → Novo**.
2. Cadastrar a nutricionista com tipo **Nutricionista** e registro **CRN 12345**.
3. Cadastrar o dentista com tipo **Dentista** e registro **CRO 99999**.
4. Na listagem, buscar por `Apoena` e conferir nome, tipo e registro.

### 2.3 Resultado esperado — cadastro

- Os dois profissionais aparecem na listagem com o tipo e o registro corretos.
- O tipo **Nutricionista** é aceito pela tela (não é preciso criar cadastro
  paralelo só para a nutricionista).
- O registro no conselho aparece no lugar do CRO quando o tipo não é dentista.

### 2.4 Passos — pagamento

1. Acessar **Financeiro → Contas a pagar → Nova conta**.
2. Lançar a diária da nutricionista: profissional **Apoena**, remuneração
   **DIÁRIA**, valor **R$ 150,00**, vencimento **09/11/2026**.
3. Lançar o repasse do dentista: profissional **Dentista Parceiro**,
   remuneração **PORCENTAGEM**, valor **R$ 300,00**, vencimento **30/11/2026**.
4. Dar baixa nas duas contas informando a forma de pagamento.
5. Na listagem de contas a pagar, filtrar por **Profissional externo = Apoena**
   e depois conferir o painel **Pago a profissionais externos** do mês
   **11/2026**.

### 2.5 Resultado esperado — pagamento

- As duas contas nascem em **Contas a pagar** (saída de caixa), nunca em
  **Contas a receber**.
- Cada conta mostra o profissional e o modelo de remuneração.
- Após a baixa, as contas ficam **pagas** e o saldo zera.
- O filtro por profissional mostra só as contas da Apoena.
- O painel mensal de novembro mostra:
  - Apoena: principal **R$ 150,00**; juros e multa **R$ 0,00**;
  - Dentista Parceiro: principal **R$ 300,00**;
  - a conta de outubro, se houver, **não** aparece no painel de novembro.
- O detalhe da conta exibe o extrato com **principal, juros, multa e total
  desembolsado**.

### 2.6 Evidência automatizada

- `test_profissional_externo_cadastro_e_pagamento` — cadastro pela tela,
  listagem por busca e pagamento via conta a pagar.
- `test_profissional_externo_pagamento_sai_do_caixa` — a saída não é lançada
  como recebimento de paciente.
- `test_profissional_externo_tipo_porcentagem` — o modelo PORCENTAGEM é aceito
  e um modelo inventado é recusado.
- `test_total_mensal_por_profissional_externo` — painel mensal separa principal,
  juros e multa e não mistura meses.

### 2.7 Critério de aprovação

A cliente aprova se os dois profissionais forem cadastrados pela tela e pagos
por conta a pagar, com o modelo de remuneração visível e o total mensal por
profissional correto. **O sistema não calcula a porcentagem nem a diária:** o
valor é sempre o informado pela cliente, porque a regra de cálculo não foi
definida. Se a cliente quiser cálculo automático, isso é uma decisão nova
(registrada em `DECISOES_PENDENTES_CLIENTE.md`), não um defeito deste fluxo.

---

## 3. O que este roteiro NÃO cobre

- **MKT-03** (aba MARKETING): é calendário de conteúdos de WhatsApp, não CRM de
  prospects. Permanece **C** na matriz; o conteúdo é atendido por MKT-05/MKT-06.
- **PAC-10** (deduplicação de pacientes): bloqueado até a cliente entregar
  `Pacientes.xlsx` e definir a regra de deduplicação.
- **DJ-06** (desconto em conta a pagar): bloqueado até a cliente definir a
  regra de desconto.
- **Importação histórica de CONTAS.xlsx:** bloqueada até a cliente definir
  período e conferência. A prévia é somente leitura
  (`php artisan financeiro:preview-contas {arquivo}`) e não grava nada.

---

## 4. Registro da homologação

| Fluxo | Testado em | Resultado | Responsável | Observações |
|---|---|---|---|---|
| PIL-06 | | ☐ aprovado ☐ ajuste | | |
| PE-05 (cadastro) | | ☐ aprovado ☐ ajuste | | |
| PE-05 (pagamento) | | ☐ aprovado ☐ ajuste | | |

A aprovação só vale com o nome de quem testou, a data e a clínica de teste
usada. Enquanto a tabela estiver vazia, os fluxos permanecem **A** por teste
automatizado, mas a homologação manual fica **pendente**.
