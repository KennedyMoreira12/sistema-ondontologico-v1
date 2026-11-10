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

/* ==========================================================================
 * HELPERS DE BRANDING MALHEIRO
 * ==========================================================================
 * Centralizam a identidade visual da Clínica Malheiro (clinic_id = 1).
 * NÃO espalhar verificações de ID por Views/Controllers: use estes helpers,
 * que delegam ao App\Services\Branding\MalheiroBranding.
 * ========================================================================== */

if (! function_exists('is_malheiro')) {
    /**
     * O tenant atual é a Clínica Malheiro?
     */
    function is_malheiro(): bool
    {
        return app(MalheiroBranding::class)->isMalheiro();
    }
}

if (! function_exists('malheiro_cores')) {
    /**
     * Cores da identidade Malheiro.
     *
     * @return array<string, string>
     */
    function malheiro_cores(): array
    {
        return app(MalheiroBranding::class)->cores();
    }
}

if (! function_exists('malheiro_cor_primaria')) {
    /**
     * Cor primária (teal) da identidade Malheiro.
     */
    function malheiro_cor_primaria(): string
    {
        return app(MalheiroBranding::class)->corPrimaria();
    }
}

if (! function_exists('malheiro_cor_secundaria')) {
    /**
     * Cor secundária (azul-teal escuro) da identidade Malheiro.
     */
    function malheiro_cor_secundaria(): string
    {
        return app(MalheiroBranding::class)->corSecundaria();
    }
}

if (! function_exists('malheiro_profissional')) {
    /**
     * Dados do profissional Malheiro.
     *
     * @return array{nome: string, cro: string, especialidades: list<string>}
     */
    function malheiro_profissional(): array
    {
        return app(MalheiroBranding::class)->profissional();
    }
}

if (! function_exists('malheiro_nome_profissional')) {
    /**
     * Nome do profissional Malheiro.
     */
    function malheiro_nome_profissional(): string
    {
        return app(MalheiroBranding::class)->nomeProfissional();
    }
}

if (! function_exists('malheiro_cro')) {
    /**
     * CRO do profissional Malheiro.
     */
    function malheiro_cro(): string
    {
        return app(MalheiroBranding::class)->cro();
    }
}

if (! function_exists('malheiro_especialidades')) {
    /**
     * Especialidades na ordem obrigatória.
     *
     * @return list<string>
     */
    function malheiro_especialidades(): array
    {
        return app(MalheiroBranding::class)->especialidades();
    }
}

if (! function_exists('malheiro_frase_institucional')) {
    /**
     * Frase institucional (configurável no banco, com padrão da arte).
     */
    function malheiro_frase_institucional(): string
    {
        return app(MalheiroBranding::class)->fraseInstitucional();
    }
}

if (! function_exists('malheiro_dados_rodape')) {
    /**
     * Dados da clínica para o rodapé dos documentos.
     *
     * @return array<string, ?string>
     */
    function malheiro_dados_rodape(): array
    {
        return app(MalheiroBranding::class)->dadosRodape();
    }
}

if (! function_exists('malheiro_dados_documento')) {
    /**
     * Dados completos para as views de documentos Malheiro.
     *
     * @return array<string, mixed>
     */
    function malheiro_dados_documento(): array
    {
        return app(MalheiroBranding::class)->dadosParaDocumento();
    }
}

if (! function_exists('usar_layout_malheiro')) {
    /**
     * Se os documentos do tenant atual devem usar o layout Malheiro.
     */
    function usar_layout_malheiro(): bool
    {
        return app(MalheiroBranding::class)->usarLayoutMalheiro();
    }
}
