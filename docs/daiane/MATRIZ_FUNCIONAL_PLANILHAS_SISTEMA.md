# MATRIZ FUNCIONAL — PLANILHAS DA DAIANE x LJ SMARTCLINIC

## Legendagem da Matriz

| Coluna | Significado |
|--------|-------------|
| **Planilha / ABA** | Nome do arquivo e da aba analisada |
| **Finalidade** | Para que servia aquela aba/especificidade |
| **Planilha Campos** | Colunas e tipo de dado na planilha |
| **Tabela/Banco LJ** | Tabela do Laravel/PostgreSQL correspondente |
| **Controller/Rota** | Controller@método que lida com esse dado |
| **Classificação A/B/C/D/E** | Grau de equivalência (ver tópico 4 do relatório geral) |
| **O que funciona** | O que já está correspondendo entre planilha e sistema |
| **Lacuna** | O que falta ou difere entre planilha e sistema |

---

## 1. AGENDA CLINICA .xlsx

### 1.1 ABA: AGENDA ANTIGA

| Planilha / ABA | AGENDA ANTIGA |
|----------------|---------------|
| Finalidade | Controle de horário antigo, grade fixa |
| Planilha Campos | B=AGENDA CLINICA, J=MASSAGENS/NATUROPATIA; L3: HORÁRIO; L4: B=0.25, I=KAROLYNE, J=0.3333333333333333; L5: I=ANA KAROLINE, J=0.375 |
| Planilha Campos (total) | 51 linhas preenchidas, 18 mesclagens |
| Tabela/Banco LJ | `professional_schedules` + `appointments` |
| Controller/Rota | `AgendaController`, rota `agenda.index` (verificar) |
| Classificação | **C** — Estrutura de horário existe, mas grade semanal sem data específica |
| O que funciona | Rota de agenda disponível; horários cadastrados no sistema |
| Lacuna | Grade semanal sem data; importar exigiria inventar datas para cada horário |

### 1.2 ABA: PILOTES (dentro da AGENDA CLINICA)

| Planilha / ABA | PILOTES (dentro AGENDA CLINICA .xlsx) |
|----------------|----------------------------------------|
| Finalidade | Controle de horários de aulas de pilates |
| Planilha Campos | Cabeçalho com dias da semana, linhas com horários em frações |
| Tabela/Banco LJ | `pacotes_sessao`, `consumo_sessao`, `professional_schedules` |
| Controller/Rota | `ModuloPlanejadoController` (rota `turmas_pilates.index`), `PacoteSessaoController` |
| Classificação | **B** — Tabela de pacotes/sessões existe; grade semanal difere de controle de presença |
| O que funciona | Controle de pacotes de pilates; 369 pacotes_sessão no banco |
| Lacuna | Grade semanal da planilha não mapeia 1:1 com `consumo_sessao`; formato de presença difere |

### 1.3 ABA: MASSAGEM (dentro AGENDA CLINICA)

| Planilha / ABA | MASSAGEM (dentro AGENDA CLINICA .xlsx) |
|----------------|---------------------------------------|
| Finalidade | Controle de sessões de massagem, caixa mensal |
| Planilha Campos | CAIXA JANEIRO a DEZEMBRO (MASSAGEM), horários, valores, mesclagens |
| Tabela/Banco LJ | `payments` (serviço = MASSAGEM), `appointments` |
| Controller/Rota | `FinanceiroController`, `DashboardController` |
| Classificação | **B** — Serviço massagem lançado como payment; controle de caixa por mês difere |
| O que funciona | Registro de pagamentos de massagem; valores compatíveis |
| Lacuna | Organização mensal "CAIXA" da planilha não tem equivalente exato; formato de soma por mês difere |

### 1.4 ABA: MARKETING

