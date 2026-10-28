<?php

namespace Database\Seeders;

use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'Responsable RH',
                'email' => 'rh@wakati.test',
                'role' => User::ROLE_RH,
                'position_title' => 'Responsable RH',
            ],
            [
                'name' => 'Comptable',
                'email' => 'comptable@wakati.test',
                'role' => User::ROLE_COMPTABLE,
                'position_title' => 'Comptable',
            ],
            [
                'name' => 'Directeur',
                'email' => 'directeur@wakati.test',
                'role' => User::ROLE_DIRECTEUR,
                'position_title' => 'Directeur Général',
            ],
        ];

        foreach ($accounts as $account) {
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'role' => $account['role'],
                    'job_title' => $account['position_title'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            $position = Position::where('title', $account['position_title'])->first();
            if ($position) {
                $user->update(['department_id' => $position->department_id]);
                $user->positions()->syncWithoutDetaching([
                    $position->id => ['is_primary' => true],
                ]);
            }
        }
    }
}
