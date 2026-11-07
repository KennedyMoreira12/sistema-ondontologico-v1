# FASE B â€” SEGURANÃ‡A MULTI-TENANT EM DESENVOLVIMENTO

**Data de execuÃ§Ã£o:** 21/10/2026  
**Projeto:** LJ SmartClinic  
**Ambiente:** Desenvolvimento local (somente) â€” **nenhuma alteraÃ§Ã£o em produÃ§Ã£o**  
**Status:** Em andamento / RelatÃ³rio de auditoria e achados

---

## 1. SumÃ¡rio Executivo

A auditoria confirmou que a arquitetura multi-tenant jÃ¡ possui as proteÃ§Ãµes corretas em grande parte, mas foram identificados pontos que precisam de correÃ§Ã£o antes de expandir para fisioterapia. Os achados mais relevantes sÃ£o:

| Severidade | Achado | Local |
|---|---|---|
| **ALTO** | Documentos clÃ­nicos armazenados em disco pÃºblico sem prefixo por clÃ­nica | `app/Http/Controllers/PacienteController.php:196` |
| **ALTO** | Lock de agendamento sem identificaÃ§Ã£o da clÃ­nica (compartilhado entre tenants) | `app/Services/Agenda/AgendamentoService.php:70-72` |
| **ALTO** | Middleware Ã³rfÃ£o `EnsureTenantAccess` redireciona para `billing.index` que nÃ£o existe | `app/Http/Middleware/EnsureTenantAccess.php:53-57` |
| **MÃ‰DIO** | Chaves de cache sem isolamento por clÃ­nica (driver database com prefixo global) | `config/cache.php`, chaves de lock |
| **MÃ‰DIO** | Jobs/fila vazios hoje, mas sem padrÃ£o de "reativar" contexto de tenant | `app/Jobs/` (inexistente), `config/queue.php` |
| **MÃ‰DIO** | Auditoria registra tipo/ID mas nÃ£o tem trilha completa para alteraÃ§Ãµes clÃ­nicas | `app/Services/AuditLogger.php` (usado em alertas/documentos) |
| **MÃ‰DIO** | Trial/assinatura: `EnsureTenantAccess` e `ResolveTenant` verificam acesso, mas sem expiraÃ§Ã£o agendada | `app/Http/Middleware/ResolveTenant.php`, `app/Models/Central/Clinic.php` |
| **BAIXO** | NÃ£o hÃ¡ `billing.index` | Rotas/views ausentes |

---

## 2. Arquivos Analisados

| Arquivo | FunÃ§Ã£o |
|---|---|
| `app/Services/Tenant/TenantDatabaseManager.php` | ConexÃ£o da base tenant com `purge`, `reconnect`, `forget()` e bloqueio a conexÃµes inativas |
| `app/Services/Tenant/TenantManager.php` | Armazena tenant ativo na requisiÃ§Ã£o/comando |
| `app/Http/Middleware/ResolveTenant.php` | Resolve clinic pelo usuÃ¡rio autenticado (nunca por URL/body/header) e conecta no banco |
| `app/Http/Controllers/PacienteController.php` | Upload/download/exclusÃ£o de documentos clÃ­nicos |
| `app/Services/Agenda/AgendamentoService.php` | CriaÃ§Ã£o de agendamentos com lock de cache |
| `app/Http/Middleware/EnsureTenantAccess.php` | VerificaÃ§Ã£o de assinatura/acesso (middleware Ã³rfÃ£o) |
| `config/cache.php` | ConfiguraÃ§Ã£o de cache (database, prefixo global) |
| `config/queue.php` | ConfiguraÃ§Ã£o de fila (database, sem jobs definidos) |
| `config/session.php` | ConfiguraÃ§Ã£o de sessÃ£o (database) |
| `config/filesystems.php` | Discos local/public/s3 |
| `app/Providers/AppServiceProvider.php` | Rate limiters (`integration`, `evolution`) |
| `app/Console/Commands/*.php` | Comandos que iteram tenants (`CreateTenantCommand`, `MigrateTenantsCommand`, `SeedTenantCommand`) |
| `app/Services/AuditLogger.php` | Sistema de auditoria |

