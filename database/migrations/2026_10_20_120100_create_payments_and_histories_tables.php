<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('currency', 10)->default('XOF');
                $table->string('payment_method')->default('Mobile Money');
                $table->string('transaction_reference')->nullable();
                $table->string('status', 30)->default('completed'); // completed | pending | failed | refunded
                $table->timestamp('payment_date')->useCurrent();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('company_histories')) {
            Schema::create('company_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action');
                $table->text('description');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_histories');
        Schema::dropIfExists('payments');
    }
};
