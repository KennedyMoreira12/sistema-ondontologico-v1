<?php

use App\Services\Tenant\TenantManager;

if (! function_exists('tenant')) {
    function tenant(): ?App\Models\Central\Clinic
    {
        return app(TenantManager::class)->current();
    }
}

if (! function_exists('tenant_id')) {
    function tenant_id(): ?int
    {
        return app(TenantManager::class)->id();
    }
}

if (! function_exists('tenant_cache_key')) {
    function tenant_cache_key(string $key): string
    {
        $id = app(TenantManager::class)->id();

        return $id === null ? $key : "tenant:{$id}:{$key}";
    }
}

if (! function_exists('vertical')) {
    /**
     * Vertical da clínica em atendimento: 'odontologia' ou 'fisioterapia'.
     *
     * A vertical é lida do registro central da clínica, nunca da requisição.
     */
    function vertical(): string
    {
        return app(App\Services\Tenant\VerticalManager::class)->vertical();
    }
}

if (! function_exists('vertical_permite')) {
    /**
     * A clínica em atendimento pode acessar este módulo?
     */
    function vertical_permite(string $modulo): bool
    {
        return app(App\Services\Tenant\VerticalManager::class)->permite($modulo);
    }
}

if (! function_exists('clinic_primary_color')) {
    /**
     * A cor da clínica é o acento do layout (item ativo, títulos, botões).
     * Sem clínica resolvida, vale o azul da marca LJ.
     */
    function clinic_primary_color(string $fallback = '#0b7285'): string
    {
        return app(TenantManager::class)->current()?->primary_color ?: $fallback;
    }
}

if (! function_exists('clinic_secondary_color')) {
    /**
     * Secondária da clínica: acompanha a primária na borda de destaque do menu.
     * Sem clínica resolvida, vale o verde-azulado da marca LJ.
     */
    function clinic_secondary_color(string $fallback = '#0ca678'): string
    {
        return app(TenantManager::class)->current()?->secondary_color ?: $fallback;
    }
}
