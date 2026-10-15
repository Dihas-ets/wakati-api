<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ajouter company_id et champs d'inscription à users
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone')->nullable()->after('email');
        });

        // 2. Ajouter company_id aux autres tables clés
        $tenantTables = [
            'departments',
            'company_settings',
            'schedules',
            'attendances',
            'absences',
            'work_patterns',
            'leaves',
            'explanation_requests',
            'activity_logs',
            'kiosk_heartbeats',
        ];

        foreach ($tenantTables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->cascadeOnDelete();
                });
            }
        }

        // 3. Créer une entreprise par défaut et y associer les données existantes
        $now = now();
        $defaultCompanyId = DB::table('companies')->insertGetId([
            'name' => 'Wakati Entreprise Pilote',
            'slug' => 'wakati-pilote',
            'registration_number' => 'BJ-COT-01-2026-A10',
            'email' => 'contact@wakati-pilote.test',
            'phone' => '+229 97 00 00 00',
            'access_type' => 'free_grant', // Accès gratuit pour l'entreprise pilote
            'status' => 'active',
            'has_used_trial' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Rattacher les utilisateurs existants à cette entreprise
        DB::table('users')->whereNull('company_id')->update([
            'company_id' => $defaultCompanyId,
        ]);

        foreach ($tenantTables as $tableName) {
            if (Schema::hasTable($tableName)) {
                DB::table($tableName)->whereNull('company_id')->update([
                    'company_id' => $defaultCompanyId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $tenantTables = [
            'kiosk_heartbeats',
            'activity_logs',
            'explanation_requests',
            'leaves',
            'work_patterns',
            'absences',
            'attendances',
            'schedules',
            'company_settings',
            'departments',
        ];

        foreach ($tenantTables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'company_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['company_id']);
                    $table->dropColumn('company_id');
                });
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn(['company_id', 'first_name', 'last_name', 'phone']);
        });
    }
};
