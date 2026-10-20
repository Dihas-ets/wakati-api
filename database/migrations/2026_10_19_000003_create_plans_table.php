<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 12, 2);
            $table->string('currency')->default('XOF');
            $table->integer('duration');
            $table->enum('duration_unit', ['day', 'month', 'year']);
            $table->boolean('is_trial')->default(false);
            $table->integer('trial_period_days')->default(0);
            $table->integer('max_employees')->nullable();
            $table->json('features')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
