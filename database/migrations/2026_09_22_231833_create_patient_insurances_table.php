<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_insurances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_plan_id')->constrained()->cascadeOnDelete();
            $table->string('numero_carteirinha', 50)->nullable();
            $table->date('validade')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['paciente_id', 'insurance_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_insurances');
    }
};