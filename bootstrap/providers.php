<?php

use App\Providers\AppServiceProvider;
use App\Providers\FiscalServiceProvider;
use App\Providers\TenantServiceProvider;

return [
    AppServiceProvider::class,
    TenantServiceProvider::class,
    FiscalServiceProvider::class,
];
