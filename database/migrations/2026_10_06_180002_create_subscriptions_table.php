<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->timestamp('start_date'); // Date de début
            $table->timestamp('end_date'); // Date de fin de l'abonnement
            $table->string('status', 30)->default('active'); // active, trial, expired, cancelled
            $table->boolean('is_trial')->default(false);
            $table->decimal('price_paid', 12, 2)->default(0);
            $table->string('currency', 10)->default('FCFA');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
