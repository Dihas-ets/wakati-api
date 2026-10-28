<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            if (! Schema::hasColumn('departments', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->cascadeOnDelete();
            }
            if (! Schema::hasColumn('departments', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('departments', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }
        });
    }
};
