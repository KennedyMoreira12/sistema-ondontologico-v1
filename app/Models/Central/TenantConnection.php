<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Credenciais de conexão com o banco de uma clínica.
 *
 * A senha é criptografada em repouso pelo cast `encrypted`. Ela nunca sai
 * daqui para uma tela ou um log: use `$hidden` e um método de-status para
 * diagnóstico.
 */
#[Fillable([
    'clinic_id', 'driver', 'database_host', 'database_port',
    'database_name', 'database_username', 'database_password', 'is_active', 'last_migrated_at',
])]
#[Hidden(['database_password'])]
class TenantConnection extends CentralModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'database_port' => 'integer',
            'database_password' => 'encrypted',
            // Sem este cast o sqlite devolve 0/1 e o `! is_active` do
            // TenantDatabaseManager deixaria de valer para valor vindo do banco.
            'is_active' => 'boolean',
            'last_migrated_at' => 'datetime',
        ];
    }

    /**
     * Desligar a conexão é o botão de emergência de uma clínica: o painel e a
     * API param de responder sem derrubar o banco nem apagar dado nenhum.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toConnectionConfig(): array
    {
        return array_merge([
            'driver' => $this->driver,
            'database' => $this->database_name,
            'prefix' => '',
            'prefix_indexes' => true,
            'foreign_key_constraints' => true,
        ], $this->driver === 'sqlite' ? [] : [
            'host' => $this->database_host,
            'port' => $this->database_port,
            'username' => $this->database_username,
            'password' => $this->database_password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict' => true,
            'engine' => null,
        ]);
    }
}
