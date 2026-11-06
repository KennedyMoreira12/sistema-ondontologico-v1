# Arquitetura do WhatsApp

Como uma mensagem entra, é atendida, é respondida e tem a entrega confirmada —
e onde cada peça mora.

## Visão geral

```
Paciente
   │  mensagem
   ▼
Evolution API  ──webhook──▶  AuthenticateEvolutionWebhook
   (gateway único)            (segredo + token da instância
   │                            resolvem a CLÍNICA)
   │                                   │
   │                                   ▼
   │                          EvolutionWebhookController
   │                          · dedupe por key.id (CAS)
   │                          · grava whatsapp_messages
   │                          · MESSAGES_UPDATE → delivery_status
   │                                   │
   │                                   ▼
   │                             N8nDispatcher  ──▶ n8n (opcional)
   │                                                  │
   │                                                  │ decide responder
   │                                                  ▼
   │                                    POST api/whatsapp/mensagem
   │                                                  │
   │                                                  ▼
   │                                          WhatsAppController
   │                                     flag whatsapp_outbox?
   │                                        │            │
   │                                       não          sim
   │                                        │            ▼
   │                                        │         Outbox::enqueue
   │                                        │            │
   │                                        │            ▼
   │                                        │      SendWhatsAppOutboxMessage
   │                                        │      (job com ids + TenantRunner)
   │                                        │            │
   └────── sendText / sendButtons / sendList ◀───────────────────────┴────────────┘
                                          EvolutionService
```

## Componentes

| Componente | Arquivo | Papel |
| --- | --- | --- |
| Middleware do webhook | `app/Http/Middleware/AuthenticateEvolutionWebhook.php` | Confere segredo e token da instância, resolve a clínica e aponta a conexão |
| Controller do webhook | `app/Http/Controllers/Api/EvolutionWebhookController.php` | Dedupe, gravação do histórico, status de entrega e despacho ao n8n |
| Leitura do payload | `app/Support/EvolutionPayload.php` | Tolerante a formato e a versão; nunca estoura por payload incompleto |
| Cliente da Evolution | `app/Services/EvolutionService.php` | Chamada HTTP com o token da instância; **sem retry próprio** |
| Menus interativos | `app/Services/WhatsApp/MenuInterativo.php` | Monta botões/lista a partir do estado, gera o payload da Evolution e o texto de fallback |
| Despacho ao n8n | `app/Services/N8nDispatcher.php` | Entrega o evento já resolvido; nunca manda token da clínica |
| API de envio | `app/Http/Controllers/Api/WhatsAppController.php` | Valida, escolhe caminho direto ou outbox, responde 200/202/502/503 |
| Outbox | `app/Services/WhatsApp/Outbox.php` | Enfileira texto ou menu, reivindica, envia, classifica falha, recupera, reproduz |
| Job de envio | `app/Jobs/SendWhatsAppOutboxMessage.php` | `$tries = 1`; carrega ids e reabre o tenant |
| Execução por clínica | `app/Services/Tenant/TenantRunner.php` | Troca a conexão, executa, restaura o estado anterior |
| Status de entrega | `app/Support/WhatsAppDeliveryStatus.php` | Tradução e atualização monotônica |
| Flags | `app/Services/WhatsApp/ClinicFeatures.php` | Leitura com fallback desligado e cache por clínica |
| Saúde | `app/Services/WhatsApp/WhatsAppHealth.php` | Retrato operacional sem enviar mensagem |
| Correlação | `app/Http/Middleware/AssignCorrelationId.php` | `X-Correlation-Id` em toda API |

## Onde cada dado mora

| Dado | Banco | Tabela |
| --- | --- | --- |
| Clínica, instância, token da Evolution | central | `clinics` |
| Flags de recurso | central | `clinic_feature_flags` |
| Token de integração do n8n (SHA-256) | central | `integration_tokens` |
| Conversa e histórico de mensagens | da clínica | `whatsapp_conversations`, `whatsapp_messages` |
| Fila de envio | da clínica | `whatsapp_outbox` |
| Configuração, regras, respostas e notas | da clínica | `clinic_settings`, `whatsapp_rules`, `whatsapp_quick_replies`, `whatsapp_notes`, `whatsapp_alerts` |

A separação é proposital: o operador da plataforma precisa enxergar
configuração, não a conversa de um paciente.

## Decisões de projeto