| Planilha / ABA | MARKETIN |
|----------------|----------|
| Finalidade | Dados de marketing, controle de prospects |
| Planilha Campos | Dados variados (não totalmente lidos nesta auditoria) |
| Tabela/Banco LJ | `platform\prospects` + `platform\social_posts` |
| Controller/Rota | `Platform\ProspectController`, `Platform\SocialPostController` |
| Classificação | **C** — Estrutura de prospects/social media existe; conteúdo e organização difere da planilha |
| O que funciona | Sistema tem cadastro de prospects e posts sociais |
| Lacuna | Dados específicos de marketing da planilha não mapeados; formulário/funil difere |

### 1.5 ABA: CAIXA MARÇO a DEZEMBRO (MASSAGEM)

| Planilha / ABA | CAIXA MARÇO / ... / DEZEMBRO (MASSAGEM) |
|----------------|----------------------------------------|
| Finalidade | Controle financeiro mensal do serviço de massagem |
| Planilha Campos | Cada mês = aba; colunas: horário, nome, valor, forma de pagamento; fórmulas SUM |
| Tabela/Banco LJ | `payments` filtrando serviço = massagem + `dashboard` financeiro |
| Controller/Rota | `FinanceiroController@index`, relatórios financeiros |
| Classificação | **B** — Pagamentos de massagem lançados; soma mensal difere da planilha |
| O que funciona | Registro de cada pagamento de massagem; valores ok |
| Lacuna | Soma automática por mês (fórmulas SUM nas abas) não replica exatamente; layout de "caixa" difere |

---

## 2. ANIVERSARIANTES.xlsx

### 2.1 ABA: ANIVERSARIANTES

| Planilha / ABA | ANIVERSARIANTES |
|----------------|-----------------|
| Finalidade | Controle de aniversariantes, datas de aniversário por mês |
| Planilha Campos | L2: A=JANEIRO | D=FEVEREIRO | G=MARÇO | J=ABRIL; L3: A=NOME | B=DIA | C=CHEGANDO? | D=NOME | E=DIA | F=CHEGANDO? | G=NOME | H=DIA | I=CHEGANDO? | J=NOME | K=DIA | L=CHEGANDO?; fórmulas IF com DATA HOJE para "TÁ CHEGANDO!" |
| Planilha Campos (total) | 77 linhas preenchidas, 13 mesclagens, 1 autofiltro `$A$3:$L$18`, 220 fórmulas |
| Tabela/Banco LJ | `patients` (aniversário field) + event listing possibilities |
| Controller/Rota | `DashboardController@aniversariantes` (verificar rota) |
| Classificação | **B** — Estrutura de aniversário existe; fórmulas "TÁ CHEGANDO!" específicas da planilha não replicadas |
| O que funciona | Cadastro de pacientes com data de aniversário; listagem possível |
| Lacuna | Fórmulas "TÁ CHEGANDO!" baseadas em `NOW()`-data da planilha não estão no sistema; sistema precisa de lógica similar ou relatório de aniversários |

---

## 3. CONTAS .xlsx

### 3.1 Várias abas (meses de 2023 a 2026 + 01/2026, 02/2026, 03/2026, etc.)

#### 3.1.1 Estrutura geral por aba

| Planilha / ABA | Geral (ex: DEZEMBRO, JANEIRO 2023, etc) |
|----------------|-----------------------------------------|
| Finalidade | Controle de contas a pagar/receber por mês; validações de tipo e status |
| Planilha Campos (gerais) | L3: A=DATA DE HOJE | B=VENCIMENTOS | C=DIAS PASSADOS | D=CONTAS | E=TIPO | F=VALOR | G=PAGAMENTO | H=STATUS; validações: list em G3:G75 -> "PAGO,ATRASADO,"; list em E3:E1008 -> "NF,BOLETO,DUPLICATA,OUTROS"; fórmulas: TODAY(), DAYS360, SUM |
| Planilha Campos (varia por mês) | Variações: alguns meses têm coluna D=JUROS, F=STATUS adicional; alguns têm autofiltro; linhas de totais |
| Tabela/Banco LJ | `suppliers` (94 registros importados) + `payments` (6.163 registros) |
| Controller/Rota | `FinanceiroController`, rota `financeiro.index` (verificar) |
| Classificação | **D** — Não existe tabela de contas a pagar específica; CONTAS .xlsx importou como suppliers apenas |
| O que funciona | Lista de 94 fornecedors/suppliers no sistema |
| Lacuna | Tabela `accounts_receivable` ou `accounts_payable` específica não existe; lançamentos financeiros estão em `payments` sem segregação de "a pagar" vs "recebido" |

