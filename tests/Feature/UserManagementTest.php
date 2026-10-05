<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_rh_and_directeur_can_list_and_create_users_with_departments_and_job_titles(): void
    {
        $rh = User::factory()->create(['role' => 'rh']);
        $department = Department::create(['name' => 'Technologie']);

        // RH creates an employee with job_title and department
        $response = $this->actingAs($rh, 'sanctum')->postJson('/api/users', [
            'name' => 'Marc Developer',
            'email' => 'marc@wakati.test',
            'password' => 'secret1234',
            'role' => 'employe',
            'job_title' => 'Lead Developer',
            'department_id' => $department->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'name' => 'Marc Developer',
            'job_title' => 'Lead Developer',
            'department_id' => $department->id,
        ]);

        // List users as RH
        $listResponse = $this->actingAs($rh, 'sanctum')->getJson('/api/users');
        $listResponse->assertStatus(200);
        $listResponse->assertJsonFragment([
            'name' => 'Marc Developer',
            'job_title' => 'Lead Developer',
        ]);

        // Departments index includes users and users_count
        $depResponse = $this->actingAs($rh, 'sanctum')->getJson('/api/departments');
        $depResponse->assertStatus(200);
        $depResponse->assertJsonFragment([
            'name' => 'Technologie',
            'users_count' => 1,
        ]);
    }

    public function test_rh_cannot_create_directeur_account(): void
    {
        $rh = User::factory()->create(['role' => 'rh']);

        $response = $this->actingAs($rh, 'sanctum')->postJson('/api/users', [
            'name' => 'Big Boss',
            'email' => 'boss@wakati.test',
            'password' => 'secret1234',
            'role' => 'directeur',
        ]);

        $response->assertStatus(403);
    }
}
