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
        Schema::table('attendances', function (Blueprint $table) {
            $table->boolean('is_on_break')->default(false)->after('check_out_time');
            $table->timestamp('current_break_start')->nullable()->after('is_on_break');
            $table->unsignedInteger('total_break_minutes')->default(0)->after('current_break_start');
            $table->json('break_history')->nullable()->after('total_break_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'is_on_break',
                'current_break_start',
                'total_break_minutes',
                'break_history',
            ]);
        });
    }
};
