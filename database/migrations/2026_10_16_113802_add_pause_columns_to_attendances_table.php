<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('attendances', 'pause_start')) {
                $table->timestamp('pause_start')->nullable()->after('check_in_time');
            }
            if (!Schema::hasColumn('attendances', 'pause_end')) {
                $table->timestamp('pause_end')->nullable()->after('pause_start');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('attendances', 'pause_end')) {
                $columns[] = 'pause_end';
            }
            if (Schema::hasColumn('attendances', 'pause_start')) {
                $columns[] = 'pause_start';
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
