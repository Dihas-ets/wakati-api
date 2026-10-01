<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // On retire la FK avant de toucher à l'index qui la supporte.
        Schema::table('absences', function (Blueprint $table) {
            $table->dropForeign(['schedule_id']);
        });

        Schema::table('absences', function (Blueprint $table) {
            $table->dropUnique(['schedule_id']);
        });

        // ->change() plutôt qu'un ALTER ... MODIFY brut : portable entre
        // MySQL (prod) et SQLite (tests, DB_CONNECTION=sqlite en mémoire).
        Schema::table('absences', function (Blueprint $table) {
            $table->unsignedBigInteger('schedule_id')->nullable()->change();
        });

        Schema::table('absences', function (Blueprint $table) {
            $table->foreign('schedule_id')->references('id')->on('schedules')->nullOnDelete();
            $table->foreignId('work_pattern_id')->nullable()->after('schedule_id')
                ->constrained('work_patterns')->nullOnDelete();
            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('absences', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'date']);
            $table->dropForeign(['work_pattern_id']);
            $table->dropForeign(['schedule_id']);
            $table->dropColumn('work_pattern_id');
        });

        Schema::table('absences', function (Blueprint $table) {
            $table->unsignedBigInteger('schedule_id')->nullable(false)->change();
        });

        Schema::table('absences', function (Blueprint $table) {
            $table->foreign('schedule_id')->references('id')->on('schedules')->cascadeOnDelete();
            $table->unique('schedule_id');
        });
    }
};