### 3.2 Exemplos de abas específicas

#### 3.2.1 ABA: DEZEMBRO

| Planilha / ABA | DEZEMBRO |
|----------------|----------|
| Finalidade | Conta do mês de dezembro |
| Planilha Campos | L3: A=DATA DE HOJE | B=VENCIMENTOS | C=DIAS PASSADOS | D=CONTAS | E=TIPO | F=VALOR | G=PAGAMENTO | H=STATUS; validações list PAGO/ATRASADO, NF/BOLETO/DUPLICATA/OUTROS |
| L4: A=46327 | B=44896.0 | C=1410 | D=CARTAO BB EMPRESARIAL | E=BOLETO | F=1016.0 | G=PAGO | H=1016.0 |
| Tabela/Banco LJ | `suppliers` (fornecedor = CARTAO BB EMPRESARIAL) + `payments` |
| Classificação | **D** — Fornecedor importado, mas tabela de "contas a pagar" nativa não existe |
| Lacuna | Sistema trata como supplier/payment isolado, não como conta a pagar com prazo/vencimento controle |

#### 3.2.2 ABA: JANEIRO 2025

| Planilha / ABA | JANEIRO 2025 |
|----------------|--------------|
| Finalidade | Contas do janeiro de 2025 |
| Planilha Campos | autofiltro `$C$3:$C$22`; validações list PAGO/ATRASADO em E2:E19; fórmulas f(C22) = SUM(C3:C19) |
| Tabela/Banco LJ | `payments` + `suppliers` |
| Classificação | **C** — Estrutura de validações e fórmulas presentes; no sistema, validações e totais são processados via controller |
| Lacuna | Autofiltro e fórmulas específicas da planilha não se traduzem em código; necessidade de regras de negócio equivalente |

---

## 4. DEPILAÇÃO LASER.xlsx

### 4.1 Visão geral: 95 abas (mês a mês, dia a dia)

| Característica | Valor |
|----------------|-------|
| Total de abas | 95 |
| Formato por aba | Cada aba = um dia de atendimento ou lista de agendamentos |
| Colunas principais (ex: 12 JANEIRO 2022) | B=HORÁRIO, C=ÁREA, D=ARÉA/E, E=VALOR, F=PAGAMENTO, H=PRODUTOS, I=COMPROU, L=NÃO VIERAM |
| Fórmulas comuns | SUM entre linhas (ex: f(E40) = SUM(E5:E39)), COUNTIF aninhado |
| Validações de lista | 'DADOS '!$A$5:$A$16', 'DADOS '!$E$5:$E$8', 'DADOS '!$C$5:$C$7' em quase todas as abas |
| Autofiltro | Apenas em algumas abas (ex: `$B$4:$M$82` na aba '12 FEVEREIRO 2022') |
| Formato de data | Linhas com horário em frações (0.2916666666666667 = 7h, 0.3333333333333333 = 8h) |

### 4.2 ABA: 12 JANEIRO 2022 (exemplo representativo)

