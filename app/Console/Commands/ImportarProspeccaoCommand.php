<?php

namespace App\Console\Commands;

use App\Models\Central\AdminUser;
use App\Services\Platform\Prospeccao\ProspectImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

class ImportarProspeccaoCommand extends Command
{
    protected $signature = 'prospeccao:importar
        {--dir= : Pasta com os JSON extraídos dos PDFs (padrão: database/prospeccao)}
        {--only= : Importa apenas o source_key (ou nome do arquivo) informado}';

    protected $description = 'Importa as listas de prospecção comerciais (JSON extraído dos PDFs) de forma idempotente.';

    public function handle(ProspectImporter $importer): int
    {
        $dir = (string) ($this->option('dir') ?: database_path('prospeccao'));

        if (! File::isDirectory($dir)) {
            $this->error("Pasta não encontrada: {$dir}");

            return self::FAILURE;
        }

        $only = $this->option('only');

        $files = collect(File::files($dir))
            ->filter(fn ($file) => strtolower($file->getExtension()) === 'json')
            ->sortBy(fn ($file) => $file->getFilename())
            ->values();

        if ($files->isEmpty()) {
            $this->error("Nenhum arquivo .json encontrado em {$dir}");

            return self::FAILURE;
        }

        $admin = Auth::guard('admin')->user();
        $admin = $admin instanceof AdminUser ? $admin : null;

        $summary = [];

        foreach ($files as $file) {
            $data = json_decode((string) File::get($file->getPathname()), true);

            if (! is_array($data) || ! isset($data['rows'])) {
                $this->error("JSON inválido, ignorando: {$file->getFilename()}");

                continue;
            }

            if ($only && ($data['source_key'] ?? null) !== $only && $file->getFilename() !== $only) {
                continue;
            }

            $import = $importer->import($data, $admin);

            $summary[] = [
                $import->source_file,
                $import->total,
                $import->imported,
                $import->updated,
                $import->duplicated,
                $import->pending,
                $import->rejected,
            ];

            $this->newLine();
            $this->line("Fonte: {$import->source_file} (source_key={$import->source_key})");

            foreach (($import->report['rejeitados'] ?? []) as $rejeitado) {
                $this->warn("  rejeitado ref={$rejeitado['ref']} {$rejeitado['name']}: {$rejeitado['motivo']}");
            }

            foreach (($import->report['possiveis_duplicados'] ?? []) as $possivel) {
                $this->line("  possível duplicado ref={$possivel['ref']} {$possivel['name']} ({$possivel['motivo']})");
            }

            foreach (($import->report['telefones_compartilhados'] ?? []) as $compartilhado) {
                $this->line("  telefone compartilhado ref={$compartilhado['ref']} {$compartilhado['name']} x {$compartilhado['name_existente']}");
            }
        }

        if ($summary === []) {
            $this->warn('Nenhuma lista correspondeu ao filtro.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['arquivo', 'total', 'novos', 'atualizados', 'duplicados', 'pendentes', 'rejeitados'],
            $summary,
        );

        return self::SUCCESS;
    }
}
