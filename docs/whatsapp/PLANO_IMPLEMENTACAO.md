# Plano de implementação — WhatsApp do LJ SmartClinic

Documento mestre das sete fases de evolução do atendimento por WhatsApp.
Cada fase tem escopo fechado, critérios de aceite e um checkpoint: nada da fase
seguinte começa antes de a anterior estar verificada.

## Princípios que valem para todas as fases

- O **Laravel é a fonte da verdade**. A agenda, o paciente e o histórico moram
  no banco da clínica; nenhum sistema externo decide por ele.
- A **Evolution API continua sendo o único gateway**. Não há segundo gateway,
  segunda sessão nem troca de provedor dentro deste plano.
- O **n8n é opcional**. O atendimento precisa funcionar sem ele, com fluxo
  determinístico; com ele, o workflow orquestra a conversa.
- A **IA propõe, o Laravel decide**. A IA nunca escreve na agenda, nunca muda
  preço e nunca responde sozinha quando a regra manda pausar.
- **Isolamento por banco de clínica**. O banco central guarda configuração e
  monitoramento, nunca dado de paciente.
- **Flags por clínica, desligadas por padrão**. Código novo entra em produção
  sem mudar comportamento até alguém ligar o recurso para uma clínica.
- **Nada operacional sem autorização**: migration em produção, ativação de
  flag, deploy, restart de worker e mudança de fila são passos separados e
  explícitos.

## Matriz das fases

| Fase | Tema | Entrega principal | Estado |
| --- | --- | --- | --- |
| 0 | Auditoria e baseline | Diagnóstico, testes medidos, riscos | concluída |
| 1 | Confiabilidade | Outbox, status de entrega, retry/DLQ, correlação, healthcheck | implementada, aguardando autorização operacional |
| 2 | Regras e pausa | Regras determinísticas, expediente, pausa/handoff por conversa | implementada, aguardando autorização operacional |
| 3 | Menus e agendamento | Botões/listas, agendamento guiado | implementada, aguardando autorização operacional e ajuste do n8n |
| 4 | Inbox e humano | Caixa de entrada, assumir conversa, notas e etiquetas | planejada |
| 5 | IA e n8n opcional | Resumo por IA, n8n como caminho opcional | planejada |
| 6 | Monitoramento e testes | Painel operacional, métricas, cobertura das fases | planejada |
| 7 | Ativação gradual | Rollout por clínica, treinamento, documentação final | planejada |

## Fase 1 — Confiabilidade (implementada)

### Problema que ela resolve

O envio de mensagem era uma chamada HTTP dentro do request. Se a Evolution
caísse depois de o n8n já ter recebido `200`, a resposta se perdia sem
registro. Se o processo morresse entre o envio e a gravação do histórico, a
mensagem existia no WhatsApp e não no banco. E não havia como saber se uma
mensagem foi aceita, entregue ou lida.

### O que foi entregue

- `whatsapp_outbox` no banco da clínica: a linha nasce **antes** do envio e
  guarda status, tentativas, próximo horário, erro e correlação.
- Job `SendWhatsAppOutboxMessage` com apenas ids no payload, reabrindo o banco
  da clínica dona da mensagem via `TenantRunner`.
- Reivindicação por compare-and-swap: duas execuções nunca enviam a mesma linha.
- Classificação conservadora de falha: retry só quando é seguro; timeout vai
  para a DLQ porque reenviar pode duplicar a mensagem no celular do paciente.
- `MESSAGES_UPDATE` no webhook atualizando `delivery_status`, com atualização
  monotônica (`pending < sent < delivered < read < failed`).
- `X-Correlation-Id` em toda chamada de API, gravado nas linhas e devolvido no
  header.
- `GET api/whatsapp/saude` e `php artisan whatsapp:health` com o mesmo
  retrato operacional.
- Recuperação periódica (`whatsapp:outbox:recover`) agendada a cada minuto e
  replay manual de DLQ (`whatsapp:outbox:replay`).
- Flags por clínica em `clinic_feature_flags`, todas desligadas.

### Critérios de aceite

