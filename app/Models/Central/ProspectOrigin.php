<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Procedência de um contato: cada PDF (e cada linha) que apontou para ele.
 *
 * Um contato pode aparecer em mais de uma lista; em vez de fundir/duplicar
 * silenciosamente, cada aparição vira uma linha aqui. `raw` guarda a linha
 * original extraída, para auditoria e reprocessamento.
 */
#[Fillable(['prospect_id', 'source', 'source_file', 'source_ref', 'raw'])]
class ProspectOrigin extends CentralModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw' => 'array',
        ];
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }
}
