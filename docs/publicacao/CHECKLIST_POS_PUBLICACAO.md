# CHECKLIST PÓS-PUBLICAÇÃO — LJ SmartClinic

**Versão de referência:** commit `d73d2c1ae37368c32f0b063d226aecd03acfec8e`  
**Ambiente:** execução na VPS por administrador autorizado  
**Destino:** `minha-vps` (179.236.238.61), `/var/www/ljsmartclinic`

---

## 1. Objetivo

Verificar, após a publicação da implementação da Daiane, que o sistema está funcionando corretamente, sem regressões e com isolamento preservado entre todos os tenants.

---

## 2. Checklist de infraestrutura

| # | Item | Status | Observação |
|---|---|---|---|
| 1 | Aplicação inicializa sem erros 500 | ☐ | |
| 2 | Login funcional para usuários de cada tenant | ☐ | |
| 3 | Identificação de tenant (ResolveTenant) funciona | ☐ | Cada usuário conecta ao banco da própria clínica |
| 4 | Cache limpo (config, rotas, views, autoload) | ☐ | `config:clear`, `route:clear`, `view:clear`, `dump-autoload` |
| 5 | Workers de fila rodando | ☐ | Se `QUEUE_CONNECTION=redis` |
| 6 | Agendador (cron) ativo | ☐ | `schedule:run` |
| 7 | Backups verificados antes da publicação | ☐ | Conforme `PLANO_BACKUP_RESTAURACAO.md` |

---

## 3. Checklist de módulos da Daiane (`clinic_000005`)

| # | Tarefa | Status | Observação |
|---|---|---|---|
| 1 | Cadastrar paciente | ☐ | `pacientes.create` / `pacientes.store` |
| 2 | Criar serviço/pacote | ☐ | `pacotes.create` / `pacotes.store` |
| 3 | Agendar atendimento | ☐ | `agenda.store` (com idempotência) |
| 4 | Registrar presença | ☐ | `agenda.presenca` com `status=atendido` |
| 5 | Registrar falta | ☐ | `agenda.presenca` com `status=faltou` |
| 6 | Consumir sessão do pacote | ☐ | `atendimentos.consumir` (idempotente por appointment) |
| 7 | Registrar pagamento (conta a receber) | ☐ | `financeiro.receive` |
| 8 | Lançar conta a pagar | ☐ | `contas-pagar.store` |
| 9 | Registrar baixa da conta a pagar | ☐ | `contas-pagar.baixar` (idempotente) |
| 10 | Consultar relatórios | ☐ | `relatorios.receitas`, `presenca-faltas`, `pacotes` |
| 11 | Exportar CSV | ☐ | `relatorios.exportar/{tipo}` (BOM UTF-8, `;`) |

---

## 4. Checklist de isolamento multi-tenant

| # | Verificação | Status | Observação |
|---|---|---|---|
| 1 | Daiane (`clinic_000005`) não vê dados da Malheiro (`clinic_000001`) | ☐ | HTTP: 404 para ID de outra clínica |
| 2 | Malheiro (`clinic_000001`) não vê dados da Daiane | ☐ | HTTP: 404 para ID de outra clínica |
| 3 | Demais tenants não veem dados de Daiane nem Malheiro | ☐ | |
| 4 | Login de Daiane conecta ao banco `clinic_000005` | ☐ | |
| 5 | Login de Malheiro conecta ao banco `clinic_000001` | ☐ | |

---

## 5. Checklist de financeiro (sem emissão fiscal real)

| # | Verificação | Status | Observação |
|---|---|---|---|
| 1 | Contas a pagar: resumo usa saldo (`valor - baixas`) | ☐ | `emAberto`, `atrasado`, `vencendo` |
| 2 | Conta a pagar parcialmente paga continua `pendente` | ☐ | Status calculado pelo saldo |
| 3 | Baixa duplicada com mesma chave é recusada | ☐ | `baixaDuplicada` |
| 4 | Chave de idempotência de outra conta é recusada | ☐ | Nunca devolve baixa de outra conta |
| 5 | Cancelamento de conta com baixas é recusado | ☐ | `contaComBaixas` (transacional) |
| 6 | Total em aberto desconta `valor_pago` | ☐ | `financeiro.index` |
| 7 | Período `de > até` é normalizado | ☐ | Relatórios e fluxo de caixa |
| 8 | SendNF segue mock (sem documentos fiscais reais) | ☐ | Não emitir NFSe |

---

## 6. Checklist de WhatsApp (sem mensagens reais)

| # | Verificação | Status | Observação |
|---|---|---|---|
| 1 | Agendamento via WhatsApp usa `idempotency_key` | ☐ | Não duplica em reenvio |
| 2 | Rotas de WhatsApp funcionam com mocks | ☐ | Sem envio real de mensagens |
| 3 | Evolution API credenciais não alteradas | ☐ | |
| 4 | n8n credenciais não alteradas | ☐ | |

---

## 7. Checklist de regressão (demais clínicas)

| # | Verificação | Status | Observação |
|---|---|---|---|
| 1 | Malheiro (`clinic_000001`) funciona normalmente | ☐ | Sem alteração de experiência |
| 2 | Demais tenants funcionam normalmente | ☐ | Sem regressão |
| 3 | Rotas administrativas (painel) funcionam | ☐ | `admin.*` |
| 4 | Módulos planejados (`modulos.planejado`) funcionam | ☐ | |

---

## 8. Condição de liberação

A publicação está liberada somente quando **todos** os itens acima estão marcados como concluídos. Qualquer item não resolvido bloqueia a liberação e deve ser documentado em `docs/daiane/PENDENCIAS_PUBLICACAO.md`.

---

## 9. Registro de execução

| Campo | Valor |
|---|---|
| Executado por | ________________________ |
| Data/Hora | ________________________ |
| Versão publicada | `d73d2c1ae37368c32f0b063d226aecd03acfec8e` |
| Ambiente | VPS `minha-vps` (179.236.238.61) |
| Resultado | ☐ Aprovado ☐ Bloqueado |
| Assinatura | ________________________ |
