<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->constrained('prospects')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('prospect_message_templates')->nullOnDelete();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();

            $table->string('channel', 20)->default('whatsapp');
            $table->string('status_before', 30)->nullable();
            $table->string('status_after', 30)->nullable();
            $table->string('result')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('interacted_at')->nullable();
            $table->timestamps();

            $table->index(['prospect_id', 'interacted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_interactions');
    }
};