| Critério | Situação |
| --- | --- |
| Flag desligada preserva o envio direto e não cria linha na outbox | atendido (teste) |
| Flag ligada persiste antes de enviar e registra histórico com correlação | atendido (teste) |
| `idempotency_key` repetida não gera segunda mensagem | atendido (teste) |
| 5xx/429 agenda retry e a recuperação reenvia | atendido (teste) |
| 4xx e erro no corpo vão para a DLQ e não voltam sozinhos | atendido (teste) |
| Timeout vai para a DLQ e nunca é reenviado automaticamente | atendido (teste) |
| Worker sem tenant processa a clínica dona da linha e não vaza para outra | atendido (teste) |
| Status de entrega não rebaixa confirmação e não é reenviado ao n8n | atendido (teste) |
| Linha órfã em `processing` volta para a fila | atendido (teste) |
| Replay manual da DLQ funciona | atendido (teste) |
| Healthcheck não chama a Evolution sem pedido explícito | atendido (teste) |
| Suíte geral sem regressão nova | atendido (ver `RESULTADOS_TESTES.md`) |
| Migration aplicada em produção | **pendente de autorização** |
| Flag ligada em alguma clínica | **pendente de autorização** |

### Fora do escopo da Fase 1

- Botões e listas interativas.
- Lembretes de consulta.
- Inbox, etiquetas, notas e download de mídia.
- Regras automáticas de resposta e horário de funcionamento (a configuração
  existe; o comportamento foi entregue na Fase 2).
- Correção do bug de `botAtivo()` com `pausado_ate` nulo (corrigido na Fase 2).
- Correção do `TenantProvisioner::startSubscription()` (bug preexistente,
  fora do tema).

## Fase 2 — Regras e pausa (implementada)

### Problema que ela resolve

Toda mensagem em linguagem livre dependia da IA ou caía no menu. Endereço,
horário e convênio eram respondidos pelo modelo quando ele estava no ar, e não
havia resposta quando ele faltava. Fora do expediente o bot continuava
atendendo como se a clínica estivesse aberta. A pausa sem prazo não calava o
bot — `botAtivo()` tratava `pausado_ate` nulo como ativo — e a recepção não
tinha como saber que alguém estava esperando por um humano.

### O que foi entregue

- `whatsapp_rules` no banco da clínica: `exact`, `contains` e `regex`, com
  prioridade, ativação e escopo (`menu` ou `livre`). Regex inválida é ignorada
  com log e não derruba o atendimento.
- Casamento de texto sem diferenciar caixa, acento ou espaços: "endereço" e
  "endereco" são a mesma pergunta.
- Precedência em um lugar só (`Triagem`), aplicada tanto na rota de fluxo quanto
  na rota com IA: opt-out > pausa/humano > expediente > regra > IA > fluxo.
- Passos com contrato próprio (consentimento, cadastro, profissional,
  procedimento, dia, horário), comandos globais e escolha por número nunca são
  sequestrados por regra.
- Expediente por clínica em `clinic_settings`, no fuso de `clinics.timezone`,
  com mensagem de ausência e cooldown para não repetir o aviso a cada mensagem.
- Fora do expediente a conversa é pausada até a próxima abertura e entra na
  fila da recepção; o bot volta sozinho no horário configurado.
- Opt-out por palavra inteira: pausa sem prazo, só desfeita por retomada
  explícita. Em conversa já silenciosa (humano), o pedido é registrado sem o bot
  falar por cima do atendente.
- `whatsapp_alerts` como fila da recepção; `GET api/whatsapp/alertas` lista os
  alertas abertos com tempo de espera e `handoff` resolve os da conversa.
- `whatsapp_quick_replies` e `whatsapp_notes`: respostas prontas só são lidas
  (quem envia é o atendente) e nota interna não entra no histórico nem leva o
  texto para a auditoria.
- Correção de `botAtivo()` e centralização da abertura/reset de conversa em
  `WhatsAppConversation::abrirPara()`, preservando `bot_status` no reset.
- Endpoints `alertas`, `respostas-rapidas`, `notas` (GET/POST) sob a ability
  `conversations.handoff`; as quatro tabelas novas entraram no inventário de
  `InventarioTabelas`.

### Critérios de aceite