### A clínica vem do payload, nunca da URL

A rota do webhook não tem `{clinic}`. Quem conhece o nome da instância — que
aparece no próprio payload — poderia escrever no banco de outra clínica se o
path aceitasse um id. A resolução acontece no middleware, por
`evolution_instance`.

### Dedupe antes de qualquer efeito

O webhook da Evolution reentrega. O `INSERT` com índice único em
`whatsapp_messages.message_key` é o compare-and-swap: quem perde a corrida não
despacha ao n8n. Sem isso, a mesma mensagem geraria duas respostas.

### A outbox nasce antes do HTTP

Persistir depois do envio não cobre a queda no meio. A linha `pending` é criada
primeiro e o job a leva para `processing` com `UPDATE` condicional; só uma
execução vence.

### Retry conservador

| Situação | Decisão | Por quê |
| --- | --- | --- |
| 5xx e 429 | retry com backoff exponencial | a Evolution respondeu que não aceitou |
| 4xx, `error` no corpo, integração ausente | DLQ | não melhora repetindo |
| Recusa de conexão / DNS | retry | a requisição não saiu |
| Timeout | DLQ, **nunca** retry automático | a mensagem pode ter saído; repetir duplicaria no celular do paciente |
| Worker morto no meio | volta para `pending` após `stale_minutes` | o envio não chegou a concluir |

Backoff: `retry_base_seconds * 2^(tentativa-1)`, limitado por
`retry_max_seconds`, com `max_attempts` tentativas.

### Status monotônico

A Evolution manda `SERVER_ACK`, `DELIVERY_ACK`, `READ` e pode reentregar um
evento antigo. O ranking `pending < sent < delivered < read < failed` garante
que evento atrasado não rebaixa confirmação. `failed` é terminal.

`MESSAGES_UPDATE` **não** é reenviado ao n8n: o workflow já respondeu àquela
mensagem, e reenviar faria o paciente receber resposta repetida.

### Menus interativos com fallback obrigatório (Fase 3)

A Evolution `2.3.7` aceita `sendButtons` e `sendList`, mas o aparelho do
paciente pode não renderizar botões. Por isso o menu nunca substitui o texto:
`MenuInterativo` produz os dois no mesmo objeto.

- **Contrato interno**: `tipo` (`buttons`/`list`), `titulo`, `descricao`,
  `rodape`, `botao_lista` e `opcoes[{id, titulo, descricao}]`. O payload cru da
  Evolution (`footerText`, `sections`, `rowId`) fica em `MenuInterativo::payload()`,
  então uma troca de versão do gateway não obriga o n8n a mudar.
- **Ids por posição**: o `id` é a posição que o paciente viu (1, 2, 3...), não o
  id do banco. É o mesmo número que `itemEscolhido` já lê e que volta no
  `selectedRowId` do webhook.
- **Limites**: até 3 botões, 10 linhas de lista, título de 20/24 caracteres,
  descrição de linha com 72. Título repetido ganha sufixo; menu fora desses
  limites é recusado inteiro pela Evolution.
- **Flag**: `/fluxo` só inclui `interativo` com `interactive_menus` ligada, e
  `resposta` continua idêntica. Com a flag desligada a rota de envio devolve 409
  com `fallback_texto`, sem chamar a Evolution.
- **Decisão de envio**: recusa explícita (4xx ou `error` no corpo) → texto
  numerado (`sent_fallback`); timeout/5xx → 502 sem fallback, porque o menu pode
  ter saído e o texto duplicaria a mensagem.
- **Outbox**: a linha nasce com `kind` do menu, `payload` do contrato e `body`
  do texto. Uma recusa explícita reescreve a MESMA linha para `kind = text`,
  `payload = null` e `last_error_code = interativo_recusado_fallback`; não há
  segunda mensagem para o mesmo evento.

Não há migration da Fase 3: `kind`, `payload` e `body` vieram na outbox da
Fase 1.

### Fila sem contexto de tenant

O job carrega `clinicId` e `outboxId`. O `TenantRunner` reabre o banco da
clínica, executa e restaura a conexão anterior — inclusive quando o job roda no
mesmo processo de outro tenant. É o que permite um worker atender todas as
clínicas sem cruzar dados.

### `QUEUE_CONNECTION=sync` é suportado

