# ETAPA 5 — Relatórios e Controles Administrativos

## 5.1 Objetivo
Reproduzir no sistema os totais e indicadores que a Daiane acompanhava nas planilhas: receitas por serviço e forma de pagamento, presença e faltas, pacotes e sessões, além de exportação em CSV para quem ainda quiser abrir no Excel.

## 5.2 Implementação

### 5.2.1 Relatório geral (`relatorios/index.blade.php`)
- Período padrão do mês corrente, com filtro `de` / `até`.
- Resumo de contas a receber, agenda e estoque.
- Links para os relatórios específicos e para a exportação.

### 5.2.2 Receitas (`relatorios/receitas-mensais.blade.php`)
- Total recebido no período e quantidade de lançamentos.
- Saídas pagas (baixas de contas a pagar) e saldo do período.
- Recebido por serviço, por forma de pagamento e por mês.
- Lista dos lançamentos com paciente, serviço, forma, observações e valor.
- Destaque para lançamentos sem paciente vinculado, que continuam somando no caixa e são revisados em Financeiro → Pagamentos.

### 5.2.3 Presença e faltas (`relatorios/presenca-faltas.blade.php`)
- Contagem por status: atendidos, faltas, cancelados e aguardando.
- Taxa de faltas (faltas ÷ total do período).
- Agrupamento por profissional e por mês.
- Tabela dos atendimentos do período.

### 5.2.4 Pacotes e sessões (`relatorios/pacotes-sessoes.blade.php`)
- Total de pacotes, ativos, sessões contratadas, usadas, restantes e reposições.
- Consumo por serviço no período.
- Lista de pacotes com paciente, serviço, status, usadas, restantes e validade.

### 5.2.5 Exportação CSV
- BOM UTF-8 e separador `;` para abrir no Excel em português.
- Proteção contra fórmula em célula de texto livre (prefixo seguro quando o conteúdo começa com `=`, `+`, `-` ou `@`).
- Tipos válidos: `receitas`, `presenca`, `pacotes`; tipo desconhecido retorna 404.
- O CSV usa as mesmas consultas da tela, respeitando o período informado.

### 5.2.6 Correção de período
No SQLite a coluna `data` guarda data e hora. A primeira versão comparava apenas `Y-m-d` e deixava de fora um pagamento lançado às 18h30 no último dia do período. As consultas de receitas, baixas, consumos e resumo financeiro passaram a usar `startOfDay()` e `endOfDay()`, e um teste de regressão foi adicionado.

## 5.3 Rotas Adicionadas

| Rota | Controller | Método |
|------|-----------|--------|
| `relatorios.index` | `RelatorioController@index` | GET |
| `relatorios.receitas` | `RelatorioController@receitasMensais` | GET |
| `relatorios.presenca-faltas` | `RelatorioController@presencaFaltas` | GET |
| `relatorios.pacotes` | `RelatorioController@pacotesSessoes` | GET |
| `relatorios.exportar/{tipo}` | `RelatorioController@exportarCsv` | GET |

## 5.4 Testes Concluídos

| Suíte | Testes | Asserções | Situação |
|-------|-------:|----------:|----------|
| `RelatorioTest` | 7 | 36 | ✅ |

Cobrem agrupamento por serviço/forma/mês, pagamento com hora no último dia do período, taxa de presença/faltas, saldo de pacotes, CSV com BOM e cabeçalho, tipo inválido (404) e permissão de acesso.

## 5.5 Pendências e Validações Humanas
- **Pendência (I):** a Daiane precisa confirmar se os totais de receitas, presença/faltas e pacotes batem com o que ela lia nas planilhas.
- **Pendência (II):** a visão de “quantas sessões restam nesta semana” não existe como relatório separado; o saldo por paciente e por pacote está disponível.
- **Pendência (III):** nenhum documento fiscal é emitido pelos relatórios; o SendNF continua mock.

## 5.6 Status
**Concluída.** Relatórios, CSV e teste de regressão implementados.