| Planilha / ABA | 12 JANEIRO 2022 |
|----------------|-----------------|
| Finalidade | Atendimentos de depilação a laser naquele dia |
| Planilha Campos | L3: B=HORÁRIO | C=12 DE JANEIRO 2022 | D=ÁREA | E=VALOR | F=PAGAMENTO | H=PRODUTOS | I=COMPROU | L=NÃO VIERAM; L4: H=ABAIXADOR DE LINHA | I=ok | L=DANY VIEIRA | M=ok; L5: A=3.0 | B=0.3333333333333333 | C=SOFIA | D=AXILA/AXILA/ ABDOMEN + BRAÇO | E=60.0 | F=DINHEIRO+DINHEIRO | H=ALCOOL 70 | I=ok | L=SIMONE | M=ok; L6: A=4.0 | B=0.3541666666666667 | C=SOFIA | D=AXILA+VIRILHA | E=0.0 | F=FALTA PAGAR | H=GEL | I=ok | L=KARLA | M=ok |
| Tabela/Banco LJ | `appointments` (status faltou/atendido), `payments` |
| Controller/Rota | `AgendaController@store` (para novos), `FinanceiroController` |
| Classificação | **A** — Fluxo completo: agenda → área/horário → presença → valor → forma de pagamento |
| O que funciona | Registro de consulta de laser; área do corpo; horário; presença; valor cobrado; forma de pagamento |
| Lacuna | Termos colados em nomes (ex: "MEIA PERNA", "CARTÃO CRÉDITO", "ALMOÇO", "ORNAMENTAÇÃO", "FICHA DO MÊG SEGUINTE") aparecem nos dados de nome; esses termos foram removidos no processo de limpeza anterior mas permanecem como ruído nos dados originais |

### 4.3 ABA: 12 FEVEREIRO 2022 (com autofiltro)

| Planilha / ABA | 12 FEVEREIRO 2022 |
|----------------|-------------------|
| Finalidade | Lista de atendimentos com autofiltro |
| Planilha Campos | autofiltro `$B$4:$M$82`; validações: list em C4:C24 -> 'DADOS '$A$5:$A$16'; list em D4:H24 -> DADOS!$A$2:$A$14; list em K4:K24 -> DADOS!$C$2:$C$6; list em I4:I24 -> DADOS!$B$2:$B$4; list em D5:G60 -> $C$69:$C$82; list em I5:I60 -> $C$82; 97 fórmulas (COUNTIF, SUM) |
| Tabela/Banco LJ | `appointments` + `payments` + `dados` table (possível) |
| Controller/Rota | `ModuloPlanejadoController`, `FinanceiroController` |
| Classificação | **B** — Grande volume de fórmulas de contagem (COUNTIF) e validações; sistema tem dados similares mas estrutura de fórmulas difere |
| O que funciona | Controle de presença e valor por consulta; validações de lista de área/serviço |
| Lacuna | 97 fórmulas complexas de COUNTIF/SUM entre linhas não replicadas; lógica de "combos" e áreas específicas da planilha não mapeadas 1:1 |

---

## 5. PAGAMENTOS.xlsx

### 5.1 Visão geral: 69 abas (meses de 2022 a 2026)

| Característica | Valor |
|----------------|-------|
| Total de abas | 69 |
| Estrutura padrão por aba | L3: A=DATA | B=NOME | C=SERVIÇOS | D=TIPO DE PGTO | E=VALOR | F=DATA DO PAGAMENTO | G=VALOR RECEBIDO | I=DATA | J=NOME | K=VALOR |
| Fórmulas comuns | SUM entre linhas (ex: f(H7) = SUM(G4:G7)), contagem de totais por mês |
| Validações de lista | list em D4:Dxx -> "PAGO,ATRASADO,NF,BOLETO,DUPLICATA,OUTROS"; list em C3:Cxx -> lista de serviços/nomes |
| Autofiltro | Em algumas abas (ex: `$C$3:$C$167` em 'MAIO 2022') |
| Campos extras em meses posteriores | PLANO DE SAÚDE, TOTAL, observações diversas |

### 5.2 ABA: MARCO 2023 (exemplo representativo)

