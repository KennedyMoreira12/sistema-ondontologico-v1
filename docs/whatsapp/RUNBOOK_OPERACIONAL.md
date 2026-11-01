# Runbook operacional — WhatsApp

Procedimentos para ligar, acompanhar e desligar o atendimento por WhatsApp de
uma clínica. Tudo aqui pressupõe **autorização explícita** para a mudança: este
documento descreve o passo, não decide por ninguém.

## 1. Pré-condições antes de qualquer ativação

| Item | Como conferir | Observação |
| --- | --- | --- |
| Código no servidor | `git log -1 --oneline` | precisa conter a Fase 1 |
| Migration central aplicada | `php artisan migrate --pretend` | deve listar `clinic_feature_flags` só se pendente |
| Migration de tenant aplicada | `php artisan tenants:migrate --clinic=ID --pretend` | deve listar `whatsapp_outbox` só se pendente |
| Flags desligadas | `SELECT * FROM clinic_feature_flags WHERE clinic_id = ID;` | vazio significa "tudo desligado" |
| Flag de menus (Fase 3) | `ClinicFeatures::all(ID)` | só ligar depois de o n8n usar `interativo`/a rota nova |
| Integração da clínica | `GET api/whatsapp/info` com token válido | precisa ter instância e token da Evolution |
| Fila | `php artisan config:show queue.default` | `sync` funciona; `database`/`redis` exige worker |
| Cron do scheduler | `crontab -l` | precisa ter `schedule:run` a cada minuto |
| Backup recente | rotação automática da clínica | antes de migration |

## 2. Aplicar as migrations (requer autorização)

```bash
# 1) banco central
php artisan migrate --force

# 2) um banco de clínica por vez, começando pela clínica piloto
php artisan tenants:migrate --clinic=ID
```

Ordem importa: as flags vivem no central e a outbox no banco da clínica. Rodar
`tenants:migrate` sem o central deixa a clínica com a tabela e sem como ligar a
flag; rodar o central sem o tenant deixa a flag ligável e a outbox inexistente.

Confira depois:

```bash
php artisan tenants:auditar-tabelas --clinica=ID
php artisan whatsapp:health --clinic=ID
```

`tenants:auditar-tabelas` acusa tabela sem classificação — `whatsapp_outbox` e as
quatro tabelas da Fase 2 (`whatsapp_rules`, `whatsapp_quick_replies`,
`whatsapp_notes`, `whatsapp_alerts`) já estão classificadas em
`InventarioTabelas::TABELAS_COMUNS`.

> A migration da Fase 2 não muda o comportamento sozinha: sem regra cadastrada e
> sem expediente configurado, o bot continua 24/7 como antes. Só cadastre regras
> ou expediente na clínica piloto **depois** de autorizado.

A Fase 3 **não tem migration nova**: `kind`, `payload` e `body` já vieram na
outbox da Fase 1. Se essas colunas não existirem, a Fase 1 não foi aplicada.

## 3. Ligar a flag de uma clínica

Não há tela ainda. A escrita é por `ClinicFeatures`, nunca por `UPDATE` direto:
o serviço valida o nome da flag e invalida o cache da clínica.

```bash
php artisan tinker --execute 'app(\App\Services\WhatsApp\ClinicFeatures::class)->set(\App\Models\Central\Clinic::find(5), "whatsapp_outbox", true);'
```

Conferir:

```bash
php artisan tinker --execute 'app(\App\Services\WhatsApp\ClinicFeatures::class)->all(5);'
```

Desligar é o mesmo comando com `false`. O cache dura até 5 minutos, mas `set()`
invalida na hora.

> A flag `whatsapp_outbox` só deve ser ligada **depois** de a migration do
> tenant estar aplicada. Ligada sem a tabela, todo envio vira erro 500.

### Menus interativos (`interactive_menus`)

A flag `interactive_menus` não muda o texto sozinha: ela libera a chave
`interativo` em `/fluxo` e a rota `mensagem-interativa`. O workflow do n8n
precisa passar a usar uma das duas; **sem isso, o atendimento continua
exatamente como antes**.

```bash
php artisan tinker --execute 'app(\App\Services\WhatsApp\ClinicFeatures::class)->set(5, "interactive_menus", true);'
```

Ligue primeiro em clínica piloto. Se o n8n ainda não usa o contrato interativo,
ligar a flag não envia botões e não quebra nada — só expõe o campo novo.
A `Evolution 2.3.7` já aceita `sendButtons`/`sendList`; a renderização depende do
aparelho do paciente, e por isso o fallback textual continua sendo enviado
quando a Evolution recusa o formato.

## 4. Fila

### Modo `sync` (sem worker)

O job roda dentro do request. A resposta é `sent` ou a falha classificada, e a
linha da outbox existe com tentativas e DLQ. Nada a instalar — mas o retry só
acontece quando a recuperação roda.

### Modo `database` / `redis` (com worker)

```ini
; /etc/supervisor/conf.d/lj-whatsapp-worker.conf
[program:lj-whatsapp-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ljsmartclinic/artisan queue:work --queue=default --sleep=3 --tries=1 --max-time=3600
directory=/var/www/ljsmartclinic
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/ljsmartclinic/storage/logs/worker-whatsapp.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status lj-whatsapp-worker:*
```

Notas:

