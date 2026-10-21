<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->nullable()->constrained('prospect_imports')->nullOnDelete();

            $table->string('name');
            $table->string('type')->nullable();
            $table->string('category')->nullable();
            $table->string('specialty')->nullable();

            $table->string('phone_original')->nullable();
            $table->string('phone_alt')->nullable();
            $table->string('phone_normalized', 20)->nullable()->index();
            $table->string('phone_status', 20)->default('unconfirmed');

            $table->string('city')->default('Januária');
            $table->string('state', 2)->default('MG');
            $table->string('address')->nullable();

            $table->string('priority', 10)->default('media');
            $table->string('source')->nullable();
            $table->string('source_ref')->nullable();
            $table->string('status', 30)->default('nao_contatado')->index();

            $table->dateTime('last_contact_at')->nullable();
            $table->date('next_follow_up_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->text('disinterest_reason')->nullable();

            $table->boolean('opted_out')->default(false)->index();
            $table->dateTime('opted_out_at')->nullable();
            $table->dateTime('validated_at')->nullable();

            $table->foreignId('converted_clinic_id')->nullable()->constrained('clinics')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['source', 'source_ref']);
            $table->index('name');
            $table->index('priority');
            $table->index('city');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};
