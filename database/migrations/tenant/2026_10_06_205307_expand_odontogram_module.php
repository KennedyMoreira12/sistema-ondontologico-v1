<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('odontograms', function (Blueprint $table) {
            $table->string('denticao', 20)->default('permanente');
            $table->string('notacao', 10)->default('FDI');
            $table->text('notas')->nullable();
        });

        Schema::table('odontogram_teeth', function (Blueprint $table) {
            $table->string('status', 20)->default('presente');
        });

        $this->migrarCondicoesLegadas();

        Schema::disableForeignKeyConstraints();
        try {
            Schema::table('odontogram_teeth', function (Blueprint $table) {
                $table->dropForeign(['tooth_condition_id']);
                $table->dropColumn(['tooth_condition_id', 'condicao']);
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        Schema::table('odontogram_teeth', function (Blueprint $table) {
            $table->foreignId('tooth_condition_id')->nullable()->constrained()->nullOnDelete();
            $table->string('condicao')->nullable();
        });

        Schema::table('odontograms', function (Blueprint $table) {
            $table->dropColumn(['denticao', 'notacao', 'notas']);
        });

        Schema::table('odontogram_teeth', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    /**
     * As marcas antigas (1 condição por dente, sem superfície) viram condições
     * da camada "existente" aplicadas ao dente inteiro.
     */
    private function migrarCondicoesLegadas(): void
    {
        DB::table('odontogram_tooth_conditions')->insertUsing(
            [
                'odontogram_tooth_id',
                'tooth_condition_id',
                'camada',
                'profissional_id',
                'descricao',
                'created_at',
                'updated_at',
            ],
            DB::table('odontogram_teeth')
                ->select([
                    'id',
                    'tooth_condition_id',
                    DB::raw("'existente' AS camada"),
                    'profissional_id',
                    DB::raw('NULL AS descricao'),
                    DB::raw('CURRENT_TIMESTAMP AS created_at'),
                    DB::raw('CURRENT_TIMESTAMP AS updated_at'),
                ])
                ->whereNotNull('tooth_condition_id')
        );
    }
};