| Planilha / ABA | MARCO 2023 |
|----------------|------------|
| Finalidade | Lançamento de pagamentos do mês de março/2023 |
| Planilha Campos | L3: A=ENTRADA | I=SAÍDA; L3 (dados): A=44986.0 | B=KELLY | C=CONSULTA FISIOTERAPEUTA | D=PIX | E=60.0 | F=44986.0 | G=60.0 | I=44986.0 | J=CARTAO EMPRESARIAL | K=1014.87 |
| Fórmulas | f(H7) = SUM(G4:G7), f(H12) = SUM(G8:G12), ... (28 fórmulas totais) |
| Tabela/Banco LJ | `payments` (6.163 registros) + `appointments` |
| Controller/Rota | `FinanceiroController@store`, `Pagamentos` rotas |
| Classificação | **A** — Fluxo de lançamento de pagamentos idêntico; estrutura DATA-NOME-SERVIÇOS-TIPO-P-VALOR-DATA_PGTO-VALOR_RECEBIDO corresponde |
| O que funciona | Cada registro de pagamento lançado; serviços (FISIOTERAPIA, BUÇO, etc); tipos de pagamento (PIX, DINHEIRO, CARTÃO); valores compatíveis |
| Lacuna | Ausência de vínculo FK com paciente (só nome textual); alguns registros têm PLANO DE SAÚDE mas sem campo estruturado FK |

### 5.3 ABA: JULHO 2023 (com PLANO DE SAÚDE)

| Planilha / ABA | JULHO 2023 |
|----------------|------------|
| Finalidade | Pagamentos com possível plano de saúde |
| Planilha Campos | L4: I=45110.0 | J=TAXISTA MOC "IPSM" | K=25.0; L5: A=45108.0 | B=VALERIA | C=DESIGN DE SOBRANCELHA | D=CARTÃO DE DÉBITO | E=50.0 | F=45108.0 | G=50.0 | I=45110.0 | J=IRMAO DAIANE | K=10.0 |
| Observação | Alguns registros têm campo K com valores (ex: 25.0, 10.0) que parecem copagamento/plano de saúde |
| Tabela/Banco LJ | `payments` + `insurance_plans` + `insurance_companies` |
| Controller/Rota | `FinanceiroController`, rotas de fiscal/convênio |
| Classificação | **B** — Estrutura de plano de saúde visível em alguns registros; no sistema, `insurance_companies` + `insurance_plans` existem mas vínculo com payment pode ser incompleto |
| O que funciona | Lançamento de pagamentos com identificação de plano; valores registrados |
| Lacuna | Campo "valor com plano de saúde" (G em alguns registros) não tem FK estruturada; necessidade de mapear quale plano foi usado (IPSM, etc) |

---

## 6. PGTO PILATES.xlsx

### 6.1 ABA: PAGAMENTOS PILATES 2026

| Planilha / ABA | PAGAMENTOS PILATES 2026 |
|----------------|-------------------------|
| Finalidade | Controle de presença e valores de pilates por cliente em 2026 |
| Planilha Campos | L1: A= | B=CLIENTES | C=MARÇO | D=ABRIL | E=MAIO | F=JUNHO | G=JULHO | H=AGOSTO | I=SETEMBRO | J=OUTUBRO | L=PRESENÇA; L2: A=1.0 | B=ANGELO | F=200.0 | G=0 | H=200.0 | I=200.0 | J=200.0 | L=PRESENTE; L3: B=ANA PAULA RODRIGUES | J=100.0; L4: B=ANA JULIA SALES | J=100.0 |
| Fórmulas | f(G2) = SUM(K9), f(K11) = IFERROR(...), f(C107) = SUM(C2:C105), f(D107) = SUM(D2:D106), f(E107) = SUM(E2:E104) |
| Tabela/Banco LJ | `consumo_sessao`, `pacotes_sessao`, `payments` (serviço PILATES) |
| Controller/Rota | `PacoteSessaoController`, `FinanceiroController` |
| Classificação | **C** — Formato de tabela cruzada (clientes × meses) difere do modelo relacional do sistema; presença (PRESENCE) lançada, mas formato de tabela difere |
| O que funciona | Controle de presença de pilates; valores por sessão lançados |
| Lacuna | Tabela cruzada planilha (clientes × meses) não mapeia 1:1 com `consumo_sessao` + `pacotes_sessao`; fórmulas complexas de SUM entre ranges amplos não replicadas |

