# Integração WhatsApp Multi-Clínica + Provisionamento — Homologação

Branch: `integration/whatsapp-multiclinica`
Base: `1d5dcae` (`feat/provisionamento-whatsapp`), que já contém `e8efd6d` (`fix/grupos-multiclinica`).
Commit final: ver `git log -1` na branch de integração (registrado no relatório de entrega).

Este documento prepara a validação em ambiente local/isolado. **Não foi feito deploy, merge em release, migration em produção, envio de mensagem real nem criação de infraestrutura de homologação no VPS.**

## 1. O que está sendo homologado

- Bloqueio de grupos `@g.us` no webhook, na API interna, no fluxo, na IA e no envio.
- Preservação de contatos `@lid` (o `remoteJidAlt` só substitui um JID vazio ou `@lid`).
- Instância Evolution exclusiva por clínica, token cifrado e webhook com `apikey` individual + `X-Evolution-Secret`.
- Oito eventos no webhook, incluindo `CONNECTION_UPDATE`.
- Timeout do n8n com piso de 45 s.
- Roteamento n8n por clínica via `clinics.n8n_webhook_url` e template validado.
- Ativação só após webhook configurado, conexão aberta e roteamento existente.
- Bloqueio de clínicas desconectadas, sem webhook ou sem roteamento (para clínicas provisionadas pelo fluxo novo).
- Monitoramento a cada 15 min e reprovisionamento pelo painel.
- Isolamento entre tenants: clínica resolvida pelo nome da instância, nunca por `clinic_id` do payload.

## 2. Ambiente isolado

Use um worktree separado, nunca `/var/www/ljsmartclinic`:

```bash
git worktree add -b integration/whatsapp-multiclinica /caminho/isolado 1d5dcae
cd /caminho/isolado
composer install --no-interaction --prefer-dist
cp .env.example .env
php artisan key:generate
```

Banco de teste: SQLite `:memory:` já é usado pela suíte (`phpunit.xml`). Para homologação manual, use um **banco novo e descartável** (SQLite local ou MariaDB com prefixo próprio). Nunca aponte `DB_*` ou `TENANT_DB_*` para produção.

Se a suíte reclamar de `Vite manifest not found`, rode `npm install && npm run build` no worktree, ou copie apenas `public/build` de um build já existente para o worktree de teste.

## 3. Variáveis de ambiente

Valores abaixo são ilustrativos. **Não copie credenciais reais para `.env.example`, `.env.production.example` ou para este documento.**

```dotenv
EVOLUTION_API_URL=
EVOLUTION_API_TIMEOUT=30
EVOLUTION_WEBHOOK_SECRET=
EVOLUTION_WEBHOOK_URL=

N8N_WEBHOOK_URL=
N8N_WEBHOOK_TOKEN=
N8N_WEBHOOK_TIMEOUT=45
N8N_CLINIC_WEBHOOK_TEMPLATE=
```

Regras:

| Variável | Uso | Validação / comportamento |
|---|---|---|
| `EVOLUTION_API_URL` | Base da API Evolution | Vazia = provisionamento falha com 503. |
| `EVOLUTION_WEBHOOK_SECRET` | Header `X-Evolution-Secret` enviado na configuração do webhook e exigido na rota pública | Vazio = todo POST ao webhook responde 403 (fail-closed). |
| `EVOLUTION_WEBHOOK_URL` | URL pública que a Evolution chama | Vazia = fallback para `route('api.evolution.webhook.handle')`, que pode estar errado atrás de proxy. |
| `N8N_CLINIC_WEBHOOK_TEMPLATE` | Destino de clínicas novas; aceita `{instance}`, `{clinic_id}` e `{slug}` | Placeholder desconhecido ou URL que não seja `http(s)` válida aborta o provisionamento. Vazio = clínica fica pendente. |
| `N8N_WEBHOOK_TIMEOUT` | Timeout da entrega ao n8n | O código aplica `max(45, valor)`, então um `.env` antigo com `15` não reduz o timeout. |
| `N8N_WEBHOOK_URL` | Fallback apenas para clínicas anteriores ao provisionamento | Clínica provisionada com roteamento vazio **não** cai no global. |
| `N8N_WEBHOOK_TOKEN` | Header opcional `X-N8n-Token` | Não é logado; valores de config são removidos de mensagens de erro. |

O token por clínica fica em `clinics.evolution_token` (cast `encrypted`), nunca no `.env`. A chave administrativa só cria/lista/configura webhook; estado, QR e envio usam o token da instância.

## 4. Migration

Uma migration nova:

