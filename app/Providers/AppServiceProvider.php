<?php

namespace App\Providers;

use App\Models\Central\IntegrationToken;
use App\Models\Central\User;
use App\Rules\Cpf;
use App\Services\Tenant\TenantManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /**
         * O projeto é Bootstrap 5; sem isso o Laravel usa o view padrão do
         * Tailwind, cujas classes de tamanho do SVG não existem aqui e a seta
         * de navegação era renderizada no tamanho intrínseco (300x150px).
         */
        Paginator::useBootstrapFive();

        /**
         * Papéis e permissões são POR CLÍNICA, então esta checagem só funciona
         * depois que o ResolveTenant conectou no banco da clínica. É por isso
         * que a ordem dos middlewares é auth -> tenant -> autorização: o Gate
         * é avaliado dentro dos controllers, depois dos dois.
         */
        Gate::before(function (User $user, string $ability) {
            return $user->hasPermission($ability) ? true : null;
        });

        /**
         * A clínica do logo e do nome é a do tenant resolvido, nunca a
         * primeira linha da tabela — o `Clinica::first()` original mostrava o
         * nome de outra clínica para todo mundo.
         */
        View::composer('layouts.app', function ($view): void {
            $view->with('clinica', app(TenantManager::class)->current());
        });

        /**
         * As funções clinic_primary_color() / clinic_secondary_color() usadas
         * nos layouts vivem em app/Support/helpers.php, carregadas pelo autoload
         * "files" do composer.json — Blade não tem um helper() para registrar
         * funções callable de dentro do {{ }}.
         */
        Validator::extend('cpf', function (string $attribute, mixed $value, array $parameters, $validator) {
            $errors = [];

            (new Cpf)->validate($attribute, $value, function ($message) use (&$errors) {
                $errors[] = $message;
            });

            return count($errors) === 0;
        });

        /**
         * Limite da API de integração (n8n, webhooks).
         *
         * A chave é o token, não o IP: quem chama é o n8n, várias clínicas
         * saem do mesmo endereço e um limite por IP faria a clínica mais barulhenta
         * estourar o limite das outras. A chave é o SHA-256 do token, então o
         * token em claro não vira chave de cache.
         *
         * Três baldes, porque os endpoints não têm o mesmo peso: ler a agenda
         * custa uma consulta e o bot de atendimento chama isso a cada mensagem;
         * criar paciente e criar agendamento escrevem, e é deles que sai o prejuízo
         * se algo repetir em loop.
         *
         * O peso sai do método HTTP, não da rota: quem escreve passa pelo balde
         * apertado e pelo diário, quem lê só pelo diário. Um token de leitura que
         * precisasse aguentar 120/min não pode gastar o orçamento das escritas,
         * que é o número que protege a agenda.
         */
        RateLimiter::for('integration', function (Request $request): Limit|array {
            $token = IntegrationToken::extractFromRequest($request);

            $chave = $token === null
                ? 'anon:'.$request->ip()
                : 'token:'.IntegrationToken::hashToken($token);

            $diario = Limit::perDay(2000)->by('dia:'.$chave);

            return $request->isMethod('GET') || $request->isMethod('HEAD')
                ? [Limit::perMinute(120)->by('req:'.$chave), $diario]
                : [Limit::perMinute(20)->by('req:'.$chave), $diario];
        });

        /**
         * Limite do webhook da Evolution.
         *
         * A chave é a INSTÂNCIA, e não o IP: todas as instâncias das clínicas
         * saem do mesmo endereço da Evolution, e um limite por IP faria a clínica
         * mais barulhenta estourar o limite das outras. Por instância, uma
         * clínica não trava a outra, e uma requisição forjada de uma só
         * instância não gasta o orçamento de ninguém.
         *
         * A instância vem do corpo, não da URL, porque é o mesmo campo que o
         * middleware usa para achar a clínica. Quando ela não vem, cai no IP:
         * ainda é melhor que nenhuma chave, que contaria tudo como uma requisição
         * só.
         *
         * 120 por minuto é folgado para o pior caso real — uma clínica recebe
         * algumas dezenas de mensagens por minuto — e aperta o bastante para que
         * um laço de reentrega da Evolution não consuma a fila do resto.
         */
        RateLimiter::for('evolution', function (Request $request): Limit|array {
            $instancia = $request->json('instance');
            $instancia = is_string($instancia) && $instancia !== '' ? $instancia : null;

            $chave = $instancia === null ? 'ip:'.$request->ip() : 'instancia:'.$instancia;

            return [
                Limit::perMinute(120)->by('min:'.$chave),
                Limit::perHour(2000)->by('hora:'.$chave),
            ];
        });
    }
}
