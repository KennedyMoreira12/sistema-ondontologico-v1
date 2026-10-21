<?php

use App\Models\Central\Clinic;
use App\Services\Tenant\TenantManager;
use App\Services\Tenant\VerticalManager;
use Illuminate\Support\Facades\Storage;

if (! function_exists('tenant')) {
    function tenant(): ?Clinic
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
        return app(VerticalManager::class)->vertical();
    }
}

if (! function_exists('vertical_permite')) {
    /**
     * A clínica em atendimento pode acessar este módulo?
     */
    function vertical_permite(string $modulo): bool
    {
        return app(VerticalManager::class)->permite($modulo);
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

if (! function_exists('clinic_logo_url')) {
    /**
     * Logo da clínica em atendimento, ou null quando ela não configurou nenhum.
     *
     * O arquivo vive no disco público, em `clinicas/{id}/marca`. A URL é
     * montada pelo próprio disco, que já sabe o prefixo público do deploy —
     * e é a clínica que faz o upload, do próprio painel dela.
     */
    function clinic_logo_url(): ?string
    {
        $caminho = app(TenantManager::class)->current()?->logo_path;

        if (! is_string($caminho) || $caminho === '') {
            return null;
        }

        return Storage::disk('public')->url($caminho);
    }
}
