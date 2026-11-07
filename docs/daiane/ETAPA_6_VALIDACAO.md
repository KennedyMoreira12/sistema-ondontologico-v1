# ETAPA 6 — Validação com a Cliente

## 6.1 Objetivo
Confirmar que a cliente Daiane consegue realizar nas nove tarefas críticas do sistema o mesmo ou um melhor nível de facilidade comparado às planilhas. Este é o passo final e determinante para aprovação da substituição das planilhas.

## 6.2 Protocolo de Nove Tarefas Críticas

A cliente deve conseguir completar estas nove tarefas. Para cada uma, será registrada:
- **Classificação**: "Sem dificuldades", "Alguma dificuldade" ou "Impossível"
- **Comentários**: Observações da cliente sobre a experiência
- **Correções sugeridas**: O que melhoraria a tarefa

> **Nota:** as classificações já marcadas neste documento são expectativas de projeto, não resultados de uma sessão real com a cliente. Elas só valem depois de preenchidas no formulário da seção 6.6.

### Tarefa 1: Localizar uma cliente

| Aspecto | Planilha | LJ SmartClinic | Classificação |
|---------|----------|----------------|---------------|
| Buscar por nome | Coluna NOME em aba qualquer | Busca `pacientes` por nome | ✓ Sem dificuldades |
| Buscar por histórico | Revisar abas passadas | `appointments` + `evolucoes_sessao` + `avaliacoes` | ✓ Sem dificuldades (melhor que planilha) |
| Buscar por variações de nome | GISELE/GISELE ALVES/GISELE LVES | Sistema alerta candidatos, NÃO merge automático | ⚠ Alguma dificuldade (primeira vez) |

**Critério de aceitação:** Cliente localiza seu registro sem ajuda técnica.

### Tarefa 2: Consultar a agenda

| Aspecto | Planilha | LJ SmartClinic | Classificação |
|---------|----------|----------------|---------------|
| Ver horários da semana | Grade SEGUNDA..SÁBADO | Agenda do dia + listagem por dia | ⚠ Alguma dificuldade (mudança de visual) |
| Ver próprios agendamentos | Revisar agendas da planilha | Minha agenda / consultas próprias | ✓ Sem dificuldades |
| Buscar por data específica | Não se aplica (grade sem data) | Agenda com seletor de data | ✓ Sem dificuldades (melhor) |

**Critério de aceitação:** Cliente consulta a agenda e encontra seus agendamentos sem dificuldades.

### Tarefa 3: Criar um agendamento

| Aspecto | Planilha | LJ SmartClinic | Classificação |
|---------|----------|----------------|---------------|
| Novo agendamento | Nova linha/aba na planilha | Formulário: data + profissional + serviço | ✓ Sem dificuldades |
| Seleção de horário | Horário da grade da semana | Horário disponível (lista gerada) | ✓ Sem dificuldades |
| Confirmação | Linha nova na planilha | Mensagem de sucesso + appointment criado | ✓ Sem dificuldades |

**Critério de aceitação:** Cliente cria novo agendamento sem erros e confirma na tela.

### Tarefa 4: Registrar atendimento

| Aspecto | Planilha | LJ SmartClinic | Classificação |
|---------|----------|----------------|---------------|
| Marcar presença | Coluna G: PRESENTE | Botão "Presente" no appointment | ✓ Sem dificuldades |
| Marcar falta | Coluna G: AUSENTE | Botão "Falta" no appointment | ✓ Sem dificuldades |
| Ver status imediatamente | Conferência visual da linha | Status exibido claramente | ✓ Sem dificuldades |

**Critério de aceitação:** Cliente registra presença ou falta e o status é atualizado imediatamente.

### Tarefa 5: Dar baixa em uma sessão

| Aspecto | Planilha | LJ SmartClinic | Classificação |
|---------|----------|----------------|---------------|
| Baixa de sessão | Não explícita na planilha (controle por pacote) | Tela de consumo de pacote - decrementar sessões | ⚠ Alguma dificuldade (novo conceito) |
| Ver sessões restantes | Total por mês em resumo | Minhas sessões - X usadas = Y restantes | ⚠ Alguma dificuldade (primeira vez) |

**Critério de aceitação:** Cliente entende como baixar sessão e vê quantidade restante.

### Tarefa 6: Registrar falta ou reposição

| Aspecto | Planilha | LJ SmartClinic | Classificação |
|---------|----------|----------------|---------------|
| Registrar falta | Coluna G: AUSENTE | Botão "Falta" no appointment | ✓ Sem dificuldades |
| Registrar reposição | Não explicitamente | Campo "reposição" em consumo de sessão ou novo agendamento | ⚠ Alguma dificuldade (novo conceito) |

