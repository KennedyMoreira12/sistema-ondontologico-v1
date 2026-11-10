<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->text('frase_institucional')->nullable()->after('secondary_color');
            // Relacionamento lógico com a tabela professionals (banco tenant).
            // Não usa foreignId porque clinics vive no banco central e professionals no banco do tenant.
            $table->unsignedBigInteger('profissional_principal_id')->nullable()->after('frase_institucional');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn(['frase_institucional', 'profissional_principal_id']);
        });
    }
};
