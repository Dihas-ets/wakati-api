<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'secondary_logo')) {
                $table->longText('secondary_logo')->nullable()->after('logo');
            }
            if (! Schema::hasColumn('companies', 'primary_color')) {
                $table->string('primary_color', 20)->default('#795FFC')->after('secondary_logo');
            }
            if (! Schema::hasColumn('companies', 'secondary_color')) {
                $table->string('secondary_color', 20)->default('#0F172A')->after('primary_color');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'secondary_color')) {
                $table->dropColumn('secondary_color');
            }
            if (Schema::hasColumn('companies', 'primary_color')) {
                $table->dropColumn('primary_color');
            }
            if (Schema::hasColumn('companies', 'secondary_logo')) {
                $table->dropColumn('secondary_logo');
            }
        });
    }
};
