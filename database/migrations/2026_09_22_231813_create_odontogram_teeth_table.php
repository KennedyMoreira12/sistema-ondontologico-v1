<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odontogram_teeth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('odontogram_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tooth_condition_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('dente');
            $table->string('condicao')->nullable();
            $table->text('observacao')->nullable();
            $table->foreignId('profissional_id')->nullable()->constrained('professionals')->nullOnDelete();
            $table->timestamps();

            $table->unique(['odontogram_id', 'dente']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odontogram_teeth');
    }
};