- `--tries=1` é intencional: o retry é da outbox, com classificação própria.
- `--max-time=3600` recicla o worker para não segurar memória.
- `stopwaitsecs` alto evita matar um envio em andamento.
- O worker não tem tenant: cada job reabre a clínica dona via `TenantRunner`.
- Ao mudar código, `php artisan queue:restart` após o deploy. Isso **derruba**
  jobs em execução no fim do ciclo; faça fora de pico.

> **Atenção:** com fila `database` e **sem** worker, a recuperação agendada
> reenfileira as mesmas linhas a cada minuto e a tabela `jobs` cresce sem que
> nada saia. Não deixe o scheduler rodando nesse estado.

## 5. Scheduler

```cron
* * * * * cd /var/www/ljsmartclinic && php artisan schedule:run >> /dev/null 2>&1
```

O que está agendado hoje: `whatsapp:outbox:recover` a cada minuto, com
`withoutOverlapping()`. A trava usa o cache; se o cache estiver em arquivo, a
proteção vale por servidor.

Conferir:

```bash
php artisan schedule:list
```

## 6. Comandos de operação

| Comando | Para que serve |
| --- | --- |
| `php artisan whatsapp:health` | tabela por clínica: integração, conexão, pendentes, DLQ, órfãs |
| `php artisan whatsapp:health --clinic=ID --check-evolution` | inclui o estado da conexão na Evolution |
| `php artisan whatsapp:health --json` | saída para script/monitoramento |
| `php artisan whatsapp:outbox:recover` | reenfileira pendentes válidos e linhas órfãs |
| `php artisan whatsapp:outbox:recover --clinic=ID --limit=50` | limitado a uma clínica |
| `php artisan whatsapp:outbox:replay {clinic} {outbox}` | tira uma linha da DLQ e tenta de novo |
| `php artisan whatsapp:outbox:replay {clinic} {outbox} --attempts=0` | mesmo, zerando a contagem |

## 7. Diagnóstico rápido

### "O paciente diz que não recebeu"

1. `php artisan whatsapp:health --clinic=ID` — há pendente? DLQ? órfã?
2. Se houver DLQ, veja `last_error_code`:
   - `sem_integracao`: falta instância/token no painel. Corrija e faça replay.
   - `evolution_recusou`: 4xx ou `error` no corpo. Leia `last_error`.
   - `incerto`: timeout. **Pode ter saído.** Confirme no aparelho antes de
     replay; reenviar duplica.
   - `orfao`: o worker morreu; a recuperação já devolveu para a fila.
3. Confira `whatsapp_messages.delivery_status` e `delivery_status` da mensagem.
4. `correlation_id` liga a linha ao log da requisição e do webhook.

### "O bot não responde"

1. `whatsapp:health --check-evolution` — conexão `open`?
2. A integração tem instância e token? (`integration_ready`)
3. Há mensagem na DLQ? (resposta não saiu)
4. A clínica está pausada ou fora do expediente? (Fase 2)
5. O webhook está configurado na Evolution apontando para o domínio correto?
6. `tail -f storage/logs/laravel.log` filtrando `correlation_id`.

### "O paciente recebeu texto em vez de botões"

1. A flag `interactive_menus` está ligada? Desligada, a rota devolve 409 e o n8n
   deve enviar o `fallback_texto` pela rota de texto.
2. O n8n está chamando `mensagem-interativa`/lendo `interativo`? Se ainda usa só
   `resposta`, o comportamento antigo é o esperado.
3. Se a Evolution recusou o formato, a outbox mostra
   `last_error_code = interativo_recusado_fallback`: o texto já foi enviado na
   mesma linha. Não é falha de entrega.
4. O aparelho do paciente pode não renderizar botões/listas; nesse caso o texto
   é o caminho normal, não um incidente.

### "A fila está parada"

```bash
php artisan queue:monitor default:100
sudo supervisorctl status
php artisan queue:failed
```

Job na tabela `failed_jobs` significa que o worker morreu antes de a outbox
classificar. O log terá `Job da outbox do WhatsApp falhou antes de classificar`.

## 8. Rotina de acompanhamento

Diária:

- `whatsapp:health` em todas as clínicas.
- DLQ diferente de zero: investigar no mesmo dia.

Semanal:

- Crescimento de `whatsapp_outbox` e `whatsapp_messages`.
- Tempo entre `created_at` e `sent_at` das linhas enviadas.
- Falhas por código de erro, para separar problema de configuração de problema
  da Evolution.

## 9. Desligar

Para parar o atendimento de uma clínica sem tocar em código:

```bash
php artisan tinker --execute 'app(\App\Services\WhatsApp\ClinicFeatures::class)->set(5, "whatsapp_outbox", false);'
```

A flag desligada volta ao envio direto síncrono. Linhas `pending` que sobrarem
na outbox **não** saem sozinhas: decida entre drenar (religar a flag e rodar a
recuperação) ou marcar como descartadas. Ver `ROLLBACK.md`.

Para parar só os menus, sem mexer no envio:

```bash
php artisan tinker --execute 'app(\App\Services\WhatsApp\ClinicFeatures::class)->set(5, "interactive_menus", false);'
```

O envio de texto continua; a rota interativa passa a responder 409 com o
`fallback_texto`, e quem chamou decide enviá-lo pela rota normal.
