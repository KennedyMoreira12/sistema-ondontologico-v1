<?php

namespace App\Providers;

use App\Services\Fiscal\FiscalManager;
use Illuminate\Support\ServiceProvider;

class FiscalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FiscalManager::class, fn ($app) => new FiscalManager($app));
        $this->app->alias(FiscalManager::class, 'fiscal');
    }
}
