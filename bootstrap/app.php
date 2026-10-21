<?php

use App\Http\Middleware\CheckPlanFeature;
use App\Http\Middleware\EnsurePlatformAdminIsActive;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTenantAccess;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            /**
             * O painel da empresa entra pelo grupo `web` (sessão, CSRF) e protege
             * as rotas com `auth:admin` + `platform.active`. O `auth` global que
             * o Laravel 11+ aplica por padrão exige um usuário de clínica, então
             * o guard é escolhido rota a rota, nunca herdado.
             */
            Route::middleware('web')->group(base_path('routes/platform.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'tenant.access' => EnsureTenantAccess::class,
            'plan' => CheckPlanFeature::class,
            'platform.active' => EnsurePlatformAdminIsActive::class,
            'superadmin' => EnsureSuperAdmin::class,
        ]);

        /**
         * `ResolveTenant` tem que rodar ANTES de `SubstituteBindings`, e não como
         * middleware de rota.
         *
         * O grupo `web` já traz `SubstituteBindings`, e ele é o que resolve o
         * route model binding implícito de `{paciente}`, `{consulta}`, `{orcamento}`
         * e companhia — logo ele dispara uma query no banco da clínica. Middleware
         * de rota entra só depois do grupo inteiro, então com `['auth', 'tenant']`
         * a ordem real era: sessão iniciada, `SubstituteBindings` consulta
         * `pacientes` na conexão `tenant` sem database definido, e só depois
         * `ResolveTenant` conectava. Resultado: 500 "No database selected" em toda
         * rota com parâmetro, enquanto os índices (que consultam o model dentro do
         * controller) funcionavam — um bug que só aparece em rotas com model
         * binding.
         *
         * Aqui a ordem é corrigida pela lista de prioridade: `ResolveTenant` entra
         * logo antes de `SubstituteBindings`, ou seja, depois de `StartSession` e de
         * `Authenticate` (ambos já o antecederam no grupo `web`, e o próprio
         * `ResolveTenant` depende do `$request->user()`), e antes de qualquer query
         * de model.
         */
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            ResolveTenant::class,
        );

        /**
         * Guest cai sempre na tela única de login, seja numa rota de clínica
         * seja no painel. O `guest()` guarda a URL pretendida na sessão, e o
         * LoginRequest só a devolve se ela pertencer ao guard que autenticou —
         * então um admin que caiu aqui tentando abrir `/painel/clinicas` volta
         * para lá, e um usuário de clínica nunca é jogado de volta ao painel.
         */
        $middleware->redirectGuestsTo(fn (): string => route('login'));

        /**
         * E quem já tem sessão vai para o lado do seu guard: o admin para o
         * painel, o usuário de clínica para o dashboard. Isso também quebra o
         * laço de um usuário de clínica que abre `/painel` — mandá-lo para
         * `painel.dashboard` o faria bater no `auth:admin` e voltar aqui.
         */
        $middleware->redirectUsersTo(
            fn (): string => Auth::guard('admin')->check()
                ? route('painel.dashboard')
                : route('dashboard')
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->create();

/**
 * Cache de bootstrap fora do OneDrive.
 *
 * `bootstrap/cache` precisa ser gravável, e dentro de uma pasta sincronizada o
 * OneDrive põe uma ACE de `Deny` que faz até o `is_writable` do PHP mentir: a
 * pasta parece gravável para o Explorer e não é para o PHP. O sintoma é o
 * PackageManifest estourar "the bootstrap/cache directory must be present and
 * writable" em toda chamada de artisan, e `config:cache`/`route:cache` nunca
 * completarem.
 *
 * Não dá para corrigir pela permissão: o OneDrive devolve o `Deny` na sequência.
 * E também não dá para ler um `.env` aqui — este arquivo roda antes do
 * `LoadEnvironmentVariables`, então `env()` ainda devolve `null`, que é
 * exatamente o motivo de uma versão anterior deste arquivo carregar um caminho
 * `C:\laragon\...` fixo no código, quebrando em qualquer outra máquina.
 *
 * A solução é portátil e não precisa de configuração: só quando a pasta padrão
 * está realmente ingravável o cache vai para o temp do sistema, com o nome
 * derivado da raiz do projeto para não colidir entre dois clones. Em Linux,
 * macOS, CI e no deploy o `bootstrap/cache` é gravável e nada muda.
 */
if (! is_dir($bootstrapCache = $app->bootstrapPath('cache')) || ! is_writable($bootstrapCache)) {
    $app->useBootstrapPath(
        sys_get_temp_dir().DIRECTORY_SEPARATOR.'sco-bootstrap-'.md5(dirname(__DIR__))
    );

    if (! is_dir($bootstrapCache = $app->bootstrapPath('cache'))) {
        @mkdir($bootstrapCache, 0755, true);
    }
}

return $app;
