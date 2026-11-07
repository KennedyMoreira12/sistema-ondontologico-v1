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

### Investigação 6.174 × 6.163
A diferença entre os 6.174 lançamentos extraídos e os 6.163 pagamentos gravados é de 11 registros. A conferência foi feita em leitura e **não fechou** a origem exata dessas 11 linhas: isso exige o arquivo/banco original e uma conferência linha a linha que não pode ser feita nesta máquina.

O que se pode afirmar com segurança:

- Nenhuma igualdade foi forçada: os 6.163 pagamentos existentes permanecem intactos e continuam somando no caixa.
- O sistema não apaga nem sobrescreve lançamento antigo para “fazer o número bater”.
- A revisão de vínculo em `financeiro/pagamentos` mostra os lançamentos sem paciente; qualquer associação é manual.

A explicação definitiva das 11 linhas continua como pendência de conferência com o banco original.

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
