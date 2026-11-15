<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assinatura manuscrita eletrônica de documentos da clínica.
 *
 * Cada linha é um evento de assinatura imutável: guarda o desenho (PNG em
 * base64), o hash do desenho, o hash do conteúdo assinado e as evidências
 * (quem assinou, quando, de qual IP). `superseded_at` marca a assinatura que
 * deixou de valer porque o termo mudou — a linha continua existindo, porque
 * histórico de assinatura não se apaga.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_signatures', function (Blueprint $table) {
            $table->id();

            /**
             * Sem cascade: apagar um paciente não pode levar junto a prova de
             * que ele assinou. O paciente usa soft delete, então o vínculo
             * permanece mesmo depois da exclusão lógica.
             */
            $table->foreignId('paciente_id')->constrained('pacientes');

            /**
             * `users` mora no banco central: referência sem FK, mesmo padrão
             * de `contracts.user_id`.
             */
            $table->foreignId('user_id')->nullable()->index();

            /**
             * Documento assinado. Polimórfico para servir tanto ao termo
             * (Contract) quanto à presença de uma sessão (EvolucaoSessao).
             */
            $table->string('signable_type');
            $table->unsignedBigInteger('signable_id');

            $table->string('document_type', 60);
            $table->string('document_title');
            $table->unsignedInteger('term_version')->default(1);

            /**
             * Cópia do texto assinado + hash. A cópia é o que permite provar
             * depois que o PDF exibido é o mesmo que o paciente leu, mesmo que
             * o termo tenha sido reeditado.
             */
            $table->longText('conteudo_assinado')->nullable();
            $table->string('content_hash', 64);

            $table->longText('signature_data');
            $table->string('signature_hash', 64);

            $table->dateTime('signed_at');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('superseded_at')->nullable();
            $table->timestamps();

            $table->index(['signable_type', 'signable_id']);
            $table->index(['paciente_id', 'document_type']);
            $table->index(['signable_type', 'signable_id', 'superseded_at'], 'document_signatures_ativas_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_signatures');
    }
};