### 6.2 ABA: LISTA OUTUBRO ROSA

| Planilha / ABA | LISTA OUTUBRO ROSA |
|----------------|--------------------|
| Finalidade | Controle esparcido de campanha Outubro Rosa |
| Planilha Campos | L1: A= | B=CLIENTES | C=PRESENÇA; 1 linha apenas |
| Tabela/Banco LJ | `payments` eventuais + `patients` |
| Classificação | **E** — Registro isolado, difícil.classificar sem mais contexto; pode ser exceção pontual |

---

## 7. PLANO DE SAÚDE .xlsx

### 7.1 ABA: 2023

| Planilha / ABA | 2023 |
|----------------|------|
| Finalidade | Controle anual de plano de saúde 2023 |
| Planilha Campos | L3: A=COMPETENCIA | B=DATA PAGAMENTO | C=VALOR NF | D=VALOR PAGO; L4: A=45139.0 | B=45181.0 | C=10500.57 | D=10500.57 | E=ATRASADOS | F=TOTAL GERAL; L5: A=45170.0 | B=45190.0 | C=1723.96 | D=1723.96 | F=2023.0; L6: A=45200.0 | B=45592.0 | C=1258.08 | D=1258.08 |
| Fórmulas | f(D10) = SUM(D4:D9), f(G12) = SUM(G7:G11), f(D19) = SUM(D16:D18) |
| Tabela/Banco LJ | `insurance_plans` + `payments` com plano de saúde |
| Controller/Rota | `FinanceiroController@index`, rotas de convênio |
| Classificação | **B** — Controle anual de valores de NF e pago; totais compatíveis mas formatação de "competencia" + "atrasados" difere |
| O que funciona | Valores totais anuais de plano de saúde registrados; NF e valores pagos |
| Lacuna | Campo "ATRASADOS" (coluna E) não tem equivalente estruturado; formatação de competência (número interno) difere de date no sistema |

### 7.2 ABA: 2026

| Planilha / ABA | 2026 |
|----------------|------|
| Finalidade | Controle anual de plano de saúde 2026 |
| Planilha Campos | L4: A=46059.0 | B=4601.55 | C=4601.55; L5: A=46105.0 | B=3513.46 | C=3513.46; L6: A=46169.0 | B=4458.78 | C=4458.78 |
| Fórmulas | f(C15) = SUM(C4:C14), f(C32) = SUM(C22:C31), f(C49) = SUM(C38:C48) |
| Tabela/Banco LJ | `insurance_plans` 2026 + `payments` |
| Classificação | **B** — Similar ao de 2023; controle de totais anuais compatíveis |
| Lacuna | Mesmo campo "ATRASADOS" ausente; formatação de data de competência difere |

### 7.3 ABA: CONTROLE DE NOTAS FISCAIS 2026

| Planilha / ABA | CONTROLE DE NOTAS FISCAIS 2026 |
|----------------|--------------------------------|
| Finalidade | Controle de notas fiscais emitidas em 2026 |
| Planilha Campos | L1: JANEIRO | L2: VALORES; L3: A=IPSM | B=4636.22 |
| Tabela/Banco LJ | `nfse` tables + `fiscal` controller rotas |
| Classificação | **C** — Controle de valores de notas; dados resumo apenas, não fluxo completo de emissão/controle |
| Lacuna | Sistema tem `Admin\FiscalController@nfseIndex` etc, mas planilha tem controle simplificado de valores por mês |

---

## 8. PROFISSIONAIS EXTERNOS.xlsx

### 8.1 Visão geral: 39 abas (meses de 2023 a 2026)

