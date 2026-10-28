<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('agency_id')->nullable()->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('month_year', 7); // Format: YYYY-MM
            $table->string('month', 7)->nullable(); // Alias/compat
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2)->default(0);
            $table->enum('status_rh', ['draft', 'validated_by_rh'])->default('draft');
            $table->string('rh_status')->default('draft'); // Alias/compat
            $table->timestamp('rh_validated_at')->nullable();
            $table->foreignId('rh_validator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_ref')->nullable();
            $table->string('payment_reference')->nullable(); // Alias/compat
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'month_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
