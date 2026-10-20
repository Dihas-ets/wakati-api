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
        Schema::create('time_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->timestamp('break_start');
            $table->timestamp('break_end')->nullable();
            $table->enum('break_type', ['lunch', 'coffee', 'personal'])->default('lunch');
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['date', 'break_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_breaks');
    }
};
