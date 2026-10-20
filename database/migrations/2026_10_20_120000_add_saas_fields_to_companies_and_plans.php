<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enrichir la table companies
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'access_type')) {
                $table->string('access_type', 30)->default('subscription')->after('rccm_ifu'); // subscription | free_grant
            }
            if (! Schema::hasColumn('companies', 'status')) {
                $table->string('status', 30)->default('active')->after('access_type'); // active | trial | expired | suspended
            }
            if (! Schema::hasColumn('companies', 'has_used_trial')) {
                $table->boolean('has_used_trial')->default(false)->after('status');
            }
            if (! Schema::hasColumn('companies', 'address')) {
                $table->string('address')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('companies', 'email')) {
                $table->string('email')->nullable()->after('address');
            }
            if (! Schema::hasColumn('companies', 'website')) {
                $table->string('website')->nullable()->after('email');
            }
            if (! Schema::hasColumn('companies', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }
        });

        // Enrichir la table plans
        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }
            if (! Schema::hasColumn('plans', 'description')) {
                $table->text('description')->nullable()->after('slug');
            }
            if (! Schema::hasColumn('plans', 'duration_type')) {
                $table->string('duration_type', 30)->default('recurring')->after('duration_unit'); // recurring | fixed | trial
            }
            if (! Schema::hasColumn('plans', 'is_popular')) {
                $table->boolean('is_popular')->default(false)->after('max_employees');
            }
            if (! Schema::hasColumn('plans', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_popular');
            }
            if (! Schema::hasColumn('plans', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('is_active');
            }
        });

        // Enrichir la table subscriptions
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'is_trial')) {
                $table->boolean('is_trial')->default(false)->after('status');
            }
            if (! Schema::hasColumn('subscriptions', 'notes')) {
                $table->text('notes')->nullable()->after('is_trial');
            }
            if (! Schema::hasColumn('subscriptions', 'price_paid')) {
                $table->decimal('price_paid', 12, 2)->default(0)->after('notes');
            }
            if (! Schema::hasColumn('subscriptions', 'currency')) {
                $table->string('currency', 10)->default('XOF')->after('price_paid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $cols = array_filter(
                ['slug', 'address', 'email', 'website', 'access_type', 'status', 'has_used_trial'],
                fn ($c) => Schema::hasColumn('companies', $c)
            );
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('plans', function (Blueprint $table) {
            $cols = array_filter(
                ['slug', 'description', 'duration_type', 'is_popular', 'is_active', 'sort_order'],
                fn ($c) => Schema::hasColumn('plans', $c)
            );
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $cols = array_filter(
                ['is_trial', 'notes', 'price_paid', 'currency'],
                fn ($c) => Schema::hasColumn('subscriptions', $c)
            );
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
