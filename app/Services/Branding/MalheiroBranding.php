<?php

namespace App\Services\Branding;

use App\Models\ClinicProfile;
use App\Services\Tenant\TenantManager;
use Illuminate\Support\Facades\Storage;

/**
 * Resolve a identidade visual da Clínica Malheiro (clinic_id = 1).
 *
 * Centraliza toda a lógica de branding para que NÃO haja verificações
 * espalhadas como `if ($clinic->id == 1)` por Controllers, Views e Services.
 *
 * A identidade Malheiro é aplicada EXCLUSIVAMENTE ao tenant 1.
 * Outros tenants usam suas próprias configurações (logo, cores, profissional).
 */
class MalheiroBranding
{
    public const MALHEIRO_CLINIC_ID = 1;

    protected TenantManager $tenants;

    // Cores oficiais extraídas das referências visuais (teal/turquesa + cinza + branco)
    protected array $cores = [
        'primary' => '#0ca678',  // teal principal
        'secondary' => '#0b7285',  // azul-teal escuro
        'dark' => '#063642',  // azul profundo
        'gray' => '#5a6a72',  // cinza médio
        'light' => '#f4f7f9',  // fundo claro
        'white' => '#ffffff',
        'text' => '#1d2b33',  // texto principal
    ];

    // Dados fixos do profissional Malheiro (conforme especificação)
    protected array $profissionalFixo = [
        'nome' => 'Dr. Alessandro Agostinho Malheiro',
        'cro' => 'CRO-MG 20.545',
        'especialidades' => [
            'ESPECIALISTA EM IMPLANTES',
            'DENTÍSTICA RESTAURADORA E ESTÉTICA',
        ],
    ];

    public function __construct(TenantManager $tenants)
    {
        $this->tenants = $tenants;
    }

    /**
     * Verifica se o tenant atual é a Clínica Malheiro.
     */
    public function isMalheiro(): bool
    {
        return $this->tenants->id() === self::MALHEIRO_CLINIC_ID;
    }

    /**
     * Id da clínica em atendimento (acesso público ao TenantManager).
     *
     * Controllers não devem tocar em `$this->tenants` diretamente: a
     * propriedade é protegida e o acesso externo quebra em runtime.
     */
    public function clinicaId(): ?int
    {
        return $this->tenants->id();
    }

    /**
     * Retorna as cores da identidade Malheiro.
     */
    public function cores(): array
    {
        return $this->cores;
    }

    /**
     * Retorna cor primária (teal).
     */
    public function corPrimaria(): string
    {
        return $this->cores['primary'];
    }

    /**
     * Retorna cor secundária (azul-teal escuro).
     */
    public function corSecundaria(): string
    {
        return $this->cores['secondary'];
    }

    /**
     * Retorna dados do profissional Malheiro.
     */
    public function profissional(): array
    {
        return $this->profissionalFixo;
    }

    /**
     * Retorna nome do profissional.
     */
    public function nomeProfissional(): string
    {
        return $this->profissionalFixo['nome'];
    }

    /**
     * Retorna CRO do profissional.
     */
    public function cro(): string
    {
        return $this->profissionalFixo['cro'];
    }

    /**
     * Retorna especialidades na ordem obrigatória.
     */
    public function especialidades(): array
    {
        return $this->profissionalFixo['especialidades'];
    }

    /**
     * Retorna primeira especialidade (destaque visual).
     */
    public function especialidadePrincipal(): string
    {
        return $this->profissionalFixo['especialidades'][0] ?? '';
    }

    /**
     * Retorna frase institucional configurável.
     * Se não configurada no banco, usa a padrão.
     */
    public function fraseInstitucional(): string
    {
        $clinica = $this->tenants->current();

        if ($clinica && ! empty($clinica->frase_institucional)) {
            return $clinica->frase_institucional;
        }

        return 'Porque ninguém resiste a um belo sorriso...';
    }

    /**
     * Logo da clínica atual.
     * Para Malheiro, usa o logo específico se existir; senão, o PNG original
     * extraído da arte oficial. Outros tenants usam o sistema padrão.
     */
    public function logoUrl(): ?string
    {
        $clinica = $this->tenants->current();

        if (! $clinica) {
            return null;
        }

        if ($clinica->logo_path) {
            return Storage::disk('public')->url($clinica->logo_path);
        }

        if ($this->isMalheiro()) {
            return asset('img/malheiro-logo.png');
        }

        return null;
    }

