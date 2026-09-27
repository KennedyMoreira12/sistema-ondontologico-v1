<?php

namespace App\Providers;

use App\Models\Central\User;
use App\Rules\Cpf;
use App\Services\Tenant\TenantManager;
use Illuminate\Support\Facades\Gate;
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

        Validator::extend('cpf', function (string $attribute, mixed $value, array $parameters, $validator) {
            $errors = [];

            (new Cpf)->validate($attribute, $value, function ($message) use (&$errors) {
                $errors[] = $message;
            });

            return count($errors) === 0;
        });
    }
}
