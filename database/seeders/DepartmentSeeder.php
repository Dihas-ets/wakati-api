<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'Direction Générale' => [
                'description' => 'Pilotage stratégique et gouvernance globale.',
                'positions' => [
                    ['title' => 'Directeur Général', 'permissions' => ['admin', '*'], 'is_sensitive' => true],
                    ['title' => 'Adjoint de Direction', 'permissions' => ['view_all'], 'is_sensitive' => false],
                ],
            ],
            'Ressources Humaines' => [
                'description' => 'Gestion du personnel, talents, congés et conformité RH.',
                'positions' => [
                    ['title' => 'Responsable RH', 'permissions' => ['rh', 'manage_employees', 'manage_leaves', 'validate_payroll'], 'is_sensitive' => true],
                    ['title' => 'Chargé de Recrutement & Formation', 'permissions' => ['view_employees'], 'is_sensitive' => false],
                ],
            ],
            'Comptabilité & Finance' => [
                'description' => 'Comptabilité, paie, trésorerie et déclarations financières.',
                'positions' => [
                    ['title' => 'Comptable', 'permissions' => ['comptable', 'accounting', 'mark_paid'], 'is_sensitive' => true],
                    ['title' => 'Auditeur Financier', 'permissions' => ['view_accounting'], 'is_sensitive' => false],
                ],
            ],
            'Technology' => [
                'description' => 'Développement informatique, infrastructure cloud et support.',
                'positions' => [
                    ['title' => 'Développeur Full-Stack', 'permissions' => ['tech'], 'is_sensitive' => false],
                    ['title' => 'Ingénieur DevOps', 'permissions' => ['tech', 'infra'], 'is_sensitive' => false],
                    ['title' => 'Lead Developer', 'permissions' => ['tech', 'lead'], 'is_sensitive' => false],
                ],
            ],
            'Commercial & Marketing' => [
                'description' => 'Développement commercial, partenariats et communication.',
                'positions' => [
                    ['title' => 'Responsable Commercial', 'permissions' => ['commercial'], 'is_sensitive' => false],
                    ['title' => 'Chargé de Clientèle', 'permissions' => ['commercial'], 'is_sensitive' => false],
                ],
            ],
        ];

        foreach ($data as $deptName => $info) {
            $department = Department::firstOrCreate(
                ['name' => $deptName],
                ['description' => $info['description']]
            );

            if (! $department->description) {
                $department->update(['description' => $info['description']]);
            }

            foreach ($info['positions'] as $pos) {
                Position::firstOrCreate(
                    [
                        'department_id' => $department->id,
                        'title' => $pos['title'],
                    ],
                    [
                        'permissions' => $pos['permissions'],
                        'is_sensitive' => $pos['is_sensitive'],
                    ]
                );
            }
        }
    }
}
