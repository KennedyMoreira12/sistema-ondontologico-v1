<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estrutura fiscal por clínica.
 *
 * IDEMPOTENTE: cada tabela só é criada se ainda não existir. A emissão real
 * de documentos fiscais continua desligada - esta migration apenas garante o
 * schema que o módulo fiscal usa quando for habilitado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fiscal_companies')) {
            Schema::create('fiscal_companies', function (Blueprint $table) {
                $table->id();
                $table->string('cnpj', 14)->nullable()->index();
                $table->string('razao_social')->nullable();
                $table->string('nome_fantasia')->nullable();
                $table->string('inscricao_municipal')->nullable();
                $table->string('inscricao_estadual')->nullable();
                $table->string('regime_tributario')->nullable();
                $table->string('codigo_cnae', 10)->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('postal_code', 10)->nullable();
                $table->string('address')->nullable();
                $table->string('number', 20)->nullable();
                $table->string('complement')->nullable();
                $table->string('district')->nullable();
                $table->string('city')->nullable();
                $table->string('state', 2)->nullable();
                $table->string('external_id')->nullable()->unique()->comment('Id da clínica no sistema (central), usado como external_id da empresa SendNF.');
                $table->string('sendnf_company_id')->nullable()->index();
                $table->string('status')->default('not_configured');
                $table->timestamp('last_check_at')->nullable();
                $table->json('readiness_payload')->nullable();
                $table->boolean('enabled')->default(false);
                $table->timestamp('enabled_at')->nullable();
                $table->unsignedBigInteger('enabled_by_user_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nfse')) {
            Schema::create('nfse', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fiscal_company_id')->constrained('fiscal_companies')->cascadeOnDelete();
                $table->string('numero')->nullable()->index();
                $table->string('serie')->nullable();
                $table->string('codigo_verificacao')->nullable();
                $table->string('chave_acesso')->nullable()->index();
                $table->string('status')->default('draft')->index();
                $table->string('tomador_tipo', 20)->nullable();
                $table->string('tomador_documento')->nullable();
                $table->string('tomador_nome')->nullable();
                $table->string('servico_codigo')->nullable();
                $table->text('servico_descricao')->nullable();
                $table->decimal('valor_servico', 12, 2)->nullable();
                $table->decimal('valor_iss', 12, 2)->nullable();
                $table->decimal('aliquota', 6, 4)->nullable();
                $table->date('competencia')->nullable();
                $table->timestamp('data_emissao')->nullable();
                $table->string('sendnf_id')->nullable()->index();
                $table->string('protocolo')->nullable();
                $table->string('idempotency_key')->unique();
                $table->unsignedBigInteger('issued_by_user_id')->nullable();
                $table->timestamp('authorized_at')->nullable();
                $table->text('rejected_reason')->nullable();
                $table->string('request_hash')->nullable()->index();
                $table->json('response_sanitized')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('nfse_events')) {
            Schema::create('nfse_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('nfse_id')->constrained('nfse')->cascadeOnDelete();
                $table->string('event_type');
                $table->string('source');
                $table->string('event_id')->nullable()->index();
                $table->json('payload_sanitized')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['nfse_id', 'event_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nfse_events');
        Schema::dropIfExists('nfse');
        Schema::dropIfExists('fiscal_companies');
    }
};
