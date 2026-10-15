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
            $table->string('name'); // Nom du plan
            $table->string('slug')->unique();
            $table->text('description')->nullable(); // Description du plan
            $table->decimal('price', 12, 2)->default(0); // Prix
            $table->string('currency', 10)->default('FCFA'); // Unités du prix (FCFA, XOF, EUR, USD)
            $table->integer('duration')->default(1); // Durée du plan (ex: 1, 3, 12)
            $table->string('duration_unit', 20)->default('month'); // Unité de la durée: day, month, year
            $table->string('duration_type', 30)->default('recurring'); // Type de durée: recurring, fixed, trial
            $table->boolean('is_trial')->default(false); // Préciser les plans d'essai
            $table->integer('trial_period_days')->default(0); // Période d'essai (en jours)
            $table->json('features')->nullable(); // Features (liste de fonctionnalités)
            $table->integer('max_employees')->nullable(); // Quota max d'employés (null = illimité)
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
