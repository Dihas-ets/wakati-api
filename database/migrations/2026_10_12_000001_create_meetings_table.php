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
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('date');
            $table->time('time');
            $table->string('location');
            $table->foreignId('redactor_id')->constrained('users')->cascadeOnDelete();
            $table->text('agenda');
            $table->text('summary_decisions')->nullable();
            $table->text('drh_notes')->nullable();
            $table->enum('status', ['draft', 'convoked', 'ongoing', 'completed'])->default('draft');
            $table->string('docx_file_path')->nullable();
            $table->timestamps();

            $table->index(['date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
