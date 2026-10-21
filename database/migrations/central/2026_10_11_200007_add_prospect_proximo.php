<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->string('proximo', 30)->nullable()->after('next_follow_up_at');
            $table->boolean('bot_pause')->default(false)->after('proximo');
            $table->dateTime('bot_paused_at')->nullable()->after('bot_pause');
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn('proximo');
            $table->dropColumn('bot_pause');
            $table->dropColumn('bot_paused_at');
        });
    }
};