| Critério | Situação |
| --- | --- |
| `exact`, `contains` e `regex` respondem conforme prioridade e ativação | atendido (teste) |
| Escopo `menu`/`livre` e etapas com contrato não são atropelados por regra | atendido (teste) |
| Comando global (`menu`) continua funcionando mesmo com regra que casaria | atendido (teste) |
| Precedência opt-out > pausa > expediente > regra > fluxo | atendido (teste) |
| Pausa com prazo volta sozinha na próxima abertura | atendido (teste) |
| Pausa sem prazo (opt-out) só volta com retomada explícita | atendido (teste) |
| Expediente usa o fuso da clínica, não o da aplicação | atendido (teste) |
| Ausência avisa uma vez (cooldown) e abre alerta para a recepção | atendido (teste) |
| Handoff cala o bot mesmo sem IA e resolve os alertas da conversa | atendido (teste) |
| Nota interna não entra em `whatsapp_messages` nem leva texto à auditoria | atendido (teste) |
| Respostas rápidas listam apenas as ativas | atendido (teste) |
| Fila e notas exigem a ability de atendimento humano | atendido (teste) |
| Suíte geral sem regressão nova | atendido (ver `RESULTADOS_TESTES.md`) |
| Migration aplicada em produção | **pendente de autorização** |
| Flag ligada em alguma clínica | **pendente de autorização** |

### Fora do escopo da Fase 2

- Tela de inbox e etiquetas (Fase 4).
- Lembretes de consulta (Fase 4).
- Resumo por IA e n8n como caminho opcional (Fase 5).
- Correção do `TenantProvisioner::startSubscription()` (bug preexistente, fora
  do tema).

`bot_pause`, `bot_paused_at` e `proximo` continuam exclusivos do fluxo de
**prospecção** no banco central. A pausa do atendimento usa `bot_status` e
`pausado_ate` na conversa, dentro do banco da clínica.

## Fase 3 — Menus e agendamento (implementada)

### Problema que ela resolve

O fluxo já agenda de ponta a ponta, mas tudo por texto numerado: o paciente
precisa digitar o número e a conversa fica longa. A Evolution instalada aceita
botões e listas, porém o suporte depende do aparelho e da conta do paciente.
Trocar o texto pelo menu sem fallback quebraria justamente quem não renderiza o
formato interativo.

### O que foi entregue

- Verificação **read-only** da Evolution `2.3.7` instalada: `POST
  /message/sendButtons/{instance}` aceita `title`, `description`, `footer` e
  `buttons[{type: 'reply', displayText, id}]`; `POST /message/sendList/{instance}`
  exige `number`, `title`, `footerText`, `buttonText` e
  `sections[{title, rows[{title, rowId}]}]`, com `description` de linha limitada
  a 72 caracteres. Nenhuma mudança foi feita no gateway.
- `MenuInterativo` monta o menu a partir do **estado já gravado** da conversa
  (`consentimento`, `menu`, `profissional`, `procedimento`, `data`, `horario`) e
  gera, no mesmo objeto, o payload da Evolution e o texto numerado equivalente.
- O `id` de cada opção é a **posição na lista**, não o id do banco: é o que o
  fluxo já lê em `itemEscolhido` e o que volta no `selectedRowId`. Botões ficam
  em no máximo 3, listas em no máximo 10 linhas, títulos em 20/24 caracteres,
  descrições em 72; títulos repetidos ganham sufixo para não derrubar o menu.
- `/fluxo` passa a expor a chave **aditiva** `interativo` quando a flag
  `interactive_menus` está ligada. `resposta` continua sendo o texto completo:
  o workflow atual do n8n segue funcionando sem alteração.
- Rota própria `POST api/whatsapp/mensagem-interativa`, com a ability
  `messages.send`. Flag desligada responde 409 com `fallback_texto` sem enviar
  nada; recusa explícita da Evolution (4xx ou `error` no corpo) envia o texto
  numerado e devolve `sent_fallback`; timeout/5xx responde 502 **sem** fallback
  para não duplicar a mensagem no celular.
- Na outbox, a linha guarda `kind` (`buttons`/`list`), `payload` (contrato
  interno) e `body` (texto de fallback). Se a Evolution recusar o formato, a
  mesma linha passa a `kind = text`, `payload = null` e recebe
  `last_error_code = interativo_recusado_fallback`, sem criar segunda mensagem.
- Nenhuma migration nova: `kind`, `payload` e `body` já existiam na outbox da
  Fase 1.