**Critério de aceitação:** Cliente registra falta sem dificuldades; reposição é explicada ou alternativa oferecida.

### Tarefa 7: Consultar pagamentos

| Aspecto | Planilha | LJ SmartClinic | Classificação |
|---------|----------|----------------|---------------|
| Ver históricos de pagamentos | Revisar abas PAGAMENTOS (69 meses) | Histórico de pagamentos do cliente | ✓ Sem dificuldades (melhor - filtrado) |
| Filtrar por tipo/service | Validações list: PAGO/ATRASADO/NF/BOLETO | Filtros por tipo e status | ✓ Sem dificuldades |
| Ver valores totais | SUM automático no final da aba | Totais gerais por serviço/tipo | ✓ Sem dificuldades |

**Critério de aceitação:** Cliente consulta seus pagamentos e encontra o que procura sem dificuldades.

### Tarefa 8: Registrar uma despesa

| Aspecto | Planilha | LJ SmartClinic | Classificação |
|---------|----------|----------------|---------------|
| Nova despesa | Nova aba/mês em CONTAS .xlsx | Conta a pagar: nome, valor, vencimento, status | ⚠ Alguma dificuldade (nova estrutura) |
| Listar despesas | 50 abas meses | Listagem filtrada por status, tipo, período | ✓ Sem dificuldades (melhor) |
| Ver status (PAGO/ATRASADO) | Validações list: PAGO/ATRASADO | Status visual + filtro | ✓ Sem dificuldades |

**Critério de aceitação:** Cliente registra nova despesa e vê listagem filtrada sem dificuldades.

### Tarefa 9: Consultar relatórios

| Aspecto | Planilha | LJ SmartClinic | Classificação |
|---------|----------|----------------|---------------|
| Relatório de receitas | Totais por mês em aba | Dashboard + relatórios filtrados | ✓ Sem dificuldades (mais organizado) |
| Relatório de atendimentos | Contagem por mês | Gráfico/tabela de atendimentos por período | ✓ Sem dificuldades (mais organizado) |
| Relatório de pacotes | Sessões por mês | Minhas sessões ativas + restantes | ✓ Sem dificuldades (mais organizado) |

**Critério de aceitação:** Cliente consulta relatórios e encontra as informações esperadas sem dificuldades.

## 6.3 Critério de Aprovação

O critério de aprovação da Etapa 6 é:

**Pelo menos 80% das nove tarefas críticas devem ser classificadas como "Sem dificuldades" pela cliente.**

- Se 7 ou 8 tarefas forem "Sem dificuldades": Aprovação condicional — liberar uso do sistema, mas identificar as 1 ou 2 tarefas com dificuldade e corrigir na próxima sprint.
- Se 6 ou menos tarefas forem "Sem dificuldades": Revisar implementação da(s) tarefa(s) com dificuldade; não aprovar substituição ainda; voltar à Etapa correspondente para ajustes.
- Se qualquer tarefa for classificada como "Impossível": Bloquear substituição; analisar se é crítica ou pode ser contornada; implementar correção fundamental.

## 6.4 Atividades da Validação

### 6.4.1 Preparação

1. **Agendar horário** com cliente Daiane com antecedência mínima de 3 dias
2. **Preparar ambiente** — computador com acesso ao LJ SmartClinic (staging ou ambiente de desenvolvimento com dados reais mas anonimizados)
3. **Coletar materiais** — printscreen das planilhas relevantes para comparar durante a validação
4. **Definir gravação** — autorização da cliente para gravação de tela (opcional, para registro) ou tomar notas detalhadas

### 6.4.2 Durante a Validação

1. **Executar cada tarefa** em ordem, permitindo que a cliente tente sozinha primeiro
2. **Registrar classificação** e comentários para cada tarefa (formulário preenchido pelo auditor)
3. **Permitir "pause"** se cliente cansar ou precisar de pausa
4. **Não fornecer respostas** — se cliente travar, pedir que tente do seu jeito; se realmente impossível, marcar como "Impossível" e explicar o motivo
5. **Registrar sugestões** da cliente para melhoria (anexar em notas)

### 6.4.3 Finalização

1. **Compilar resultados** — planilha de classificação das 9 tarefas
2. **Aplicar critério de 80%** — verificar se aprovação ou necessidade de correções
3. **Documentar pendências** — tarefas com dificuldade e propostas de correção
4. **Decisão final** — em conjunto com a equipe técnica e aprovação da cliente

## 6.5 Cenários Possíveis

### Cenário A: Aprovação (80%+ "Sem dificuldades")

