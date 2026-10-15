<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Enrichir companies avec domain_slug si manquant
        if (Schema::hasTable('companies') && !Schema::hasColumn('companies', 'domain_slug')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('domain_slug')->nullable()->after('slug');
            });
            DB::statement('UPDATE companies SET domain_slug = slug WHERE domain_slug IS NULL');
        }

        // 2. Enrichir plans avec max_branches
        if (Schema::hasTable('plans') && !Schema::hasColumn('plans', 'max_branches')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->unsignedInteger('max_branches')->nullable()->after('max_employees');
            });
        }

        // 3. Enrichir subscriptions avec auto_renew
        if (Schema::hasTable('subscriptions') && !Schema::hasColumn('subscriptions', 'auto_renew')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->boolean('auto_renew')->default(false)->after('status');
            });
        }

        // 4. Créer la table branches (Agences)
        if (!Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('name');
                $table->string('code')->nullable();
                $table->text('address')->nullable();
                $table->string('phone')->nullable();
                $table->string('timezone')->default('Africa/Porto-Novo');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'name']);
            });
        }

        // 5. Associer branch_id aux utilisateurs
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'branch_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('company_id')->constrained('branches')->nullOnDelete();
            });
        }

        // 6. Associer branch_id, clock_in_mode et note aux pointages
        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                if (!Schema::hasColumn('attendances', 'branch_id')) {
                    $table->foreignId('branch_id')->nullable()->after('company_id')->constrained('branches')->nullOnDelete();
                }
                if (!Schema::hasColumn('attendances', 'clock_in_mode')) {
                    $table->string('clock_in_mode', 30)->default('qr_code')->nullable()->after('status');
                }
                if (!Schema::hasColumn('attendances', 'note')) {
                    $table->text('note')->nullable()->after('clock_in_mode');
                }
            });
        }

        // 7. Initialiser une agence par défaut pour chaque entreprise existante et rattacher les données existantes
        $companies = DB::table('companies')->get();
        $now = now();

        foreach ($companies as $company) {
            $branchId = DB::table('branches')
                ->where('company_id', $company->id)
                ->where('name', 'Siège Principal')
                ->value('id');

            if (!$branchId) {
                $branchId = DB::table('branches')->insertGetId([
                    'company_id' => $company->id,
                    'name' => 'Siège Principal',
                    'code' => 'HQ',
                    'address' => $company->address ?? 'Siège social',
                    'phone' => $company->phone ?? null,
                    'timezone' => 'Africa/Porto-Novo',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Rattacher les utilisateurs de cette entreprise sans agence (hors super_admin)
            DB::table('users')
                ->where('company_id', $company->id)
                ->whereNull('branch_id')
                ->where('role', '!=', 'super_admin')
                ->update(['branch_id' => $branchId]);

            // Rattacher les pointages de cette entreprise
            DB::table('attendances')
                ->where('company_id', $company->id)
                ->whereNull('branch_id')
                ->update(['branch_id' => $branchId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                if (Schema::hasColumn('attendances', 'note')) {
                    $table->dropColumn('note');
                }
                if (Schema::hasColumn('attendances', 'clock_in_mode')) {
                    $table->dropColumn('clock_in_mode');
                }
                if (Schema::hasColumn('attendances', 'branch_id')) {
                    $table->dropForeign(['branch_id']);
                    $table->dropColumn('branch_id');
                }
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'branch_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        Schema::dropIfExists('branches');

        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'auto_renew')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropColumn('auto_renew');
            });
        }

        if (Schema::hasTable('plans') && Schema::hasColumn('plans', 'max_branches')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->dropColumn('max_branches');
            });
        }

        if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'domain_slug')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('domain_slug');
            });
        }
    }
};
