<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_url')->nullable();
            $table->string('registration_number')->nullable(); // Références de l'entreprise (RCCM, IFU, SIRET)
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('website')->nullable();
            $table->enum('access_type', ['subscription', 'free_grant'])->default('subscription'); // Paramétrage super_admin: Accès gratuit ou conditionné à un abonnement
            $table->enum('status', ['active', 'trial', 'expired', 'suspended'])->default('active');
            $table->boolean('has_used_trial')->default(false); // Le plan gratuit valable une seule fois
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
