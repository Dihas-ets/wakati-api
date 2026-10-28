<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('company_settings')) {
            Schema::create('company_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete()->unique();
                $table->string('smtp_host')->nullable();
                $table->integer('smtp_port')->nullable()->default(587);
                $table->string('smtp_username')->nullable();
                $table->text('smtp_password')->nullable();
                $table->string('smtp_encryption')->nullable()->default('tls');
                $table->string('from_address')->nullable();
                $table->string('from_name')->nullable();
                $table->string('reply_to_address')->nullable();
                $table->string('reply_to_name')->nullable();
                $table->string('key')->nullable()->index();
                $table->json('value')->nullable();
                $table->string('description')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('company_settings', function (Blueprint $table) {
                if (! Schema::hasColumn('company_settings', 'company_id')) {
                    $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('company_settings', 'smtp_host')) {
                    $table->string('smtp_host')->nullable();
                }
                if (! Schema::hasColumn('company_settings', 'smtp_port')) {
                    $table->integer('smtp_port')->nullable()->default(587);
                }
                if (! Schema::hasColumn('company_settings', 'smtp_username')) {
                    $table->string('smtp_username')->nullable();
                }
                if (! Schema::hasColumn('company_settings', 'smtp_password')) {
                    $table->text('smtp_password')->nullable();
                }
                if (! Schema::hasColumn('company_settings', 'smtp_encryption')) {
                    $table->string('smtp_encryption')->nullable()->default('tls');
                }
                if (! Schema::hasColumn('company_settings', 'from_address')) {
                    $table->string('from_address')->nullable();
                }
                if (! Schema::hasColumn('company_settings', 'from_name')) {
                    $table->string('from_name')->nullable();
                }
                if (! Schema::hasColumn('company_settings', 'reply_to_address')) {
                    $table->string('reply_to_address')->nullable();
                }
                if (! Schema::hasColumn('company_settings', 'reply_to_name')) {
                    $table->string('reply_to_name')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // No-op for SQLite rollback compatibility on existing company_settings
    }
};
