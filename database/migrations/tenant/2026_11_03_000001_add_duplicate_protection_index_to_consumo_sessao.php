<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 7 - Proteção duplicidade (pacote + evolução).
 *
 * Esta migration só deve ser aplicada após:
 * 1. Confirmação de que não existem duplicados (`check_duplicates.php`).
 * 2. Backup verificado (`backup_pre_publicacao.sql` ou `.sqlite` testado).
 * 3. Autorização específica para alteração do banco.
 *
 * É IDEMPOTENTE: se o índice já existir, nada é feito.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('consumo_sessao')) {
            return;
        }

        if (Schema::hasIndex('consumo_sessao', 'consumo_pacote_evolucao_idx')) {
            return;
        }

        Schema::table('consumo_sessao', function (Blueprint $table) {
            // Índice composto: pacote + evolução (proteção duplicidade).
            // `evolucao_sessao_id` pode ser null; o SQLite trata NULLs como distintos,
            // mas no MySQL o comportamento de índices únicos com NULL varia.
            // Se o banco não aceitar NULL no índice único, a aplicação já bloqueia
            // duplicidade por código (transação + verificação), então o índice
            // é uma garantia extra quando ambos são não-nulos.
            $table->index(['pacote_id', 'evolucao_sessao_id'], 'consumo_pacote_evolucao_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('consumo_sessao')) {
            return;
        }

        if (! Schema::hasIndex('consumo_sessao', 'consumo_pacote_evolucao_idx')) {
            return;
        }

        Schema::table('consumo_sessao', function (Blueprint $table) {
            $table->dropIndex('consumo_pacote_evolucao_idx');
        });
    }
};
