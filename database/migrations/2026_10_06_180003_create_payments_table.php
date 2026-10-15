<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('FCFA');
            $table->string('payment_method')->default('mobile_money'); // Carte bancaire, MTN/Moov/Wave Mobile Money, Virement, etc.
            $table->string('transaction_reference')->unique();
            $table->enum('status', ['completed', 'pending', 'failed', 'refunded'])->default('completed');
            $table->timestamp('payment_date');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
