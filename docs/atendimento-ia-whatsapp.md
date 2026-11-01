# Atendimento com IA no WhatsApp

Este documento cobre a camada de IA do atendimento do WhatsApp: o que foi
adicionado, como configurar, como operar e como reverter. Ele **não** descreve a
ativação do bot em produção — o workflow `LJAtendimento01` continua
desabilitado, e nada aqui o liga.

## O que existe

- **`ClienteIA`** (`app/Services/WhatsApp/ClienteIA.php`): fala com o provedor
  (OpenRouter por padrão) e devolve uma **proposta** — intenção, texto e dados
  soltos. Qualquer falha, intenção fora do catálogo ou JSON inválido vira `null`.
- **`CotaIA`** (`app/Services/WhatsApp/CotaIA.php`): lê o limite da assinatura,
  reserva a chamada de forma atômica (`Cache::lock` por clínica) e registra o
  consumo em `usage_metrics` (banco central).
- **`AtendimentoIA`** (`app/Services/WhatsApp/AtendimentoIA.php`): orquestra o
  turno. A IA **propõe**, o Laravel **decide**. Nada que escreve na agenda nasce
  da proposta do modelo; a ação continua passando pelo `FluxoAtendimento` e
  pelos serviços de agenda.
- **Endpoints** (grupo `api.whatsapp.*`, autenticados por token de integração):
  - `POST /api/whatsapp/ia` — turno com IA. Exige `plan:whatsapp` **e**
    `plan:ai_agent`, e a ability `conversations.ai`.
  - `POST /api/whatsapp/conversa/handoff` — pausa o bot, assume como humano.
    Ability `conversations.handoff`.
  - `POST /api/whatsapp/conversa/retomar` — devolve a conversa ao bot.
    Ability `conversations.handoff`.
- **Estados do bot** (`whatsapp_conversations.bot_status`): `ativo`, `pausado`,
  `aguardando_atendente`, `humano`. `etapa` continua sendo a etapa da conversa;
  `bot_status` é QUEM responde.

## Regras de negócio da IA

O `ClienteIA` só pode devolver uma destas intenções: `saudacao`, `agendar`,
`horarios`, `profissionais`, `procedimentos`, `falar_humano`, `duvida`,
`desconhecido`. O prompt de sistema proíbe diagnóstico, prescrição, invenção de
preços/horários e confirmação de agendamento.

O `AtendimentoIA` decide o que fazer:

| Intenção                     | Ação                                                        |
| ---------------------------- | ----------------------------------------------------------- |
| `saudacao`, `duvida`         | responde com o texto da IA                                   |
| `agendar`                    | delega ao fluxo determinístico (nunca agenda direto)         |
| `profissionais`/`procedimentos`/`horarios` | lista dados reais do banco             |
| `falar_humano`               | `bot_status = aguardando_atendente` + auditoria              |
| `desconhecido`               | segue no fluxo determinístico                                |

Consentimento (LGPD), cadastro e seleções numéricas **nunca** passam pela IA:
vão direto para o `FluxoAtendimento`.

Fallback: se a IA estiver desligada, sem chave, sem cota, com o limite por
conversa estourado ou devolver proposta inválida, o turno cai no fluxo
determinístico. Um atendimento com IA não pode quebrar quando a IA falta.

## Configuração

### Variáveis de ambiente (`.env`)

```
ATENDIMENTO_IA_ENABLED=false
AI_PROVIDER=openrouter
AI_BASE_URL=https://openrouter.ai/api/v1
AI_MODEL=meta-llama/llama-3.3-70b-instruct
ATENDIMENTO_IA_TIMEOUT=30
ATENDIMENTO_IA_MAX_TOKENS=500
ATENDIMENTO_IA_TEMPERATURE=0.3
ATENDIMENTO_IA_MAX_POR_CONVERSA=30
AI_API_KEY=
```

`AI_API_KEY` é a chave do provedor. **Ela não existe hoje no `.env` do Laravel** —
a credencial do OpenRouter está apenas no n8n. Enquanto `AI_API_KEY` estiver
vazia ou `ATENDIMENTO_IA_ENABLED=false`, o `ClienteIA::configurado()` devolve
`false` e todo o atendimento segue pelo fluxo determinístico, sem chamar a rede.