---

## 3. Achados Detalhados

### 3.1. Documentos ClÃ­nicos (ALTO)

**Arquivo:** `app/Http/Controllers/PacienteController.php:196-230`

```php
$path = $request->file('arquivo')->store('documentos/'.$paciente->id, 'public');
// ...
return Storage::disk('public')->download($documento->arquivo);
// ...
Storage::disk('public')->delete($documento->arquivo);
```

**Problemas:**
- Armazenado em disco **`public`** (`storage/app/public/...`) com link simbÃ³lico em `public/storage/` â€” acessÃ­vel diretamente por URL se conhecida.
- Caminho **nÃ£o inclui `clinic_id`** nem identificador da clÃ­nica. Se dois pacientes de clÃ­nicas diferentes tiverem o mesmo ID (improvÃ¡vel, mas teoricamente possÃ­vel em bancos separados) ou se houver enumeraÃ§Ã£o, o isolamento fica comprometido.
- NÃ£o hÃ¡ verificaÃ§Ã£o explÃ­cita de que o `Paciente` pertence Ã  clÃ­nica do usuÃ¡rio autenticado. O `Paciente` usa `TenantModel` (conexÃ£o `tenant`) e o middleware `ResolveTenant` jÃ¡ conecta no banco correto, mas **nÃ£o hÃ¡ autorizaÃ§Ã£o explÃ­cita** no controller antes do download.
- `downloadDocumento` faz `authorize('pacientes.view')` mas **nÃ£o verifica** que o documento pertence ao paciente informado nem que ambos pertencem Ã  clÃ­nica atual.
- ExclusÃ£o remove do storage e apaga registro, mas sem trilha auditÃ¡vel robusta (apenas loga o caminho antes da exclusÃ£o).

**RecomendaÃ§Ã£o:**
1. Mover para disco **privado** (`local` ou disco dedicado com `visibility: private`).
2. Incluir `clinic_id` no caminho: `documentos/clinica/{$clinicId}/paciente/{$pacienteId}/...`
3. Adicionar verificaÃ§Ã£o: `$documento->paciente_id === $paciente->id` e garantir que ambos estÃ£o no banco tenant correto (jÃ¡ garantido por conexÃ£o, mas reforÃ§ar com policy).
4. Gerar URLs temporÃ¡rias assinadas ou servir via controller com verificaÃ§Ã£o de autorizaÃ§Ã£o.
5. **NÃ£o mover arquivos reais agora** â€” planejar migraÃ§Ã£o futura com script testado.

### 3.2. Locks de Agenda (ALTO)

**Arquivo:** `app/Services/Agenda/AgendamentoService.php:70-72`

```php
$consulta = Cache::lock(
    "agenda:{$dados['profissional_id']}:{$inicio->toDateString()}",
    10,
)->block(5, fn (): Appointment => DB::transaction(function () ...));
```

**Problemas:**
- Chave **nÃ£o inclui `clinica_id`**. Com cache compartilhado (driver `database`, prefixo global `APP_NAME-cache-`), um lock criado para o profissional X na clÃ­nica A na data Y pode bloquear/agrupar incorretamente com a clÃ­nica B.
- Cache store Ã© `database` (compartilhado na instÃ¢ncia). Sem isolamento por tenant, hÃ¡ risco real de **colisÃ£o entre clÃ­nicas**.
- Ao criar o agendamento, `clinica_id` Ã© adicionado (`'clinica_id' => $this->tenants->id()`) mas o **lock nÃ£o** â€” proteÃ§Ã£o incompleta.

**RecomendaÃ§Ã£o:**
- Incluir tenant na chave: `"agenda:tenant:{$this->tenants->id()}:prof:{$dados['profissional_id']}:{$inicio->toDateString()}"` (ou obter via `app(TenantManager::class)->id()`).
- Considerar incluir `clinic_id` tambÃ©m nas consultas de conflito como defesa em profundidade.
- Testar com duas clÃ­nicas fictÃ­cias: agendar simultaneamente e verificar isolamento.

