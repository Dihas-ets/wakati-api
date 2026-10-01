<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('card_uid')->nullable()->unique()->after('role');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->string('source')->default('online')->after('ip_address'); // online | offline_sync
            $table->string('sync_uuid')->nullable()->unique()->after('source'); // idempotence
        });

        Schema::create('badge_pairings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_pairings');

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['source', 'sync_uuid']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('card_uid');
        });
    }
};