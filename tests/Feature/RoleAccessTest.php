<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_cannot_access_user_management(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);

        $response = $this->actingAs($employee, 'sanctum')->getJson('/api/users');

        $response->assertStatus(403);
    }

    public function test_directeur_can_access_user_management(): void
    {
        $directeur = User::factory()->create(['role' => 'directeur']);

        $response = $this->actingAs($directeur, 'sanctum')->getJson('/api/users');

        $response->assertStatus(200);
    }

    public function test_rh_cannot_access_user_management(): void
    {
        $rh = User::factory()->create(['role' => 'rh']);

        $response = $this->actingAs($rh, 'sanctum')->getJson('/api/users');

        $response->assertStatus(403);
    }

    public function test_employee_cannot_update_company_settings(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);

        $response = $this->actingAs($employee, 'sanctum')
            ->putJson('/api/company-settings', ['late_tolerance_minutes' => 15]);

        $response->assertStatus(403);
    }

    public function test_rh_can_update_company_settings(): void
    {
        $rh = User::factory()->create(['role' => 'rh']);

        $response = $this->actingAs($rh, 'sanctum')
            ->putJson('/api/company-settings', ['late_tolerance_minutes' => 15]);

        $response->assertStatus(200);
    }

    public function test_guest_cannot_access_any_protected_route(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }
}