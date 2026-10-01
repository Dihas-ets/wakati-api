<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            // pending | approved | rejected — les congés créés directement par
            // RH/Directeur restent 'approved' d'emblée (comportement existant
            // préservé), seules les demandes créées par un employé démarrent
            // en 'pending'.
            $table->string('status')->default('approved')->after('notes');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete()->after('status');
            $table->timestamp('decided_at')->nullable()->after('decided_by');
        });
    }

    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropForeign(['decided_by']);
            $table->dropColumn(['status', 'decided_by', 'decided_at']);
        });
    }
};