### Plano

A rota `POST /api/whatsapp/ia` exige a feature `ai_agent` no plano da
assinatura ativa. O limite mensal vem de
`ai_agent.limits.ai_messages_per_month` (`null` = sem limite). A feature
`ai_agent` deve ser cadastrada no plano do painel.

### Banco (migration)

A migration aditiva
`database/migrations/tenant/2026_10_10_000001_add_ia_to_whatsapp_tables.php`
acrescenta as colunas de estado do bot em `whatsapp_conversations` e os metadados
`ai_intent` / `ai_handled` / `ai_error` em `whatsapp_messages`. Ela é **aditiva e
nulável**: não reescreve linhas existentes nem exige backfill.

```bash
php artisan migrate --path=database/migrations/tenant --force
```

Em produção, o banco de cada clínica migra pelo mesmo mecanismo das demais
migrations de tenant deste projeto, com backup validado antes (§ Rollback):

```bash
php artisan tenants:migrate --clinic=<ID_DA_CLINICA> --force
```

## Operação

- **Pausar o bot para atendimento humano**: `POST
  /api/whatsapp/conversa/handoff` com `{"remote_jid": "..."}`. Enquanto o
  `bot_status` for `humano`, o bot não responde.
- **Retomar**: `POST /api/whatsapp/conversa/retomar`. Volta a `ativo` e limpa
  `humano_iniciado_em` / `pausado_ate`.
- **Pausa por tempo**: grave `bot_status = pausado` e `pausado_ate` no futuro.
  Passado o prazo, o bot volta sozinho.
- **Cota**: consultas de consumo ficam em `usage_metrics` (`ai_requests`,
  `ai_input_tokens`, `ai_output_tokens`), por clínica e por mês.
- **Histórico**: a última mensagem recebida de cada conversa guarda
  `ai_intent` / `ai_handled` / `ai_error` para auditoria.

### Verificação sanitizada do bot pausado

```bash
docker exec n8n n8n list:workflow
# O workflow que atende a clínica na produção (LJAtendimento01) deve ter active = false.
```

## Segurança e LGPD

- Nenhuma chave de provedor é gravada em código, banco, frontend ou logs.
- O prompt recebe apenas a etapa da conversa e o nome já coletado — não envia
  histórico clínico nem prontuário.
- Logs registram no máximo o status HTTP e a classe da exceção, nunca o corpo
  da mensagem do paciente.
- O `clinic_id` nunca vem da IA nem do corpo: a clínica é sempre a do token.

## Rollback

1. Desligar a IA (sem apagar dados): `ATENDIMENTO_IA_ENABLED=false`. O
   atendimento volta ao fluxo determinístico imediatamente.
2. Remover o acesso: retirar `conversations.ai` e `conversations.handoff` dos
   tokens e/ou remover as rotas `ia` / `handoff` / `retomar`.
3. Reverter o schema (com backup validado antes): no desenvolvimento,
   ```bash
   php artisan migrate:rollback --path=database/migrations/tenant --step=1 --force
   ```
   Em produção, a reversão segue o mesmo mecanismo das demais migrations de
   tenant, executada por clínica. O `down()` remove as colunas de
   `whatsapp_conversations` e `whatsapp_messages`.

## Testes

```bash
php artisan test --filter=WhatsAppAtendimentoIA
```

Os testes usam `Http::preventStrayRequests()` e `Http::fake()`: nenhum provedor
real é alcançado e nenhuma mensagem real é enviada. Cobrem IA desligada,
consentimento, seleção numérica, cota esgotada e corrida de cota, timeout e falha
do provedor, resposta inválida (JSON malformado e intenção fora do catálogo),
assinatura inativa, handoff/retomada, falta de ability, plano sem `ai_agent`,
ausência de efeitos colaterais na agenda e isolamento entre clínicas
(`WhatsAppAtendimentoIAIsolamentoTest`).
