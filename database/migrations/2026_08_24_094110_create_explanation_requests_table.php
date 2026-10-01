<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('explanation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('schedules')->nullOnDelete();
            $table->foreignId('work_pattern_id')->nullable()->constrained('work_patterns')->nullOnDelete();
            $table->date('date');
            $table->timestamp('sent_at');
            $table->text('response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']); // une seule demande par employé par jour
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('explanation_requests');
    }
};