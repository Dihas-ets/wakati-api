<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Crée un compte de test par rôle.
     * Mot de passe pour les trois : "password"
     * ⚠️ À supprimer ou changer avant la mise en production.
     */
    public function run(): void
    {
        $accounts = [
            ['name' => 'Responsable RH', 'email' => 'rh@wakati.test', 'role' => User::ROLE_RH],
            ['name' => 'Comptable', 'email' => 'comptable@wakati.test', 'role' => User::ROLE_COMPTABLE],
            ['name' => 'Directeur', 'email' => 'directeur@wakati.test', 'role' => User::ROLE_DIRECTEUR],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'role' => $account['role'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
