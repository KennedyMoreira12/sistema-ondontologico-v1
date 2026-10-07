<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odontogram_tooth_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('odontogram_tooth_id')->constrained('odontogram_teeth')->cascadeOnDelete();
            $table->foreignId('tooth_condition_id')->nullable()->constrained()->nullOnDelete();
            $table->string('superficie', 20)->nullable();
            $table->string('camada', 20)->default('existente');
            $table->text('descricao')->nullable();
            $table->foreignId('profissional_id')->nullable()->constrained('professionals')->nullOnDelete();
            $table->timestamps();

            $table->index(['odontogram_tooth_id', 'camada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odontogram_tooth_conditions');
    }
};
