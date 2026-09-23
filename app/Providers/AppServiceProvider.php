<?php

namespace App\Providers;

use App\Models\Clinica;
use App\Models\User;
use App\Rules\Cpf;
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
        Gate::before(function (User $user, string $ability) {
            return $user->hasPermission($ability) ? true : null;
        });

        View::composer('layouts.app', function ($view) {
            $view->with('clinica', Clinica::first());
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