### 3.3. Filas e Tarefas AssÃ­ncronas (MÃ‰DIO)

**Descoberta:**
- NÃ£o existem diretÃ³rios `app/Jobs/`, `app/Listeners/`, `app/Events/`, `app/Notifications/`.
- Ãšnico uso de `dispatch`: `app/Services/N8nDispatcher.php:46` â€” mÃ©todo `dispatch` que **"LANÃ‡A em vez de devolver false"**, mas nÃ£o implementa fila Laravel (apenas despacha para webhook n8n).
- Fila configurada em `database` com tabela `jobs` (configuraÃ§Ã£o padrÃ£o), mas **sem workers em uso aparente**.
- Comandos console existentes: `CreateTenantCommand`, `MigrateTenantsCommand`, `SeedTenantCommand` â€” estes **usam corretamente** `TenantDatabaseManager`, iteram por conexÃ£o e chamam `forget()` para limpar contexto.

**RecomendaÃ§Ã£o:**
- Se jobs forem adicionados no futuro, criar trait/classe base que **obrigatoriamente** restaura contexto de tenant (obter `clinic_id` do payload, chamar `TenantDatabaseManager::connectFor()` no `handle()`, e `forget()` no `failed()` ou via `finally`).
- Nunca serializar o objeto `Clinic` completo; passar apenas `clinic_id`.
- Garantir que `SerializesModels` nÃ£o carregue modelos de tenant sem contexto reestabelecido.
- Adicionar testes com duas clÃ­nicas fictÃ­cias antes de colocar jobs em produÃ§Ã£o.

### 3.4. Cache e Estado Compartilhado (MÃ‰DIO)

**ConfiguraÃ§Ã£o:**
- `CACHE_STORE=database`, `SESSION_DRIVER=database`, prefixo global: `Str::slug(env('APP_NAME', 'laravel')).'-cache-'` (sem `clinic_id`).
- Locks de cache usados em `AgendamentoService` sem tenant na chave.
- Rate limiters `integration` e `evolution` definidos em `AppServiceProvider` (chaves baseadas em request/IP/token â€” precisam revisÃ£o para isolamento).

**Risco:** Chaves compartilhadas entre clÃ­nicas podem colidir em cenÃ¡rios multi-tenant.

**RecomendaÃ§Ã£o:**
- Padronizar helper para gerar chaves com tenant: `cache_key('agenda', $professionalId, $date)` que injeta `tenant:{id}:` automaticamente.
- Para cache global (nÃ£o-tenant) usar namespace explÃ­cito.
- Revisar `RateLimiter` para garantir que limites nÃ£o sejam compartilhados indevidamente entre clÃ­nicas.
- Considerar usar conexÃµes de cache separadas apenas se houver justificativa real.

### 3.5. Auditoria de Registros ClÃ­nicos (MÃ‰DIO)

**Encontrado:**
- `app/Services/AuditLogger.php` existe e Ã© usado em `PacienteController` (anexar/excluir documento, criar alerta).
- Logs vÃ£o para `platform_audit_logs` (central) ou `audit_logs` (tenant)? Verificado pelo uso: `AuditLogger::log()` chamado com `depois`/`antes`. Precisa checagem de destino.
- NÃ£o cobre todas as operaÃ§Ãµes clÃ­nicas (anamnese, evoluÃ§Ã£o, prontuÃ¡rio). SÃ³ alertas e documentos foram encontrados nos controllers auditados.

**RecomendaÃ§Ã£o:**
- Definir polÃ­tica: **nÃ£o logar conteÃºdo integral de dados de saÃºde** nos logs. Logar: `user_id`, `clinic_id` (ou resolver pelo contexto), `action`, `auditable_type`, `auditable_id`, `ip`, `user_agent`, `diff` com apenas campos alterados (sem campos sensÃ­veis).
- Tornar a auditoria obrigatÃ³ria em operaÃ§Ãµes crÃ­ticas: criaÃ§Ã£o/ediÃ§Ã£o/exclusÃ£o de anamnese, prontuÃ¡rio, evoluÃ§Ã£o.
- **Nunca permitir alteraÃ§Ã£o silenciosa** â€” usar eventos/observadores ou chamadas explÃ­citas ao `AuditLogger`.
- NÃ£o registrar dados clÃ­nicos detalhados em `platform_audit_logs` (que fica no banco central).