    /**
     * Logo como data URI, para embutir nos PDFs.
     *
     * O Dompdf não resolve URLs HTTP na configuração padrão do projeto, e o
     * documento precisa funcionar offline e em qualquer host. A logo carregada
     * pela clínica tem prioridade; a Clínica Malheiro tem o PNG original
     * (extraído da arte oficial) como fallback. Outros tenants não recebem a
     * logo do Malheiro.
     */
    public function logoDataUri(): ?string
    {
        $clinica = $this->tenants->current();

        $caminho = null;

        if ($clinica && $this->isMalheiro() && $clinica->logo_path) {
            $caminho = Storage::disk('public')->path($clinica->logo_path);
        } elseif ($this->isMalheiro()) {
            $caminho = public_path('img/malheiro-logo.png');
        }

        if (! is_string($caminho) || ! is_file($caminho)) {
            return null;
        }

        $conteudo = @file_get_contents($caminho);

        if ($conteudo === false) {
            return null;
        }

        $mime = function_exists('mime_content_type') ? (string) mime_content_type($caminho) : '';

        return 'data:'.($mime !== '' ? $mime : 'image/png').';base64,'.base64_encode($conteudo);
    }

    /**
     * Retorna dados da clínica para rodapé (endereço, telefone, WhatsApp).
     * Vem do ClinicProfile do tenant (banco da clínica).
     */
    public function dadosRodape(): array
    {
        $perfil = ClinicProfile::current();

        return [
            'nome' => $this->tenants->current()?->name,
            'endereco' => $this->formatarEndereco($perfil),
            'telefone' => $perfil->phone,
            'whatsapp' => $perfil->whatsapp,
            'cidade' => $perfil->city,
            'estado' => $perfil->state,
            'cep' => $perfil->postal_code,
        ];
    }

    /**
     * Formata endereço completo para exibição no rodapé.
     */
    protected function formatarEndereco(ClinicProfile $perfil): string
    {
        $partes = array_filter([
            $perfil->address,
            $perfil->number,
            $perfil->complement ? "{$perfil->complement}" : null,
            $perfil->district,
            $perfil->city,
            $perfil->state,
            $perfil->postal_code ? "CEP {$perfil->postal_code}" : null,
        ]);

        return implode(', ', $partes);
    }

    /**
     * Retorna cor primária da clínica atual (sistema padrão ou Malheiro).
     * Para uso em CSS dinâmico.
     */
    public function corPrimariaAtual(): string
    {
        if ($this->isMalheiro()) {
            return $this->corPrimaria();
        }

        return $this->tenants->current()?->primary_color ?? '#0b7285';
    }

    /**
     * Retorna cor secundária da clínica atual.
     */
    public function corSecundariaAtual(): string
    {
        if ($this->isMalheiro()) {
            return $this->corSecundaria();
        }

        return $this->tenants->current()?->secondary_color ?? '#0ca678';
    }

    /**
     * Verifica se deve usar layout Malheiro.
     * Útil para Views e Controllers decidirem qual template carregar.
     */
    public function usarLayoutMalheiro(): bool
    {
        return $this->isMalheiro();
    }

    /**
     * Retorna array completo de dados para passar às Views de documentos.
     */
    public function dadosParaDocumento(): array
    {
        return [
            'is_malheiro' => $this->isMalheiro(),
            'cores' => $this->cores(),
            'cor_primaria' => $this->corPrimariaAtual(),
            'cor_secundaria' => $this->corSecundariaAtual(),
            'logo_url' => $this->logoUrl(),
            'logo_data_uri' => $this->logoDataUri(),
            'profissional' => $this->profissional(),
            'nome_profissional' => $this->nomeProfissional(),
            'cro' => $this->cro(),
            'especialidades' => $this->especialidades(),
            'especialidade_principal' => $this->especialidadePrincipal(),
            'frase_institucional' => $this->fraseInstitucional(),
            'rodape' => $this->dadosRodape(),
            'clinica_nome' => $this->tenants->current()?->name,
        ];
    }
}
