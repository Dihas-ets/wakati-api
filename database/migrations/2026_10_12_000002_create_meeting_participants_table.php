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
        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('attendance_status', ['invited', 'present', 'absent', 'excused'])->default('invited');
            $table->timestamp('checked_in_at')->nullable();
            $table->enum('checkin_method', ['qr_code', 'rfid', 'manual', 'mobile_app', 'web'])->nullable();
            $table->boolean('convocation_sent')->default(false);
            $table->boolean('report_sent')->default(false);
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id']);
            $table->index(['meeting_id', 'attendance_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_participants');
    }
};
