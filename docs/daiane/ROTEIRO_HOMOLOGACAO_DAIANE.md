# ROTEIRO DE HOMOLOGAÇÃO — Daiane (clinic_id=5 / clinic_000005)

**Objetivo:** confirmar que a Daiane consegue realizar, no LJ SmartClinic, as nove tarefas críticas que hoje faz em planilhas, com o mesmo ou melhor nível de facilidade.  
**Critério de aprovação (do ETAPA_6_VALIDACAO.md):** pelo menos 8 de 9 tarefas classificadas como "Sem dificuldades".  
**Ambiente:** demo em SQLite (`.env.demo`), tenant `clinic_000005`.  
**Login:** `daiane.demo@ljdemo.test` / `demo12345`.

> As classificações abaixo são expectativas de projeto, não resultados de sessão real. São preenchidas no formulário da seção 6 durante a validação com a cliente.

---

## 1. Preparação

1. Agendar horário com a cliente Daiane (antecedência mínima de 3 dias).
2. Preparar o ambiente demo:
   ```powershell
   php artisan migrate --env=demo --force --no-interaction
   php artisan tenant:create "Clínica Daiane Teixeira (demo)" --id=5 `
       --email=daiane.demo@ljdemo.test --admin="Daiane Teixeira" `
       --password=demo12345 --vertical=fisioterapia --plan=pro `
       --env=demo --no-interaction
   php artisan tenants:seed --clinic=5 --demo-daiane --env=demo --no-interaction
   ```
   Reexecutar `tenants:seed` não duplica dados (guarda de reexecução). Para recriar do zero, apagar `storage/app/demo/tenants/clinic_000005.sqlite`, criar arquivo vazio, rodar `tenants:migrate --clinic=5` e `tenants:seed`.
3. Coletar print screens das planilhas relevantes para comparar durante a validação.
4. (Opcional) Definir gravação de tela ou anotações detalhadas.

---

## 2. Nove tarefas críticas

### Tarefa 1 — Localizar uma cliente
- Buscar paciente por nome.
- Consultar histórico (appointments, evoluções, avaliações).
- Buscar por variações de nome (ex.: GISELE / GISELE ALVES / GISELE LVES) — o sistema alerta candidatos, **não faz merge automático**.
- **Critério de aceitação:** a cliente localiza seu registro sem ajuda técnica.

### Tarefa 2 — Consultar a agenda
- Ver horários da semana (visualização Semana/Dia/Mês).
- Ver próprios agendamentos.
- Buscar por data específica com seletor de data.
- **Critério de aceitação:** consulta a agenda e encontra seus agendamentos sem dificuldades.

### Tarefa 3 — Criar um agendamento
- Formulário: data, profissional, serviço, sala, valor, observação, retorno.
- Seleção de horário disponível (lista gerada pelo sistema, respeita grade e conflitos).
- Confirmação com mensagem de sucesso e appointment criado.
- **Critério de aceitação:** cria novo agendamento sem erros e confirma na tela.

### Tarefa 4 — Registrar atendimento
- Marcar presença (botão "Presente").
- Marcar falta (botão "Falta").
- Ver status imediatamente atualizado.
- **Critério de aceitação:** registra presença ou falta e o status é atualizado imediatamente.

### Tarefa 5 — Dar baixa em uma sessão (consumo de pacote)
- Tela de consumo de pacote: decrementar sessões.
- Ver sessões restantes (total − usadas).
- Consumo idempotente: marcar presença duas vezes não debita duas sessões.
- **Critério de aceitação:** entende como baixar sessão e vê quantidade restante.

### Tarefa 6 — Registrar falta ou reposição
- Registrar falta (botão "Falta").
- **Bloqueio:** falta após consumo de sessão é recusada com mensagem para estornar a sessão antes.
- Reposição: novo agendamento ou campo de reposição no consumo.
- **Critério de aceitação:** registra falta sem dificuldades; reposição é explicada ou alternativa oferecida.

### Tarefa 7 — Consultar pagamentos
- Histórico de pagamentos do cliente, filtrável.
- Filtrar por tipo/serviço e status.
- Ver valores totais por serviço/tipo.
- **Critério de aceitação:** consulta pagamentos e encontra o que procura sem dificuldades.

### Tarefa 8 — Registrar uma despesa (conta a pagar)
- Nova despesa: nome, valor, vencimento, tipo, fornecedor/categoria.
- Listar despesas filtradas por status, tipo e período.
- Ver status visual (pendente / pago / cancelado; "atrasado" calculado pelo vencimento).
- Baixa de conta a pagar com idempotência (duplo clique não duplica baixa).
- **Critério de aceitação:** registra nova despesa e vê listagem filtrada sem dificuldades.

### Tarefa 9 — Consultar relatórios
- Relatório de receitas (por mês, por serviço).
- Relatório de presença/faltas.
- Relatório de pacotes (sessões contratadas / usadas / restantes).
- Exportar CSV (BOM UTF-8, separador `;`, proteção contra fórmulas).
- **Critério de aceitação:** consulta relatórios e encontra as informações esperadas sem dificuldades.

---

## 3. Formulário de registro da validação

| Tarefa | Classificação | Comentários | Correções sugeridas |
|---|---|---|---|
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
**Critério de aprovação:** ≥ 8 aprovado | ≤ 7 revisar (cenário C) | qualquer "Impossível" bloqueia (cenário D).

**Auditor responsável:** ________________________  
**Data da validação:** ________________________  
**Cliente (assinatura/aceite digital):** ________________________

---

## 4. Durante a validação (instruções ao auditor)

1. Executar cada tarefa em ordem, permitindo que a cliente tente sozinha primeiro.
2. Registrar classificação e comentários para cada tarefa.
3. Permitir pausa se a cliente cansar.
4. Não fornecer respostas: se travar, pedir que tente do seu jeito; se realmente impossível, marcar "Impossível" e explicar o motivo.
5. Registrar sugestões da cliente para melhoria.

---

## 5. Cenários possíveis após a validação

### Cenário A — Aprovação (≥ 8 "Sem dificuldades")
- Liberar uso do sistema como substituto das planilhas.
- Arquivar planilhas definitivamente (já estão em `/root/daiane-importacao-20261104-235412`, modo 700).
- Apresentar ao restante da equipe/cliente.
- Criar documento de "Lições aprendidas".

### Cenário B — Aprovação condicional (7 ou 8 "Sem dificuldades")
- Liberar uso com identificação das tarefas com dificuldade.
- Criar tickets de correção; corrigir na próxima sprint; revalidar.

### Cenário C — Revisão necessária (≤ 7 "Sem dificuldades")
- Não aprovar substituição ainda.
- Identificar tarefas com dificuldade e motivo; retornar à etapa correspondente.
- Implementar correções fundamentais e revalidar.

### Cenário D — Bloqueio (qualquer "Impossível")
- Bloquear substituição imediatamente.
- Analisar se a tarefa é crítica; desenvolver alternativa ou documentar limitação conhecida.

---

## 6. Após a validação (se aprovado)

1. Mover planilhas para arquivamento definitivo.
2. Atualizar `docs/daiane/` com documento final de "Substituição Concluída".
3. Comunicar à equipe sobre a transição concluída.
4. Criar treinamento resumido (1 página) de "Como fazer no sistema".
5. Agendar follow-up em 30 dias.

---

## 7. Comandos úteis para o ambiente de homologação

```powershell
# Rodar suíte completa
& 'C:\Users\User\AppData\Local\Temp\opencode\php\php.exe' artisan test --compact --env=demo

# Pint (formatação)
vendor/bin/pint --dirty --format agent --env=demo

# Recriar demo da do zero
# (no PowerShell, no bash do WSL, etc., conforme o ambiente)
rm storage/app/demo/tenants/clinic_000005.sqlite
New-Item storage/app/demo/tenants/clinic_000005.sqlite -ItemType File
php artisan tenants:migrate --clinic=5 --env=demo --no-interaction
php artisan tenants:seed --clinic=5 --demo-daiane --env=demo --no-interaction
```

**Não executar:** push, deploy, migration em produção, alteração de VPS, dados reais, DNS, SSL, Evolution API, n8n ou credenciais.
