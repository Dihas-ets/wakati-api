<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var string[]
     */
    protected array $tables = [
        'users',
        'attendances',
        'meetings',
        'kanban_tasks',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'company_id')) {
                    $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
                }
                if (! Schema::hasColumn($tableName, 'agency_id')) {
                    $table->foreignId('agency_id')->nullable()->constrained('agencies')->cascadeOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'agency_id')) {
                    $table->dropConstrainedForeignId('agency_id');
                }
                if (Schema::hasColumn($tableName, 'company_id')) {
                    $table->dropConstrainedForeignId('company_id');
                }
            });
        }
    }
};
