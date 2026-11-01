<?php

namespace Database\Seeders;

use App\Models\Central\ProspectMessageTemplate;
use Illuminate\Database\Seeder;

/**
 * Cria os modelos comerciais padrão. `firstOrCreate` de propósito: se o
 * superadministrador já editou um modelo, rodar de novo não sobrescreve o texto.
 */
class ProspectTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('prospeccao.templates') as $key => $template) {
            ProspectMessageTemplate::query()->firstOrCreate(
                ['key' => $key],
                [
                    'name' => $template['name'],
                    'body' => $template['body'],
                    'is_active' => true,
                    'sort_order' => $template['sort_order'] ?? 0,
                ],
            );
        }
    }
}