Com fila síncrona o job roda dentro do request: a resposta sai `sent` ou já com
a falha classificada, e a linha persistida, as tentativas e a DLQ continuam
existindo. Com worker, a resposta é `202 queued` e quem envia é o worker. O
código detecta o resultado pelo estado da linha, não pela configuração de fila.

### Flags desligadas por padrão

`ClinicFeatures::enabled()` lê o banco central com fallback em
`config/whatsapp.php`, onde tudo é `false`. A ausência de linha significa
"comportamento atual". Consultar a tabela direto é proibido: transforma flag
ausente em decisão implícita.

## Precedência de atendimento (implementada na Fase 2)

```
opt-out
   > pausa / aguardando atendente
      > fora do expediente
         > regra automática
            > IA
               > fluxo determinístico
```

A precedência é do mais restritivo para o mais permissivo: qualquer camada que
mande calar o bot vence as demais. Ela mora em `Triagem::decidir()` e é chamada
antes da máquina de estados, tanto pela rota de fluxo quanto pela rota com IA.
Isso é deliberado: se a checagem ficasse dentro de `FluxoAtendimento`, uma
regra poderia responder no meio da escolha de horário.

Passos com contrato próprio (`consentimento`, `cadastro`, `profissional`,
`procedimento`, `data`, `horario`), comandos globais (`menu`, `inicio`,
`cancelar`, `sair`) e escolhas numéricas vão direto ao fluxo. Neles o texto é
dado do passo, não pergunta livre.

`bot_status` decide quem responde: `ativo` deixa o bot falar; `pausado` (com ou
sem prazo), `humano` e `aguardando_atendente` mantêm silêncio. Pausa com prazo
volta sozinha quando vence; pausa sem prazo (opt-out) só volta com retomada
explícita.

## Endpoints

| Método | Rota | Autenticação | Saída |
| --- | --- | --- | --- |
| POST | `api/evolution/webhook` | segredo + token da instância | `received`, `duplicate`, `status_updated`, `ignored` |
| POST | `api/whatsapp/mensagem` | token de integração (`api.whatsapp.mensagem`) | 200 `sent`, 202 `queued`, 502/503 `failed` |
| POST | `api/whatsapp/mensagem-interativa` | token (`messages.send`) | 200 `sent`/`sent_fallback`, 202 `queued`, 409 flag desligada, 422 menu inválido, 502/503 `failed` |
| POST | `api/whatsapp/fluxo` | token (`api.whatsapp.fluxo`) | resposta do fluxo ou da triagem (`handled_by`) |
| POST | `api/whatsapp/ia` | token (`api.whatsapp.ia`) | resposta da IA, da triagem ou do fluxo |
| POST | `api/whatsapp/conversa/handoff` | token (`conversations.handoff`) | assume a conversa e resolve os alertas |
| POST | `api/whatsapp/conversa/retomar` | token (`conversations.handoff`) | devolve a conversa ao bot |
| GET | `api/whatsapp/alertas` | token (`conversations.handoff`) | fila de alertas abertos |
| GET | `api/whatsapp/respostas-rapidas` | token (`conversations.handoff`) | respostas prontas ativas |
| GET/POST | `api/whatsapp/notas` | token (`conversations.handoff`) | lista/cria nota interna |
| GET | `api/whatsapp/saude` | token de integração | integração, outbox, mensagens, flags |
| GET | `api/whatsapp/info` | token de integração | diagnóstico da integração |

## Limites conhecidos

- `forwarded_at` é reservado antes do envio ao n8n; falha do n8n não redespacha
  sozinha. Ponto frágil herdado, fora do escopo da Fase 1.
- Os menus existem na API, mas só entram no atendimento real quando o workflow do
  n8n passar a usar `interativo`/a rota `mensagem-interativa`. A renderização no
  aparelho do paciente depende do WhatsApp e não é controlada pelo Laravel; por
  isso o fallback textual é sempre gerado.
- O histórico guarda o texto achatado do menu; mídia não é baixada.
- A fila da recepção é uma API (`alertas`), não uma tela; a inbox chega na Fase 4.
- Regras valem só em linguagem livre. Passo com contrato e comando global nunca
  são interceptados — é uma decisão de segurança, não uma limitação a corrigir.
- O aviso de ausência usa `Cache::add`; com `CACHE_STORE=array` (só em teste) o
  cooldown não sobrevive entre requisições, o que é aceitável no ambiente de
  teste e não muda o comportamento em produção.