- O agendamento guiado em si não mudou: procedimento → profissional → dia →
  horário → confirmação continua criando a consulta pelo `AgendamentoService`,
  com as mesmas validações. Os menus mudam a apresentação, não a regra.

### Critérios de aceite

| Critério | Situação |
| --- | --- |
| Versão instalada da Evolution confirma suporte a botões e listas | atendido (verificação read-only em `2.3.7`) |
| Flag desligada mantém `/fluxo` sem chave nova e não chama a Evolution | atendido (teste) |
| Flag ligada expõe `interativo` sem alterar `resposta` | atendido (teste) |
| Botões/listas respeitam limites e usam a posição como id | atendido (teste) |
| Payload segue o schema real de `sendButtons`/`sendList` | atendido (teste) |
| Recusa explícita do formato cai para o texto e registra histórico | atendido (teste) |
| Timeout/5xx não disparam fallback (evita duplicar) | atendido (teste) |
| Outbox guarda o menu e reenvia como texto na mesma linha | atendido (teste) |
| Títulos repetidos são desambiguados e não derrubam o menu | atendido (teste) |
| Envio interativo exige ability e respeita a flag (409) | atendido (teste) |
| Suíte geral sem regressão nova | atendido (ver `RESULTADOS_TESTES.md`) |
| Workflow do n8n passa a usar `interativo`/a rota nova | **pendente** (fora do repositório) |
| Flag ligada em alguma clínica | **pendente de autorização** |
| Migration aplicada em produção | não se aplica (sem migration nova) |

### Fora do escopo da Fase 3

- Alterar o workflow do n8n: sem esse passo, os menus existem na API mas não são
  usados no atendimento real.
- Garantir que o aparelho do paciente renderize botões/listas — depende do
  cliente de WhatsApp e da conta; por isso o fallback é obrigatório.
- Tela de inbox, etiquetas e lembretes (Fase 4).
- Resumo por IA e n8n como caminho opcional (Fase 5).

## Fase 4 — Inbox e humano (planejada)

- Caixa de entrada por clínica, com conversa atribuída, etiquetas e notas.
- Assumir/liberar conversa com registro de quem assumiu e quando.
- Indicação de mensagens não lidas e de falha de entrega.
- Nenhuma ação do painel envia mensagem sem passar pela outbox.

## Fase 5 — IA e n8n opcional (planejada)

- Resumo de conversa por IA, sempre como sugestão revisável.
- n8n como caminho opcional: sem ele, fluxo determinístico; com ele, o webhook
  continua recebendo o evento já resolvido.
- Custo por clínica (`CotaIA`) respeitado antes de qualquer chamada de modelo.

## Fase 6 — Monitoramento e testes (planejada)

- Painel de saúde por clínica: fila, DLQ, entrega, conexão.
- Métricas de tempo de resposta e de conversas sem resposta.
- Cobertura de teste por fase, com baseline registrado.

## Fase 7 — Ativação gradual (planejada)

- Rollout em uma clínica piloto, com acompanhamento diário.
- Runbook de plantão e critérios de desligamento.
- Treinamento da recepção e documentação final.

## Dependências de infraestrutura

| Dependência | Situação atual | Impacto |
| --- | --- | --- |
| Migration de tenant (`whatsapp_outbox` + colunas em `whatsapp_messages`) | criada no repositório, **não aplicada** | sem ela a flag não pode ser ligada |
| Migration de tenant da Fase 2 (`whatsapp_rules`, `whatsapp_quick_replies`, `whatsapp_notes`, `whatsapp_alerts`) | criada no repositório, **não aplicada** | sem ela as regras e a fila da recepção não funcionam |
| Migration central (`clinic_feature_flags`) | criada no repositório, **não aplicada** | sem ela não há como ligar flag |
| `QUEUE_CONNECTION` de produção | a confirmar | `sync` funciona, mas sem retry real nem worker |
| Worker + Supervisor | não configurado | necessário para outbox fora do modo `sync` |
| Cron do `schedule:run` | a confirmar | sem ele a recuperação não roda sozinha |
| Versão da Evolution | `2.3.7` confirmada em verificação read-only | `sendButtons`/`sendList` disponíveis; o fallback textual continua obrigatório |
| Workflow do n8n | usa apenas `resposta` hoje | os menus só passam a ser enviados depois de o workflow usar `interativo`/a rota nova |
