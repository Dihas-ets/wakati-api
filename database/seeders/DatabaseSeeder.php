<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            UserSeeder::class,
            SuperAdminSeeder::class,
            DepartmentSeeder::class,
            //EmployeeSeeder::class,
            CompanySettingSeeder::class,
        ]);
    }
}
