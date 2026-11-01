<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dados fiscais do emitente no perfil da clínica.
 *
 * Todos são nullable de propósito: a clínica só precisa preencher quando for
 * emitir nota. O módulo fiscal valida o mínimo na hora da emissão e mostra o
 * que falta em Configurações da clínica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_profile', function (Blueprint $table) {
            if (! Schema::hasColumn('clinic_profile', 'email')) {
                $table->string('email', 191)->nullable()->after('whatsapp');
            }

            if (! Schema::hasColumn('clinic_profile', 'state_registration')) {
                $table->string('state_registration', 30)->nullable()->after('email');
            }

            if (! Schema::hasColumn('clinic_profile', 'municipal_registration')) {
                $table->string('municipal_registration', 30)->nullable()->after('state_registration');
            }

            if (! Schema::hasColumn('clinic_profile', 'cnae')) {
                $table->string('cnae', 10)->nullable()->after('municipal_registration');
            }

            if (! Schema::hasColumn('clinic_profile', 'city_ibge_code')) {
                $table->string('city_ibge_code', 7)->nullable()->after('state');
            }

            if (! Schema::hasColumn('clinic_profile', 'tax_regime')) {
                $table->string('tax_regime', 30)->nullable()->after('city_ibge_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clinic_profile', function (Blueprint $table) {
            foreach (['email', 'state_registration', 'municipal_registration', 'cnae', 'city_ibge_code', 'tax_regime'] as $coluna) {
                if (Schema::hasColumn('clinic_profile', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
