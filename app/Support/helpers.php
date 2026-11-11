<?php

use App\Models\Central\Clinic;
use App\Services\Branding\MalheiroBranding;
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
     * Ramo/vertical da clínica em atendimento (qualquer string).
     *
     * Lida do registro central da clínica, nunca da requisição.
     */
    function vertical(): string
    {
        $clinic = app(TenantManager::class)->current();

        return $clinic?->vertical ?? '';
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

/**
 * ============================================================================
 * HELPERS DE BRANDING MALHEIRO
 * ============================================================================
 * Centralizam a identidade visual da Clínica Malheiro (clinic_id = 1).
 * Não espalhar verificações de ID por Views/Controllers.
 * ============================================================================
 */

if (! function_exists('is_malheiro')) {
    /**
     * Verifica se o tenant atual é a Clínica Malheiro.
     */
    function is_malheiro(): bool
    {
        return app(MalheiroBranding::class)->isMalheiro();
    }
}

if (! function_exists('malheiro_cores')) {
    /**
     * Retorna array de cores da identidade Malheiro.
     */
    function malheiro_cores(): array
    {
        return app(MalheiroBranding::class)->cores();
    }
}

if (! function_exists('malheiro_cor_primaria')) {
    /**
     * Retorna cor primária (teal) da identidade Malheiro.
     */
    function malheiro_cor_primaria(): string
    {
        return app(MalheiroBranding::class)->corPrimaria();
    }
}

if (! function_exists('malheiro_cor_secundaria')) {
    /**
     * Retorna cor secundária (azul-teal escuro) da identidade Malheiro.
     */
    function malheiro_cor_secundaria(): string
    {
        return app(MalheiroBranding::class)->corSecundaria();
    }
}

if (! function_exists('malheiro_profissional')) {
    /**
     * Retorna dados completos do profissional Malheiro.
     */
    function malheiro_profissional(): array
    {
        return app(MalheiroBranding::class)->profissional();
    }
}

if (! function_exists('malheiro_nome_profissional')) {
    /**
     * Retorna nome do profissional Malheiro.
     */
    function malheiro_nome_profissional(): string
    {
        return app(MalheiroBranding::class)->nomeProfissional();
    }
}

if (! function_exists('malheiro_cro')) {
    /**
     * Retorna CRO do profissional Malheiro.
     */
    function malheiro_cro(): string
    {
        return app(MalheiroBranding::class)->cro();
    }
}

if (! function_exists('malheiro_especialidades')) {
    /**
     * Retorna especialidades na ordem obrigatória.
     */
    function malheiro_especialidades(): array
    {
        return app(MalheiroBranding::class)->especialidades();
    }
}

if (! function_exists('malheiro_especialidade_principal')) {
    /**
     * Retorna primeira especialidade (para destaque visual).
     */
    function malheiro_especialidade_principal(): string
    {
        return app(MalheiroBranding::class)->especialidadePrincipal();
    }
}

if (! function_exists('malheiro_frase_institucional')) {
    /**
     * Retorna frase institucional (configurável no banco).
     */
    function malheiro_frase_institucional(): string
    {
        return app(MalheiroBranding::class)->fraseInstitucional();
    }
}

if (! function_exists('malheiro_dados_rodape')) {
    /**
     * Retorna dados da clínica para rodapé dos documentos.
     */
    function malheiro_dados_rodape(): array
    {
        return app(MalheiroBranding::class)->dadosRodape();
    }
}

if (! function_exists('malheiro_dados_documento')) {
    /**
     * Retorna array completo de dados para Views de documentos Malheiro.
     */
    function malheiro_dados_documento(): array
    {
        return app(MalheiroBranding::class)->dadosParaDocumento();
    }
}

if (! function_exists('usar_layout_malheiro')) {
    /**
     * Verifica se deve usar layout Malheiro nos documentos.
     */
    function usar_layout_malheiro(): bool
    {
        return app(MalheiroBranding::class)->usarLayoutMalheiro();
    }
}