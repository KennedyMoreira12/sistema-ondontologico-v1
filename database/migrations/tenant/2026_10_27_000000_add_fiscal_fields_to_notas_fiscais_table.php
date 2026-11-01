<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos que o módulo fiscal precisa além do esqueleto original de
 * `notas_fiscais`: dados do provedor, do tomador, valores destacados e
 * controle de tentativas.
 *
 * Cada coluna é adicionada só se ainda não existir, porque a tabela nasceu
 * numa migration anterior e pode ter sido ajustada à mão em algum tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            if (! Schema::hasColumn('notas_fiscais', 'ambiente')) {
                $table->string('ambiente', 20)->default('homologacao')->after('tipo');
            }

            if (! Schema::hasColumn('notas_fiscais', 'provider')) {
                $table->string('provider', 30)->nullable()->after('ambiente');
            }

            if (! Schema::hasColumn('notas_fiscais', 'protocolo')) {
                $table->string('protocolo', 60)->nullable()->after('chave');
            }

            if (! Schema::hasColumn('notas_fiscais', 'motivo_rejeicao')) {
                $table->text('motivo_rejeicao')->nullable()->after('motivo_cancelamento');
            }

            if (! Schema::hasColumn('notas_fiscais', 'tentativas')) {
                $table->unsignedSmallInteger('tentativas')->default(0)->after('motivo_rejeicao');
            }

            if (! Schema::hasColumn('notas_fiscais', 'ultima_tentativa_em')) {
                $table->timestamp('ultima_tentativa_em')->nullable()->after('tentativas');
            }

            if (! Schema::hasColumn('notas_fiscais', 'valor_desconto')) {
                $table->decimal('valor_desconto', 10, 2)->default(0)->after('valor_total');
            }

            if (! Schema::hasColumn('notas_fiscais', 'valor_liquido')) {
                $table->decimal('valor_liquido', 10, 2)->nullable()->after('valor_desconto');
            }

            if (! Schema::hasColumn('notas_fiscais', 'valor_iss')) {
                $table->decimal('valor_iss', 10, 2)->default(0)->after('valor_icms');
            }

            if (! Schema::hasColumn('notas_fiscais', 'aliquota_iss')) {
                $table->decimal('aliquota_iss', 5, 2)->nullable()->after('valor_iss');
            }

            if (! Schema::hasColumn('notas_fiscais', 'codigo_servico')) {
                $table->string('codigo_servico', 20)->nullable()->after('aliquota_iss');
            }

            if (! Schema::hasColumn('notas_fiscais', 'descricao_servico')) {
                $table->string('descricao_servico')->nullable()->after('codigo_servico');
            }

            if (! Schema::hasColumn('notas_fiscais', 'tomador_nome')) {
                $table->string('tomador_nome')->nullable()->after('descricao_servico');
            }

            if (! Schema::hasColumn('notas_fiscais', 'tomador_documento')) {
                $table->string('tomador_documento', 20)->nullable()->after('tomador_nome');
            }

            if (! Schema::hasColumn('notas_fiscais', 'tomador_email')) {
                $table->string('tomador_email', 191)->nullable()->after('tomador_documento');
            }

            if (! Schema::hasColumn('notas_fiscais', 'tomador_endereco')) {
                $table->string('tomador_endereco')->nullable()->after('tomador_email');
            }

            if (! Schema::hasColumn('notas_fiscais', 'tomador_cidade')) {
                $table->string('tomador_cidade', 120)->nullable()->after('tomador_endereco');
            }

            if (! Schema::hasColumn('notas_fiscais', 'tomador_uf')) {
                $table->string('tomador_uf', 2)->nullable()->after('tomador_cidade');
            }

            if (! Schema::hasColumn('notas_fiscais', 'tomador_cep')) {
                $table->string('tomador_cep', 10)->nullable()->after('tomador_uf');
            }
        });

        if (! $this->temIndice('notas_fiscais', 'notas_fiscais_status_index')) {
            Schema::table('notas_fiscais', function (Blueprint $table) {
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $colunas = [
                'ambiente', 'provider', 'protocolo', 'motivo_rejeicao', 'tentativas',
                'ultima_tentativa_em', 'valor_desconto', 'valor_liquido', 'valor_iss',
                'aliquota_iss', 'codigo_servico', 'descricao_servico',
                'tomador_nome', 'tomador_documento', 'tomador_email',
                'tomador_endereco', 'tomador_cidade', 'tomador_uf', 'tomador_cep',
            ];

            foreach ($colunas as $coluna) {
                if (Schema::hasColumn('notas_fiscais', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }

    private function temIndice(string $tabela, string $indice): bool
    {
        foreach (Schema::getIndexes($tabela) as $existente) {
            if (($existente['name'] ?? null) === $indice) {
                return true;
            }
        }

        return false;
    }
};
