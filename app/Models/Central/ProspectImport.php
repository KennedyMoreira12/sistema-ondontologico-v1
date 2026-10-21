<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Registro de uma execução de importação, com o relatório de resultado.
 */
#[Fillable([
    'source_file', 'source_key', 'total', 'imported', 'updated',
    'duplicated', 'pending', 'rejected', 'report', 'admin_user_id',
])]
class ProspectImport extends CentralModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'imported' => 'integer',
            'updated' => 'integer',
            'duplicated' => 'integer',
            'pending' => 'integer',
            'rejected' => 'integer',
            'report' => 'array',
        ];
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(Prospect::class, 'import_id');
    }
}
