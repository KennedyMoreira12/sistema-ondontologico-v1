# Rollback — WhatsApp

Como voltar atrás sem perder dado de clínica. Cada passo indica o que ele
desfaz e o que **não** desfaz.

## Princípio

Rollback é feito em camadas, da menos invasiva para a mais invasiva:

1. **desligar a flag** — devolve o comportamento antigo sem tocar em dado;
2. **parar o worker/scheduler** — interrompe processamento novo;
3. **reverter o código** — `git revert` do commit da fase;
4. **reverter a migration** — só se for realmente necessário, e com backup.

Nunca apagar tabela, banco ou backup para "limpar". A migration `down()` existe
para desenvolvimento e para um rollback autorizado, não para rotina.

## 1. Desligar um recurso por clínica (imediato)

```bash
php artisan tinker --execute 'app(\App\Services\WhatsApp\ClinicFeatures::class)->set(5, "whatsapp_outbox", false);'
```

Efeito: o envio volta ao caminho direto e síncrono. Nada é apagado. Linhas
`pending` na outbox deixam de ser despachadas por novos requests, mas a
recuperação agendada continua reenfileirando — veja o passo 2.

Conferir:

```bash
php artisan tinker --execute 'app(\App\Services\WhatsApp\ClinicFeatures::class)->all(5);'
```

O cache é invalidado por `set()`, então o efeito é imediato.

Para desligar **apenas os menus** da Fase 3:

```bash
php artisan tinker --execute 'app(\App\Services\WhatsApp\ClinicFeatures::class)->set(5, "interactive_menus", false);'
```

Efeito: `/fluxo` deixa de expor `interativo` e a rota `mensagem-interativa`
responde 409 com o `fallback_texto`. O envio de texto e a outbox continuam
funcionando; nenhuma linha é apagada. Menus já enfileirados ainda tentam sair —
se a recusa for explícita, a própria linha vira texto.

## 2. Parar o processamento (minutos)

```bash
# impede nova recuperação
php artisan schedule:list          # confirmar o que está agendado
# remover/Comentar a linha do cron ou desabilitar o agendamento em routes/console.php
```

```bash
sudo supervisorctl stop lj-whatsapp-worker:*
```

Sem worker e sem recuperação, nenhuma linha nova é enviada. O que já está em
`processing` fica parado até ser recuperado — é o comportamento desejado num
incidente.

## 3. Reverter o código

```bash
git revert --no-edit <commit-da-fase>
composer install --no-dev --optimize-autoloader   # se houve mudança de dependência
php artisan optimize:clear
php artisan config:cache
sudo supervisorctl restart lj-whatsapp-worker:*   # se o worker estiver ativo
```

Não usar `git reset --hard` nem `git checkout -- .`: o repositório tem trabalho
não commitado de outras frentes que precisa sobreviver.

## 4. Reverter as migrations (último recurso)

Faça backup **antes**. Em produção, peça autorização específica.

```bash
# central
php artisan migrate:rollback --step=1 --force

# uma clínica
php artisan tenants:migrate --clinic=ID --rollback   # conforme o comando disponível
```

`down()` da migration de tenant da Fase 1:

- remove os índices e as colunas `delivery_status`, `delivered_at`, `read_at`,
  `correlation_id` de `whatsapp_messages`;
- remove a tabela `whatsapp_outbox`.

`down()` da migration de tenant da Fase 2:

- remove `whatsapp_alerts`, `whatsapp_notes`, `whatsapp_quick_replies` e
  `whatsapp_rules`.

A Fase 3 **não tem migration**: reverter o código remove os menus, mas as
colunas `kind`, `payload` e `body` da outbox continuam pertencendo à migration da
Fase 1. Reverter a Fase 1 remove essas colunas e a tabela inteira.

**O que se perde:** o rastro de envio (status, tentativas, erros), os status de
entrega já recebidos, as regras cadastradas, as respostas rápidas, as notas
internas e a fila de alertas. O histórico de mensagens em si permanece — só as
colunas novas somem.

Por isso o rollback de schema é o último recurso: com a flag desligada e o
código revertido, a tabela pode ficar no banco sem causar dano. Deixá-la é
geralmente mais seguro do que removê-la.

## 5. Filas

| Situação | Ação |
| --- | --- |
| Fila trocada de `sync` para `database`/`redis` e o worker não subiu | desligar o scheduler, voltar `QUEUE_CONNECTION` e `config:cache` |
| Jobs órfãos na tabela `jobs` | `php artisan queue:clear` **apenas** se autorizado; senão, deixar drenar |
| `failed_jobs` acumulando | `php artisan queue:failed` para inspecionar; `queue:retry` só depois de corrigir a causa |
| Worker com memória alta | `queue:restart` (derruba jobs em execução ao fim do ciclo) |

`queue:clear` apaga jobs pendentes de **todas** as filas da conexão. Não use sem
saber o que está lá.

## 6. O que fazer com a outbox ao desligar

Três opções, em ordem de preferência:

1. **Drenar**: religar a flag por alguns minutos e rodar
   `whatsapp:outbox:recover --clinic=ID`, depois desligar.
2. **Congelar**: deixar as linhas `pending` como estão. Elas não saem, e o
   histórico mostra o que ficou pendente.
3. **Descartar**: marcar como `dead` com motivo explícito, para o operador ver.
   Nunca `DELETE`.

```bash
php artisan tinker --execute 'App\Models\WhatsAppOutboxMessage::query()->where("status", "pending")->update(["status" => "dead", "last_error" => "Descartado no rollback: <motivo>", "last_error_code" => "rollback"]);'
```

## 7. Ordem recomendada num incidente

```
1. Desligar a flag da clínica afetada
2. Parar worker + recuperação dessa clínica
3. whatsapp:health para fotografar o estado
4. Investigar com correlation_id
5. Corrigir ou reverter código
6. Religar em uma clínica piloto
7. Acompanhar 24h antes de ampliar
```

## 8. Checklist de rollback testado

| Passo | Como verificar | Resultado esperado |
| --- | --- | --- |
| Flag desligada | `whatsapp:health --json` → `flags.whatsapp_outbox = false` | envio direto volta |
| Menus desligados | `ClinicFeatures::all(ID)` → `interactive_menus = false` | `/fluxo` sem `interativo`, rota devolve 409 |
| Envio direto funciona | POST de teste com token de integração em ambiente de teste | 200 `sent`, linha em `whatsapp_messages` |
| Worker parado | `supervisorctl status` | `STOPPED` |
| Recuperação desligada | `schedule:list` | comando ausente |
| Código revertido | `git log -1`, `php artisan about` | sem erro de classe ausente |
| Migration revertida (se aplicável) | `Schema::hasTable('whatsapp_outbox')` | `false` |
| Dados preservados | contagem de `whatsapp_messages` antes/depois | igual |

Testar o rollback em ambiente de teste, não em produção. O teste real de
rollback em produção exige janela e autorização.