- ✅ Liberar uso do sistema como substituto das planilhas
- ✅ Descarte oficial das planilhas (já arquivadas em `/root/daiane-importacao-20261104-235412` com modo 700)
- ✅ Apresentação ao resto da equipe/cliente
- ✅ Criar documento de "Lições aprendidas" e melhores práticas

### Cenário B: Aprovação Condicional (7/9 ou 8/9 "Sem dificuldades")

- ✅ Liberar uso do sistema com identificação das tarefas com dificuldade
- ✅ Criar tickets de correção para as tarefas com dificuldade
- ✅ Corrigir as tarefas com dificuldade na próxima sprint (normalmente 1-2 semanas)
- ✅ Revalidar após correções (repetir as tarefas problemáticas)
- ✅ Se as correções voltarem a ser "Sem dificuldades": aprovação final

### Cenário C: Revisão Necessária (6 ou menos "Sem dificuldades")

- ⚠️ Não aprovar substituição ainda
- ⚠️ Identificar quais tarefas tiveram dificuldade e o motivo
- ⚠️ Retornar à Etapa correspondente (provavelmente Etapas 2, 3 ou 4 dependendo das dificuldades)
- ⚠️ Implementar correções fundamentais
- ⚠️ Revalidar após correções
- ⚠️ Se persistirem dificuldades, manter plano de ação e cronograma reestabelecido

### Cenário D: Bloqueio (qualquer tarefa "Impossível")

- ❌ Bloquear substituição imediatamente
- ❌ Analisar se a tarefa é crítica para operação da clínica
- ❌ Se crítica: desenvolver solução alternativa (pode ser manual, planilha híbrida, ou feature diferente)
- ❌ Se não crítica: documentar como "limitação conhecida" e prosseguir com restrições
- ❌ Documentar todas as decisões e manter registro para futuras melhorias

## 6.6 Formulário de Registro da Validação

| Tarefa | Classificação | Comentários | Correções Sugeridas |
|--------|---------------|-------------|--------------------|
| 1. Localizar cliente | □ Sem dificuldades □ Alguma dificuldade □ Impossível | | |
| 2. Consultar agenda | □ Sem dificuldades □ Alguma dificuldade □ Impossível | | |
| 3. Criar agendamento | □ Sem dificuldades □ Alguma dificuldade □ Impossível | | |
| 4. Registrar atendimento | □ Sem dificuldades □ Alguma dificuldade □ Impossível | | |
| 5. Dar baixa em sessão | □ Sem dificuldades □ Alguma dificuldade □ Impossível | | |
| 6. Registrar falta/reposição | □ Sem dificuldades □ Alguma dificuldade □ Impossível | | |
| 7. Consultar pagamentos | □ Sem dificuldades □ Alguma dificuldade □ Impossível | | |
| 8. Registrar despesa | □ Sem dificuldades □ Alguma dificuldade □ Impossível | | |
| 9. Consultar relatórios | □ Sem dificuldades □ Alguma dificuldade □ Impossível | | |

**Total "Sem dificuldades": ______ / 9**

**Critério de aprovação:** ______ ≥ 8 (aprovado) ou ______ ≤ 7 (revisar)

**Observações gerais da cliente:**

---

**Auditor responsável:** ________________________

**Data da validação:** ________________________

**Cliente (assinatura ou aceite digital):** ________________________

## 6.7 após a Validação

### Se Aprovação:

1. Mover planilhas de `storage/app/planilhas` (já removidas anteriormente) para arquivamento definitivo
2. Atualizar `docs/daiane/` com documento final de "Substituição Concluída"
3. Comunicar à equipe e clientes sobre a transição concluída
4. Criar treinamento resumido (1 página) do "Como fazer no sistema" em vez de planilha
5. Agendar follow-up em 30 dias para verificar se não surgiram novas necessidades

### Se Aprovação Condicional:

1. Criar tickets de correção para tarefas com dificuldade
2. Desenvolver correções na sprint subsequente
3. Revalidar após correções (repetir protocolo com as tarefas problemáticas)
4. Se aprovado: seguir passos do cenário A
5. Se não aprovado: voltar ao cenário C (revisão necessária)

### Se Revisão Necessária ou Bloqueio:

1. Documentar todas as dificuldades encontradas
2. Desenvolver plano de ação com prazos
3. Implementar correções fundamentais
4. Revalidar após correções
5. Se persistirem dificuldades, manter plano de ação e cronograma reestabelecido
6. Manter planilhas disponíveis em modo de leitura enquanto sistema operacional não atende totalmente

---

*Documento de validação da Etapa 6 — protocolo de aprovação da substituição das planilhas Daiane.*