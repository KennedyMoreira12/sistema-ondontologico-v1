<?php

namespace App\Console\Commands;

use App\Models\Central\Clinic;
use App\Services\Tenant\InventarioTabelas;
use App\Services\Tenant\TenantDatabaseManager;
use App\Services\Tenant\TenantManager;
use App\Services\Tenant\VerticalManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * Audita as tabelas de cada clínica: vertical de cada uma, tabela sem
 * classificação e tabela que foi removida.
 *
 * A vertical não apaga nada: as tabelas de odontologia continuam existindo
 * para a clínica de fisioterapia, e as de fisioterapia para a odontológica. Este
 * comando é o que torna isso verificável, e não apenas uma promessa.
 */
class AuditarTabelasCommand extends Command
{
    protected $signature = 'tenants:auditar-tabelas
        {--clinica= : ID da clínica específica (padrão: todas)}';

    protected $description = 'Audita as tabelas de cada clínica: vertical, classificação e tabelas removidas';

    public function handle(InventarioTabelas $inventario, TenantDatabaseManager $connections, TenantManager $tenants): int
    {
        $clinics = Clinic::query()
            ->when($this->option('clinica'), fn ($query, $id) => $query->whereKey($id))
            ->whereHas('tenantConnection')
            ->with('tenantConnection')
            ->get();

        if ($clinics->isEmpty()) {
            $this->components->warn('Nenhuma clínica encontrada.');

            return self::SUCCESS;
        }

        $comProblemas = [];

        foreach ($clinics as $clinic) {
            $vertical = app(VerticalManager::class)->vertical($clinic);

            try {
                $connections->connectFor($clinic, allowInactive: true);
                $tenants->set($clinic);

                $resultado = $inventario->auditar();
            } catch (Throwable $exception) {
                $this->components->error("[{$clinic->name}] {$exception->getMessage()}");
                $comProblemas[] = $clinic->name;

                continue;
            } finally {
                $tenants->forget();
                $connections->forget();
            }

            if ($resultado['ok']) {
                $this->components->twoColumnDetail(
                    $clinic->name,
                    $vertical.' — '.count($resultado['tabelas']).' tabelas ok'
                );

                continue;
            }

            $this->components->error("[{$clinic->name}]");

            foreach ($resultado['problemas'] as $problema) {
                $this->components->warn('  - '.$problema);
            }

            $comProblemas[] = $clinic->name;
        }

        if ($comProblemas !== []) {
            $this->newLine();
            $this->components->error(count($comProblemas).' clínica(s) com problema: '.implode(', ', $comProblemas));

            return self::FAILURE;
        }

        $this->components->info('Todas as clínicas passaram na auditoria de tabelas.');

        return self::SUCCESS;
    }
}
