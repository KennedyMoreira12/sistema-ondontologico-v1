# Relatório Técnico: Sistema Odontológico Multitenant

## Arquitetura e Estrutura

**Versão:** Laravel 13.33.0 · PHP 8.3 · Inertia.js + Vue 3 · Tailwind CSS

### Sistema Multitenant
- Banco central (shared) vs bancos por clínica (tenant)
- Isolamento via `clinic_id` em todas as tabelas
- Cada clínica tem seu próprio banco de dados

### Autenticação Duas Guards
- **`web`** → `App\Models\Central\User` → `http://localhost:8000/login`
- **`admin`** → `App\Models\Central\AdminUser` → `http://localhost:8000/painel/login`

### Roles e Permissions
Roles: `super_admin`, `admin`, `dentista`, `recepcao`, `financeiro`, `estoque`, `auxiliar`
Permissões: 15 módulos (clínica, paciente, agenda, financeiro, estoque, relatórios, etc.)

### Tabelas Principais (Central)
- `admin_users`, `users`, `roles`, `permissions`, `role_permission`
- `clinics`, `plans`, `subscriptions`, `system_settings`

### Tabelas por Clínica (Tenant)
- Todos os módulos clínicos: `patients`, `appointments`, `agenda`, `financeiro`, `estoque`, `convenios`, `prontuarios`, etc.
- Todas têm `clinic_id` como chave estrangeira

## Módulos do Sistema

1. **Pacientes** - cadastro, histórico, prontuário eletrônico
2. **Agenda/Consultas** - agenda, waitlist, tipos de consulta
3. **Financeiro** - contas, pagamentos, convênios
4. **Prontuário** - anamnese, evoluções, documentação
5. **Estoques** - produtos, movimentações, validade
6. **Convênios** - operadoras e credenciamentos
7. **Profissionais** - dentistas e funcionários
8. **Odontograma** - gráficos dentais
9. **Orçamentos** - planos de tratamento
10. **Relatórios** - dashboards, KPIs, exports

## Rotas Principais

### Auth (`routes/auth.php`)
- `GET/POST login` - login clínica
- `GET/POST logout` - logout
- `GET/POST register` - registro
- Password reset flow

### Platform (`routes/platform.php`)
- `GET/POST login` - login painel admin
- Gestão de clínicas e planos

### Web (`routes/web.php`)
- `GET/` - dashboard (auth + tenant middleware)
- `GET/admin/` - área administrativa
- Resource routes: users, pacientes, agenda, financeiro, estoque, relatórios, etc.
- `perfis` e `permissoes` management

## Configuração (.env Essencial)
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `ADMIN_EMAIL`, `ADMIN_PASSWORD` - credenciais super admin
- `MAIL_*` - configurações de email
- `SESSION_DRIVER`, `SESSION_LIFETIME`
- `AUTH_GUARD`, `AUTH_PASSWORD_BROKER`

## Sistema de Seeders
- `DatabaseSeeder` → roda `AdminUserSeeder` central
- `DemoSeeder` → cria usuários demo por clínica
- `RolePermissionSeeder` → atribui roles/permissions por tenant

## Padrões e Convenções
- `clinic_id` em todas as tabelas de usuários/clínica
- Models estendem `CentralAuthenticatable` para auth consistente
- Roles via spatie/laravel-permission (implied pela estrutura)
- Migrações tenant via `php artisan tenant:migrate`
- Views em `resources/views/` com Blade + Tailwind
- Inertia.js para SPA experience com Vue 3 components

## Instalação e Setup
1. `composer install`
2. `cp .env.example .env`
3. `php artisan key:generate`
4. `php artisan jwt:secret` (se usar JWT)
5. `php artisan migrate --force`
6. `php artisan db:seed --class=DatabaseSeeder`
7. `npm install && npm run build`
8. `php artisan serve`

## Observações Importantes
- Sistema requer configuração de múltiplos bancos de dados para multi-tenant
- Seeders devem ser rodados após migrações
- Login admin e login clínica são sistemas separados (guards diferentes)
- Permissões são granulares por role e módulo
- Dashboard protege por `auth` + `tenant` middleware