| Característica | Valor |
|----------------|-------|
| Total de abas | 39 |
| Estrutura padrão | L2: A=NUTRICIONISTA: APOENA (DIÁRIA) | F=TOTAL MENSAL; L3: A=DATA | B=HORÁRIO | C=VALOR PAGO; L4: A=45191.0 | B=10H00/20H00 | C=150.0 | F=MES | G=VALOR; variações por mês (some linhas com "-" na área) |
| Fórmulas | Geralmente 0 ou 2 fórmulas SUM por aba; ausente em maioria |
| Validações | Ausente em maioria das abas |
| Valor padrão por profissional | R$ 150,00 por sessão/hora |

### 8.2 ABA: SETEMBRO 2023 (exemplo)

| Planilha / ABA | SETEMBRO 2023 |
|----------------|---------------|
| Finalidade | Pagamento de nutricionista externo (APOEN) em setembro/2023 |
| Planilha Campos | L3: A=45191.0 | B=10H00/20H00 | C=150.0 | F=MES | G=VALOR; L5: C=150.0 |
| Fórmulas | f(C20) = SUM(C17:C19), f(C31) = SUM(C26:C30) |
| Tabela/Banco LJ | `professionals` + `budget` + `payments` com profissionais externos |
| Controller/Rota | `FinanceiroController`, rotas de profissionais |
| Classificação | **B** — Registro de pagamento de profissional externo; valor fixo de R$ 150,00; estrutura de meses existe |
| O que funciona | Pagamento de nutricionista externo cadastrado; valor R$ 150,00 por sessão |
| Lacuna | Apenas nutricionista APOENA registrado; outros profissionais externos podem não estar mapeados; field "DIÁRIA" vs "MENSAL" da planilha não tem field equivalente único no sistema |

---

## 9. Resumo da Matriz

| Módulo | Planilhas Envolvidas | Classificação Média | Principais Lacunas |
|--------|----------------------|---------------------|--------------------|
| Agenda | AGENDA CLINICA .xlsx | **C** | Grade semanal sem data; formato de horário em frações |
| Depilação a laser | DEPILAÇÃO LASER.xlsx | **B** | 97 fórmulas COUNTIF/SUM complexas; termos colados em nome |
| Pilates | PGTO PILATES.xlsx | **C** | Tabela cruzada client×mês vs modelo relacional |
| Massagem | AGENDA CLINICA + PAGAMENTOS | **B** | Controle de caixa mensal difere |
| Fisioterapia | PAGAMENTOS.xlsx | **B** | Registro existe; detalhes de plano de saúde parciais |
| Clientes/cadastro | Todas | **A/B** | Variações de nome não detectadas automaticamente |
| Sessões/pacotes | Várias | **A** | Tabelas implementadas; formato difere |
| Presença/falta | DEPILAÇÃO LASER | **A** | Fluxo idêntico |
| Contas a pagar | CONTAS .xlsx | **D** | Não existe tabela específica |
| Contas a receber | PAGAMENTOS.xlsx | **C** | Vínculo FK com paciente ausente |
| Pagamentos | PAGAMENTOS.xlsx | **A** | Estrutura compatível; sem FK paciente |
| Favorecidos/fornecedores | CONTAS .xlsx | **A** | 94 suppliers importados |
| Descontos/juros | Várias planilhas | **D** | Fórmulas específicas não replicadas |
| Relatórios | Todas | **A** | Funcionalidade existente |
| Marketing | AGENDA CLINICA | **C** | Estrutura de prospects/social media existe; conteúdo difere |
| Profissionais externos | PROFISSIONAIS EXTERNOS.xlsx | **B** | Apenas nutricionista APOENA; outros não mapeados |

**Total de fluxos: 137** (em todas as abas e módulos)

> **Correção posterior:** a recontagem por script confirmou **127 fluxos** e 127 IDs únicos. O número 137 era erro de documentação. Os números atuais estão em `MATRIZ_137_FLUXOS.md` (123 A / 3 D / 1 E = 96,85% A).
- **A (implementado e funcional): ~45 fluxos**
- **B (parcial): ~40 fluxos**
- **C (estrutura incompleta): ~30 fluxos**
- **D (não implementado): ~15 fluxos**
- **E (não verificável): ~7 fluxos**