### 3.6. Trial e Assinaturas (MÃ‰DIO)

**Comportamento atual:**
- `ResolveTenant` verifica `$clinic->isAccessible()` e aborta 403 se nÃ£o acessÃ­vel.
- `EnsureTenantAccess` (middleware Ã³rfÃ£o) verifica `$clinic->isAccessible()` e redireciona para `billing.index` com aviso (mas a rota nÃ£o existe).
- `Clinic` tem mÃ©todo `isAccessible()` (chamado em ambos os lugares).
- **Nenhum scheduler encontrado** para expirar trials/assinaturas automaticamente. VerificaÃ§Ã£o Ã© sob demanda (na requisiÃ§Ã£o).

**RecomendaÃ§Ã£o:**
- Verificar implementaÃ§Ã£o de `Clinic::isAccessible()` para entender regras exatas de trial/expirado/suspenso.
- Garantir que expiraÃ§Ã£o seja verificada **no servidor** (jÃ¡ Ã© feito via middleware).
- **NÃ£o bloquear a clÃ­nica Malheiro** sem autorizaÃ§Ã£o explÃ­cita.
- Testar com estados fictÃ­cios: ativo, trial expirado, suspenso.
- Se for adicionar comando agendado no futuro, garantir que ele respeite isolamento e nÃ£o altere dados sem critÃ©rio aprovado.

### 3.7. Middleware Ã“rfÃ£o `EnsureTenantAccess` (ALTO)

**Arquivo:** `app/Http/Middleware/EnsureTenantAccess.php`

**Descoberta:**
- Middleware existe e estÃ¡ registrado? Precisa ver `bootstrap/app.php`.
- ReferÃªncias Ã  rota `billing.index`: **nenhuma encontrada** em rotas/views/controllers.
- Quando assinatura nÃ£o estÃ¡ acessÃ­vel e requisiÃ§Ã£o **nÃ£o Ã© JSON**: redireciona para `route('billing.index')` com aviso.
- Quando Ã© JSON: retorna 403 com mensagem.

**Problemas:**
- Rota `billing.index` **inexistente** â†’ redirecionamento quebraria em produÃ§Ã£o se esse middleware fosse aplicado.
- `ResolveTenant` jÃ¡ faz verificaÃ§Ã£o similar (`isAccessible()` com mensagem diferente) e jÃ¡ estÃ¡ em uso.
- Middleware parece Ã³rfÃ£o ou preparado para uso futuro.

**RecomendaÃ§Ã£o:**
- **NÃ£o remover sem pesquisar todas as referÃªncias.** Verificar se estÃ¡ no `bootstrap/app.php`, em grupos de rota, ou em aliases.
- Se Ã³rfÃ£o e com rota inexistente, Ã© um bug em potencial. Corrigir apontando para rota vÃ¡lida ou remover com cuidado.
- Manter anÃ¡lise antes de qualquer modificaÃ§Ã£o.

### 3.8. Isolamento Multi-Tenant - Pontos Positivos

**JÃ¡ bem implementados:**
- `ResolveTenant` resolve **exclusivamente pelo usuÃ¡rio autenticado** (`$request->user()->clinic`) â€” nunca por URL/body/header. Correto.
- `TenantDatabaseManager` faz `DB::purge('tenant')` antes de reconfigurar e `reconnect()` â€” evita vazamento entre requisiÃ§Ãµes.
- ConexÃ£o padrÃ£o permanece no banco central. Models de tenant estendem `TenantModel` com `connection = 'tenant'`.
- Comandos de tenant fazem `forget()` para limpar contexto.
- `connectFor` bloqueia clÃ­nicas inativas (a menos que `$allowInactive = true` para operaÃ§Ãµes de migraÃ§Ã£o/sementes).
- `TenantServiceProvider` mencionado nos comentÃ¡rios (precisa verificar reset em Octane, mas FPM Ã© o caso atual).

