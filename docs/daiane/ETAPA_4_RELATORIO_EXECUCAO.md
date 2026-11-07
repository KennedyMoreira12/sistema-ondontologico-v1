# ETAPA 4 — Contas a Pagar, Fornecedores e Financeiro

## 4.1 Objetivo
Substituir as abas de CONTAS e PAGAMENTOS por telas nativas: contas a pagar com baixa por pagamento, fornecedores, contas a receber, revisão dos pagamentos importados, duplicatas de nomes e fluxo de caixa.

## 4.2 Implementação das Telas e Rotas

### 4.2.1 Contas a pagar (`financeiro/contas-pagar/`)
- Listagem com filtros por busca, status (`pendente`, `atrasado`, `pago`, `cancelado`), tipo e fornecedor.
- Resumo de em aberto, pago, atrasado e vencendo em sete dias.
- Cadastro e edição com nome da conta, valor, vencimento, fornecedor, categoria, tipo, documento, descrição e observações.
- Página de detalhe com extrato de baixas.
- Baixa parcial ou total em `accounts_payable_pagamentos`, com `idempotency_key` para não duplicar o lançamento.
- O status gravado é apenas `pendente`, `pago` ou `cancelado`; “atrasado” é calculado pela data de vencimento e pelo saldo.
- Conta com baixas não pode ser cancelada; o valor não pode ser reduzido abaixo do já pago.

### 4.2.2 Fornecedores (`financeiro/fornecedores/`)
- Listagem com busca e situação (ativo/inativo).
- Cadastro e edição com nome, CPF/CNPJ, contato, telefone, e-mail, endereço, documento padrão (`NF`, `boleto`, `duplicata`, `outros`) e observações.
- Fornecedor com conta a pagar vinculada não pode ser excluído.
- A tela não emite documento fiscal: o campo de documento é apenas classificação.

### 4.2.3 Pagamentos e recebimentos (`financeiro/pagamentos/`)
- Listagem de pagamentos com filtro por serviço, forma e período.
- Lançamento manual: cria o pagamento e a conta a receber já quitada na mesma transação.
- **Revisão de lançamentos importados:** pagamentos antigos sem paciente aparecem como “não vinculado” e podem ser associados a um paciente existente. O vínculo nunca é automático e não sobrescreve vínculo já existente.
- O comando `financeiro:vincular-pagamentos` faz o mesmo em lote, com `--dry-run` por padrão e `--apply` explícito.

### 4.2.4 Contas a receber (`financeiro/`)
- Listagem de contas a receber com busca por paciente e filtro por status.
- Recebimento passa por `RecebimentoService`, em transação, com bloqueio da conta e vínculo do pagamento ao paciente.
- Recusa pagamento acima do saldo devedor.

### 4.2.5 Duplicatas de pacientes (`financeiro/duplicatas/`)
- Mostra grupos de nomes semelhantes (por exemplo, GISELE / GISELE ALVES / GISELE LVES).
- Serve para revisão humana: a tela **não** une cadastros automaticamente.

### 4.2.6 Fluxo de caixa (`financeiro/fluxo-caixa.blade.php`)
- Período padrão do mês corrente.
- Entradas = pagamentos recebidos; saídas = baixas de contas a pagar.
- Não inclui conta em aberto, porque previsão não é caixa.

## 4.3 Rotas Adicionadas

| Rota | Controller |
|------|-----------|
| `financeiro.contas-pagar.index/create/store/show/edit/update` | `ContaPagarController` |
| `financeiro.contas-pagar.baixar` | `ContaPagarController@baixar` |
| `financeiro.contas-pagar.cancelar` | `ContaPagarController@cancelar` |
| `financeiro.fornecedores.index/create/store/edit/update/destroy` | `FornecedorController` |
| `financeiro.pagamentos.index/store/revisar/vincular/desvincular` | `PagamentoController` |
| `financeiro.duplicatas.index` | `PacienteDuplicataController@index` |
| `financeiro.fluxo-caixa` | `FinanceiroController@fluxoCaixa` |
| `financeiro.index` | `FinanceiroController@index` |
| `financeiro.receive` | `FinanceiroController@receive` |

## 4.4 Migrations e Models

- `2026_11_05_081450_create_accounts_payable_tables.php`: `accounts_payable`, `accounts_payable_pagamentos` e `financeiro_categorias`.
- Models novos: `AccountsPayable`, `AccountsPayablePayment`, `FinancialCategory`.
- `Payment` recebeu `paciente_id`, `servico` e `origem`.
- `Supplier` recebeu `tipo_documento` e `observacoes`.
- `PacoteSessao` ganhou os acessores de saldo usados pelas telas.

## 4.5 Testes Concluídos

| Suíte | Testes | Situação |
|-------|-------:|----------|
| `ContaPagarTest` | 7 | ✅ |
| `PagamentoTest` | 7 | ✅ |
| `FinanceiroTest` | 4 | ✅ |
| **Total** | **18** | **✅** |

Os testes cobrem cadastro e baixa de conta a pagar, baixa acima do saldo, idempotência, atraso calculado, exclusão de fornecedor com conta, vínculo de pagamento importado, duplicatas sem fusão, permissões e recebimento de conta a receber.

## 4.6 Pendências e Validações Humanas
- **Pendência (I):** juros, multa e retenção não foram confirmados pela cliente. Categorias são cadastráveis e os padrões de juros/multa são 0.
- **Pendência (II):** pagamentos importados sem paciente continuam exigindo revisão humana; não há merge automático de nomes.
- **Pendência (III):** a diferença entre 6.174 lançamentos extraídos e 6.163 pagamentos gravados depende do banco original e continua aberta.
- **Validação humana:** a Daiane precisa conferir os totais de contas a pagar e recebimentos contra as planilhas.

## 4.7 Status
**Concluída.** Telas financeiras implementadas, transacionais e cobertas por testes.
