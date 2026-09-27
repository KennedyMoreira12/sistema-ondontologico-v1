<?php

namespace App\Services\Tenant;

use App\Models\Central\Clinic;
use App\Models\Central\TenantConnection;

/**
 * Guarda a clínica que está atendendo a requisição atual.
 *
 * Só o servidor define isto — nunca um parâmetro do navegador. A cadeia é
 * sempre: usuário autenticado -> clinic_id -> Clinic -> TenantConnection.
 */
class TenantManager
{
    protected ?Clinic $tenant = null;

    public function set(Clinic $clinic): void
    {
        $this->tenant = $clinic;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    public function current(): ?Clinic
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    public function databaseName(): ?string
    {
        return $this->tenant?->tenantConnection?->database_name;
    }

    public function connection(): ?TenantConnection
    {
        return $this->tenant?->tenantConnection;
    }

    public function require(): Clinic
    {
        if ($this->tenant === null) {
            abort(403, 'Nenhuma clínica foi selecionada para esta requisição.');
        }

        return $this->tenant;
    }

    /**
     * Prefixo do filesystem de uma clínica. Documentos de pacientes usam ids
     * que se repetem em todos os bancos, então o caminho precisa ser prefixed
     * pela clínica para não colidir entre elas.
     */
    public function storagePrefix(string $suffix = ''): string
    {
        $base = trim(config('tenancy.uploads_path').'/'.$this->id(), '/');

        return $suffix === '' ? $base : $base.'/'.trim($suffix, '/');
    }
}
