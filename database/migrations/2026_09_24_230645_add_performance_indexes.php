<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Indexation pour les pointages
        Schema::table('attendances', function (Blueprint $table) {
            // Index composite pour optimiser l'historique RH par employé ordonné par heure
            $table->index(['user_id', 'check_in_time'], 'attendances_user_checkin_idx');
            $table->index('date', 'attendances_date_idx');
        });

        // 2. Table pour les heartbeats des bornes de pointage
        if (! Schema::hasTable('kiosk_heartbeats')) {
            Schema::create('kiosk_heartbeats', function (Blueprint $table) {
                $table->id();
                $table->string('kiosk_id', 100)->index();
                $table->string('ip_address', 45);
                $table->string('status', 30)->default('online');
                $table->integer('battery_level')->nullable();
                $table->string('app_version', 50)->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('last_seen_at')->index();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendances_user_checkin_idx');
            $table->dropIndex('attendances_date_idx');
        });

        Schema::dropIfExists('kiosk_heartbeats');
    }
};