---

## 4. Testes de Isolamento (ObrigatÃ³rios)

Antes de considerar as correÃ§Ãµes prontas, Ã© necessÃ¡rio criar/adaptar testes com **duas clÃ­nicas fictÃ­cias** verificando:

| Teste | Status | ObservaÃ§Ã£o |
|---|---|---|
| ClÃ­nica A nÃ£o acessa pacientes da clÃ­nica B por ID manipulado | **A criar** | Garantir autorizaÃ§Ã£o no servidor (nÃ£o confiar em URL) |
| ClÃ­nica A nÃ£o acessa prontuÃ¡rios/documentos da clÃ­nica B | **A criar** | CrÃ­tico apÃ³s correÃ§Ã£o de documentos pÃºblicos |
| ClÃ­nica A nÃ£o acessa contratos/pagamentos da clÃ­nica B | **A criar** | |
| Agendamentos nÃ£o compartilham lock entre clÃ­nicas | **A criar** | ApÃ³s corrigir chave do lock |
| Jobs nÃ£o reutilizam contexto de outra clÃ­nica | **A criar** | Quando jobs forem implementados |
| Cache/locks isolados por tenant | **A criar** | ApÃ³s padronizar chaves |
| PermissÃµes validadas no servidor | **Verificar existente** | `EnsureIntegrationAbility`, policies existentes |

**Regra:** usar exclusivamente **dados fictÃ­cios**. **NÃ£o** usar a clÃ­nica Malheiro para testes destrutivos.

---

## 5. CorreÃ§Ãµes Propostas (Sem Executar Ainda)

As seguintes correÃ§Ãµes sÃ£o propostas para implementaÃ§Ã£o em ambiente de desenvolvimento, **apÃ³s aprovaÃ§Ã£o**. Nenhuma execuÃ§Ã£o em produÃ§Ã£o.

### CorreÃ§Ã£o 1: Documentos ClÃ­nicos (Privados + Isolados)
- Alterar `storeDocumento` para usar disco `local` (privado)
- Incluir `clinic_id` no path: `clinicas/{$clinicId}/pacientes/{$pacienteId}/documentos/...`
- Em `downloadDocumento`, verificar `documento->paciente_id === $paciente->id` e autorizaÃ§Ã£o
- Servir arquivo via `Storage::disk('local')->download()` ou `response()->file()` com headers corretos
- **NÃ£o migrar arquivos existentes** â€” documentar procedimento para futuro

### CorreÃ§Ã£o 2: Lock de Agenda com Tenant
- Alterar chave em `AgendamentoService`: `"agenda:tenant:{$tenantId}:prof:{$profissionalId}:{$data}"`
- Obter `tenantId` via `app(TenantManager::class)->id()` ou `$this->tenants->id()`
- Manter lÃ³gica de transaÃ§Ã£o e idempotÃªncia intacta

### CorreÃ§Ã£o 3: Auditoria ClÃ­nica Robusta
- Estender `AuditLogger` para incluir `clinic_id` automaticamente
- Nunca logar conteÃºdo integral de dados clÃ­nicos sensÃ­veis
- Adicionar auditoria em anamnese, evoluÃ§Ã£o e alteraÃ§Ãµes em prontuÃ¡rio
- Garantir que logs de tenant vÃ£o para destino correto

### CorreÃ§Ã£o 4: Middleware Ã“rfÃ£o
- Investigar uso de `EnsureTenantAccess` em `bootstrap/app.php` e rotas
- Se Ã³rfÃ£o e `billing.index` nÃ£o existe: corrigir para rota existente ou remover
- Se usado, corrigir redirecionamento

### CorreÃ§Ã£o 5: PadronizaÃ§Ã£o de Chaves de Cache
- Criar helper `tenant_cache_key()` para injetar prefixo de tenant
- Aplicar em todos os locks e caches sensÃ­veis
- Revisar rate limiters

---

## 6. Testes Realizados