- `database/migrations/central/2026_11_18_000004_add_whatsapp_provisioning_to_clinics_table.php`

Adiciona seis colunas **nullable, sem default e sem backfill** em `clinics`:

`whatsapp_status`, `whatsapp_status_at`, `whatsapp_last_error`, `whatsapp_provisioned_at`, `whatsapp_webhook_configured_at`, `whatsapp_connected_at`.

- Não cria índice nem constraint.
- Não altera colunas antigas nem dados de clínicas existentes.
- `up()` e `down()` são idempotentes coluna a coluna: uma aplicação interrompida no meio do ALTER pode ser reaplicada ou revertida sem exigir que as seis colunas existam.
- Compatível com Laravel 13 e MariaDB/MySQL (`string(30)`, `timestamp`, `text`, `after()`).

No ambiente isolado:

```bash
php artisan migrate --force
php artisan tenants:migrate --all
php artisan migrate:status
```

**Não executar em produção sem autorização específica.**

## 5. Testes automatizados (fakes, sem mensagens reais)

Suíte focada executada na branch de integração:

```bash
php artisan test --compact \
  tests/Feature/WhatsAppGruposMulticlinicaTest.php \
  tests/Feature/WhatsAppFluxoTest.php \
  tests/Feature/WhatsAppApiTest.php \
  tests/Feature/WhatsAppApiIsolamentoTest.php \
  tests/Feature/WhatsAppAtendimentoIATest.php \
  tests/Feature/WhatsAppAtendimentoIAIsolamentoTest.php \
  tests/Feature/WhatsAppOutboxTest.php \
  tests/Feature/WhatsAppMenusInterativosTest.php \
  tests/Feature/EvolutionWebhookTest.php \
  tests/Feature/EvolutionQrLegadoTest.php \
  tests/Feature/ProvisionamentoWhatsAppClinicaTest.php \
  tests/Feature/ProvisionamentoClinicaTest.php \
  tests/Feature/MigrationWhatsappProvisioningTest.php \
  tests/Feature/EnvProductionExampleTest.php \
  tests/Feature/Platform/PlatformClinicWhatsAppTest.php \
  tests/Feature/Platform/PlatformProspeccaoWhatsAppTest.php \
  tests/Unit/RedatorDeSegredosTest.php
```

Resultado: **266 passed (1172 assertions)**, 2 testes `risky` pré-existentes em `EnvProductionExampleTest` (ausência de assertiva quando não há violação no template).

Suíte completa (`php artisan test --compact`): **890 passed (3708 assertions)**, 2 failed, 3 risky, 4 skipped. As duas falhas são pré-existentes e não relacionadas a esta integração:

- `DemoSeederTest::test_semeia_a_clinica_completa` — esperava 4 profissionais, encontrou 5.
- `Platform\PlatformProspeccaoTest::download do pdf` — esperava 200, recebeu 404.

Os 3 testes `risky` também são pré-existentes: `DocumentPdfTest::test_atestado_lista_pacientes_legados_sem_clinica_id` (buffer de saída não fechado) e os dois de `EnvProductionExampleTest` já citados.

Cobertura das 12 categorias pedidas:

| # | Categoria | Onde validar |
|---|---|---|
| 1 | Grupos | `WhatsAppGruposMulticlinicaTest`, `WhatsAppFluxoTest` |
| 2 | Mensagens privadas | `WhatsAppFluxoTest`, `EvolutionWebhookTest` |
| 3 | `@lid` | `WhatsAppGruposMulticlinicaTest`, `EvolutionWebhookTest` |
| 4 | Isolamento entre clínicas | `WhatsAppGruposMulticlinicaTest`, `WhatsAppApiIsolamentoTest`, `WhatsAppAtendimentoIAIsolamentoTest` |
| 5 | Provisionamento | `ProvisionamentoWhatsAppClinicaTest` |
| 6 | Falha e recuperação | `ProvisionamentoWhatsAppClinicaTest`, `WhatsAppOutboxTest` |
| 7 | Webhook | `EvolutionWebhookTest`, `EvolutionQrLegadoTest` |
| 8 | Outbox | `WhatsAppOutboxTest`, `WhatsAppMenusInterativosTest` |
| 9 | Roteamento n8n | `ProvisionamentoWhatsAppClinicaTest`, `EvolutionWebhookTest` |
| 10 | Painel | `PlatformClinicWhatsAppTest`, `PlatformProspeccaoWhatsAppTest` |
| 11 | Suíte completa | `php artisan test --compact` — 890 passed, 2 failed (pré-existentes), 3 risky, 4 skipped |
| 12 | Pint | `vendor/bin/pint --dirty --format agent` — sem pendências após a integração |

