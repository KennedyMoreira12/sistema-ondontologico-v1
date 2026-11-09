# ETAPA 2 — Conferência dos Dados (Produção, Leitura)

## 2.1 Objetivo
Conferir, em modo somente leitura, os dados que a clínica Daiane (`clinic_id=5`) já tinha no banco e verificar se os volumes correspondem ao que existe hoje no sistema. Nenhum registro é criado, alterado ou apagado na produção.

## 2.2 Conclusão da Implementação
A conferência está concluída em modo somente leitura. Passou por três frentes de validação:

1. **Leitura no banco original/VPS:** os dados já estavam no banco da clínica; não há necessidade de reimportar.
2. **Fase 1 de validação:** as tabelas estruturais conferidas contra o schema atual.
3. **Fase 2 de validação:** os módulos operacionais conferidos no banco da clínica.

Os volumes atuais confirmados na produção:

| Tabela | Registros | Tabela | Registros |
|--------|----------|--------|----------|
| Pacientes | 767 | Fornecedores | 94 |
| Pagamentos | 6.163 | Pacotes de sessões | 369 |
| Consultas/Atendimentos | 1.362 | Contas a pagar | — |

### Investigação 6.174 × 6.163 (fechada)
A diferença de 11 registros foi explicada sem alterar dados:

- **14 duplicatas internas** na própria planilha, idênticas em (`data`, `valor`, `observações`), colapsadas pela idempotência do importador. A reextração de `PAGAMENTOS.xlsx` confirmou 6.174 linhas válidas, **6.160 chaves distintas** e 14 grupos repetidos.
- **3 pagamentos pré-existentes** na base de 572 que não têm linha correspondente na planilha (569 casaram; 3 sobraram).
- Conta exata: **6.174 − 14 + 3 = 6.163**; importação: 572 + 5.591 novos = 6.163.

O que se mantém:

- Nenhuma igualdade foi forçada: os 6.163 pagamentos existentes permanecem intactos e continuam somando no caixa.
- O sistema não apaga nem sobrescreve lançamento antigo para “fazer o número bater”.
- A revisão de vínculo em `financeiro/pagamentos` mostra os lançamentos sem paciente; qualquer associação é manual.

A identificação individual dos 3 pagamentos pré-existentes depende do banco original (MySQL local indisponível), mas a contagem está registrada em `comparacao.txt`.

## 2.3 Comandos Utilizados

### 2.3.1 Diagnosticar Pagamentos (`financeiro:diagnosticar-pagamentos`)
```bash
php artisan financeiro:diagnosticar-pagamentos --clinic=5
php artisan financeiro:diagnosticar-pagamentos --clinic=5 --arquivo=extracao.csv
```

O comando é somente leitura. Com `--arquivo`, compara a extração da planilha com o que está gravado e mostra exatamente quais chaves sobram de cada lado.

### 2.3.2 Vincular Pagamentos Antigos (`financeiro:vincular-pagamentos`)
```bash
# Dry-run é o padrão: não grava nada
php artisan financeiro:vincular-pagamentos --clinic=5

# Só grava com --apply explícito, e apenas quando há um candidato claramente acima dos demais
php artisan financeiro:vincular-pagamentos --clinic=5 --apply
```

### 2.3.2 Consultar Relatório Financeiro (`relatorios.receitas`)
```bash
# Acesse relatórios via navegador: /relatorios/receitas
```

## 2.4 Dependências Comerciais Pendentes

- **Pendência (I):** Regras de vinculação de pacientes antigos por nome parecido. Não há merge automático — todos os vinculamentos são revisão humana, conduzida pela clínica Daiane.
- **Pendência (II):** A clínica não confirmou como deve reagendar as faltas. A regra vigora: "faltou não consome sessão, a reposição é ato explícito". Quando confirmada, o `ConsumoSessaoService` será ajustado para reagendar automaticamente.
- **Pendência (III):** Os 11 lançamentos sem paciente devem ser vinculados enquanto o usuário tem contexto financeiro para revisar, ou conservados para manter o saldo do caixa intacto.

## 2.5 Status
**Concluída.** A conferência foi feita em produção somente leitura. Nenhum registro de produção foi criado, alterado ou apagado.
