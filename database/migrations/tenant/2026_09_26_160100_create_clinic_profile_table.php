<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil operacional da clínica — razão social, CNPJ, contatos e endereço.
 *
 * Fica no banco de cada clínica, e não no `clinics` central, porque é dado
 * operacional da clínica e some junto com o tenant. O central guarda apenas o
 * mínimo para rotear e faturar: nome, CNPJ do cobrança, e-mail, status,
 * timezone e marca.
 *
 * Tabela de linha única: o id é sempre 1. A FK para a clínica central não
 * existe de propósito, porque `users.clinic_id` no banco central já é essa
 * mesma ligação e é a única fonte de verdade de tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_profile', function (Blueprint $table) {
            $table->id();

            $table->string('legal_name')->nullable();
            $table->string('document')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();

            $table->string('postal_code')->nullable();
            $table->string('address')->nullable();
            $table->string('number')->nullable();
            $table->string('complement')->nullable();
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();

            $table->string('logo_path')->nullable();
            $table->string('primary_color')->default('#2563eb');
            $table->string('secondary_color')->default('#1e40af');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_profile');
    }
};
