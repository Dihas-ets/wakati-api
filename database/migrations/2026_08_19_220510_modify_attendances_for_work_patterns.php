<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Suppression des clés étrangères existantes
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['schedule_id']);
        });

        // 2. Suppression de l'index unique composite
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'schedule_id']);
        });

        // Rendre schedule_id nullable
        Schema::table('attendances', function (Blueprint $table) {
            $table->unsignedBigInteger('schedule_id')->nullable()->change();
        });

        // 3. Recréation des clés étrangères et ajout des nouvelles colonnes
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('schedule_id')->references('id')->on('schedules')->nullOnDelete();
            $table->foreignId('work_pattern_id')->nullable()->constrained('work_patterns')->nullOnDelete();
            $table->date('date')->nullable();
        });

        // 4. Mise à jour de la date sans backticks (Query Builder compatible PostgreSQL, MySQL et SQLite)
        DB::table('attendances')
            ->whereNull('date')
            ->update([
                'date' => DB::raw('DATE(check_in_time)'),
            ]);

        // 5. Rendre la colonne non-nullable et ajouter l'unicité
        Schema::table('attendances', function (Blueprint $table) {
            $table->date('date')->nullable(false)->change();
            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'date']);
            $table->dropForeign(['work_pattern_id']);
            $table->dropForeign(['schedule_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn(['work_pattern_id', 'date']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->unsignedBigInteger('schedule_id')->nullable(false)->change();
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('schedule_id')->references('id')->on('schedules')->cascadeOnDelete();
            $table->unique(['user_id', 'schedule_id']);
        });
    }
};