## 6. Validação manual que depende de Evolution real / QR Code / n8n

Estes itens **não** são cobertos pelos fakes e precisam de ambiente isolado com credenciais descartáveis:

1. Criar instância real pela clínica de teste e confirmar que a Evolution devolve o token uma única vez.
2. Configurar o webhook e conferir na Evolution: URL pública, headers `apikey` e `X-Evolution-Secret`, os oito eventos.
3. Gerar QR e escanear com um aparelho de teste; confirmar `connectionState = open` e badge "Conectada" no painel.
4. Enviar mensagem privada de teste e conferir que chega ao n8n da clínica correta, com `clinic.id` e `evolution_instance` corretos no payload.
5. Enviar mensagem de grupo de teste e confirmar que o webhook responde `ignored` e que nenhuma mensagem chega à IA/outbox/n8n.
6. Enviar mensagem de contato `@lid` e confirmar que é tratada como privada.
7. Desconectar o aparelho e confirmar bloqueio automático; reconectar e confirmar restauração.
8. Apagar o destino n8n da clínica provisionada e confirmar que o atendimento fica bloqueado, sem cair no webhook global.
9. Rodar `php artisan whatsapp:clinicas:monitorar --clinic=<id>` com a Evolution real e conferir que cada clínica usa seu próprio token.
10. Reprovisionar pelo painel e confirmar que a instância não é renomeada, recriada nem adotada de outra clínica.
11. Simular erro 4xx/5xx do n8n e conferir `forward_error`, `whatsapp_last_error` e ausência de token nos logs.

Use números e instâncias descartáveis. Não envie mensagem para paciente real.

## 7. Plano de deploy (somente após autorização)

1. Congelar a release atual e tirar backup de código, `.env`, banco central e bancos tenant.
2. Fazer merge/revisão da branch de integração na branch de release, sem publicar diretamente daqui.
3. Publicar código com as variáveis da seção 3 preenchidas no `.env` de destino (segredos fora do repositório).
4. Rodar `php artisan migrate --force` **apenas** para a migration `2026_11_18_000004`. Não há backfill.
5. Limpar/cachear configuração: `php artisan config:clear && php artisan config:cache`, além de `php artisan view:clear` se a blade mudou.
6. Validar `php artisan migrate:status`, `php artisan about` e `php artisan whatsapp:clinicas:monitorar --json`.
7. Provisionar uma clínica-piloto pelo painel, escanear QR e executar os itens 1–11 da seção 6.
8. Só então liberar o provisionamento para as demais clínicas, uma por vez.

## 8. Plano de rollback

1. Voltar o código para a release anterior. As seis colunas novas são nullable e extras; o código antigo ignora essas colunas, então este passo sozinho já restabelece o comportamento anterior.
2. Se for necessário remover o schema, rodar o rollback **somente desta migration**, com o caminho explícito, e nunca um rollback genérico de lote:

```bash
php artisan migrate:rollback --path=database/migrations/central/2026_11_18_000004_add_whatsapp_provisioning_to_clinics_table.php --force
```

3. Restaurar os valores antigos de `.env` apenas se a versão anterior não entender as novas variáveis.
4. O rollback descarta o estado de provisionamento (status, datas e último erro). Depois de um rollback de DB, as clínicas precisam ser reprovisionadas.
5. Se o rollback de DB falhar por colunas parciais, repetir a migration `down()` é seguro: ele remove somente as colunas existentes.

## 9. Pendências e riscos

- Clínicas anteriores a este fluxo (`whatsapp_provisioned_at` nulo) mantêm o comportamento legado: não são bloqueadas pela nova checagem de prontidão e o dispatcher ainda pode usar o webhook global do n8n. Antes de considerar o isolamento completo, elas precisam ser reprovisionadas ou passar por uma decisão explícita de backfill/bloqueio.
- A adoção de instância existente agora recusa nomes já vinculados a outra clínica no cadastro central. Ainda assim, uma instância manual criada na Evolution com nome igual ao sugerido e sem registro em nenhuma clínica pode ser adotada; conferir na Evolution antes do provisionamento.
- A redação de segredos cobre chaves de config e padrões comuns (`apikey`, `token`, `secret`) em corpos de erro. Corpos com formatos diferentes podem exigir ajuste; revisar logs após o piloto.
- A suíte completa e o Pint foram executados manualmente; não existe workflow de CI neste repositório.
- Dois testes `risky` pré-existentes em `EnvProductionExampleTest` não foram reescritos.
- Nenhuma validação com Evolution real, QR Code ou n8n real foi feita nesta integração.