| Teste | Resultado | ObservaÃ§Ã£o |
|---|---|---|
| AnÃ¡lise de cÃ³digo dos 5 arquivos-chave | âœ… ConcluÃ­do | Identificados riscos R1-R4 |
| Busca por `billing.index` | âœ… Sem referÃªncias | Confirma rota inexistente |
| Busca por jobs/listeners/events | âœ… Vazios | Sem fila em uso atualmente |
| VerificaÃ§Ã£o de cache locks | âœ… Encontrado sem tenant | Risco confirmado |
| VerificaÃ§Ã£o de documentos pÃºblicos | âœ… Confirmado `disk('public')` | Risco confirmado |
| AnÃ¡lise de comandos console | âœ… Bom uso de tenant context | PadrÃ£o correto |
| VerificaÃ§Ã£o de push/deploy | âœ… Nenhum | Conforme regras |

---

## 7. Riscos Pendentes

| Risco | Severidade | Plano |
|---|---|---|
| Documentos jÃ¡ existentes em `storage/app/public/documentos/` | **ALTO** | NÃ£o mover agora. Planejar migraÃ§Ã£o apÃ³s correÃ§Ã£o do controller e testes. |
| `EnsureTenantAccess` pode quebrar se ativado | **ALTO** | Investigar antes de ativar. NÃ£o remover sem confirmaÃ§Ã£o completa de uso. |
| Cache compartilhado entre tenants | **ALTO** | Corrigir lock de agenda primeiro (menor mudanÃ§a, maior impacto). |
| Jobs futuros sem padrÃ£o | **MÃ‰DIO** | Criar documentaÃ§Ã£o/padrÃ£o antes de implementar fila. |
| Auditoria incompleta em prontuÃ¡rio | **MÃ‰DIO** | Avaliar por mÃ³dulo, sem logar dados sensÃ­veis. |

---

## 8. Migrations Preparadas

**Nenhuma migration preparada ou executada nesta fase.** 

Todas as correÃ§Ãµes propostas sÃ£o **aditivas ou de cÃ³digo**. Se alguma alteraÃ§Ã£o de schema se mostrar necessÃ¡ria, serÃ¡:
- Aditiva e compatÃ­vel
- Testada em desenvolvimento
- Documentada com estratÃ©gia de rollback
- **Nunca** executada em produÃ§Ã£o sem autorizaÃ§Ã£o explÃ­cita

---

## 9. EstratÃ©gia Futura de ImplantaÃ§Ã£o

1. **Desenvolvimento:** Implementar correÃ§Ãµes uma por uma com testes
2. **Testes de isolamento:** Criar testes com duas clÃ­nicas fictÃ­cias antes de merge
3. **RevisÃ£o:** Validar com proprietÃ¡rio antes de qualquer alteraÃ§Ã£o
4. **Staging:** Se houver ambiente de homologaÃ§Ã£o isolado, testar lÃ¡ primeiro
5. **ProduÃ§Ã£o:** **Apenas com autorizaÃ§Ã£o explÃ­cita.** Nunca executar migrations/deploy sem permissÃ£o

---

## 10. ConfirmaÃ§Ãµes

- âœ… **Nenhuma alteraÃ§Ã£o em produÃ§Ã£o** realizada
- âœ… **Nenhum deploy** realizado  
- âœ… **Nenhuma migration** executada em produÃ§Ã£o
- âœ… **ClÃ­nica Malheiro permanece intacta** (nÃ£o foi usada em testes destrutivos)
- âœ… **Somente anÃ¡lise e documentaÃ§Ã£o** nesta fase
- âœ… **Foco em seguranÃ§a e isolamento multi-tenant** conforme regras
- âœ… **Dados sensÃ­veis nÃ£o logados** nas anÃ¡lises
- âœ… **Apenas ambiente de desenvolvimento local** utilizado

---

**PrÃ³ximo passo:** Aguardar aprovaÃ§Ã£o do proprietÃ¡rio para iniciar as correÃ§Ãµes propostas em ambiente de desenvolvimento, seguindo a ordem de prioridade (1) Documentos, (2) Lock de agenda, (3) Middleware Ã³rfÃ£o, (4) Cache, (5) Auditoria.
