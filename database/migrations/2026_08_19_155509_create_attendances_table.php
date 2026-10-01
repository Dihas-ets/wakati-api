<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('schedule_id')->constrained()->onDelete('cascade');
            $table->timestamp('check_in_time');
            $table->enum('status', ['on_time', 'late'])->default('on_time');
            $table->string('ip_address');
            $table->timestamps();

            $table->unique(['user_id', 'schedule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};