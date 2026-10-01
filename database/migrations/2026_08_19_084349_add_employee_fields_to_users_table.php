<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Poste occupé, distinct du rôle d'accès système (ex: "Développeur", "Head of Technology").
            // Rempli surtout pour les users role="employe", mais reste disponible pour tout le monde.
            $table->string('job_title')->nullable()->after('role');

            // Département de rattachement. Nullable : un directeur/RH/comptable
            // n'a pas forcément de département.
            $table->foreignId('department_id')
                ->nullable()
                ->after('job_title')
                ->constrained('departments')
                ->nullOnDelete();

            // Statut de présence, dérivé de la connexion/déconnexion (+ pause manuelle).
            $table->string('status')->default('pas_pointe')->after('department_id');

            // Utile pour l'affichage ("dernière connexion") et pour du debug.
            $table->timestamp('last_login_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['job_title', 'status', 'last_login_at']);
        });
    }
};
