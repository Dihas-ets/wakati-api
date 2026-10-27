<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyDirectorAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_assigns_employee_to_agency_becomes_director_and_role_changes(): void
    {
        $company = Company::create([
            'name' => 'Wakati Tech SARL',
            'email' => 'admin@wakatitech.test',
        ]);

        $companyAdmin = User::factory()->create([
            'company_id' => $company->id,
            'role' => User::ROLE_COMPANY_ADMIN,
        ]);

        $agency = Agency::create([
            'company_id' => $company->id,
            'name' => 'Agence Akpakpa',
            'code' => 'AKP',
            'timezone' => 'Africa/Porto-Novo',
            'is_active' => true,
        ]);

        $employee = User::factory()->create([
            'company_id' => $company->id,
            'agency_id' => null,
            'role' => User::ROLE_EMPLOYEE,
            'name' => 'Jean Dupont',
            'job_title' => 'Commercial Terrain',
        ]);

        // When company_admin assigns employee to the agency via /api/branches/{agency}/assign-manager
        $response = $this->actingAs($companyAdmin, 'sanctum')->postJson("/api/branches/{$agency->id}/assign-manager", [
            'user_id' => $employee->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('user.role', User::ROLE_DIRECTEUR);
        $response->assertJsonPath('user.agency_id', $agency->id);

        $employee->refresh();
        $this->assertEquals($agency->id, $employee->agency_id);
        $this->assertEquals(User::ROLE_DIRECTEUR, $employee->role);
        $this->assertStringContainsString("Directeur d'agence", $employee->job_title);
    }

    public function test_company_admin_assigns_employee_via_update_promotes_to_director(): void
    {
        $company = Company::create([
            'name' => 'Afrique Logistics',
            'email' => 'contact@afrilogi.test',
        ]);

        $companyAdmin = User::factory()->create([
            'company_id' => $company->id,
            'role' => User::ROLE_COMPANY_ADMIN,
        ]);

        $agency = Agency::create([
            'company_id' => $company->id,
            'name' => 'Agence Calavi',
            'code' => 'CLV',
        ]);

        $employee = User::factory()->create([
            'company_id' => $company->id,
            'agency_id' => null,
            'role' => User::ROLE_EMPLOYE,
            'name' => 'Aïcha Traoré',
            'job_title' => 'Comptable Junior',
        ]);

        // When company_admin updates employee with agency_id
        $response = $this->actingAs($companyAdmin, 'sanctum')->putJson("/api/employees/{$employee->id}", [
            'agency_id' => $agency->id,
        ]);

        $response->assertStatus(200);

        $employee->refresh();
        $this->assertEquals($agency->id, $employee->agency_id);
        $this->assertEquals(User::ROLE_DIRECTEUR, $employee->role);
        $this->assertStringContainsString("Directeur d'agence", $employee->job_title);
    }

    public function test_cannot_assign_employee_from_another_company(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $companyAdminA = User::factory()->create([
            'company_id' => $companyA->id,
            'role' => User::ROLE_COMPANY_ADMIN,
        ]);

        $agencyA = Agency::create([
            'company_id' => $companyA->id,
            'name' => 'Agence A1',
        ]);

        $employeeB = User::factory()->create([
            'company_id' => $companyB->id,
            'role' => User::ROLE_EMPLOYEE,
        ]);

        $response = $this->actingAs($companyAdminA, 'sanctum')->postJson("/api/branches/{$agencyA->id}/assign-manager", [
            'user_id' => $employeeB->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_director_cannot_create_update_or_delete_agencies(): void
    {
        $company = Company::create(['name' => 'Tech Corp']);
        $director = User::factory()->create([
            'company_id' => $company->id,
            'role' => User::ROLE_DIRECTEUR,
        ]);

        $agency = Agency::create([
            'company_id' => $company->id,
            'name' => 'Agence Porto-Novo',
        ]);

        // Attempt to create agency -> 403 Forbidden
        $createResponse = $this->actingAs($director, 'sanctum')->postJson('/api/branches', [
            'name' => 'Nouvelle Agence Cotonou',
        ]);
        $createResponse->assertStatus(403);

        // Attempt to update agency -> 403 Forbidden
        $updateResponse = $this->actingAs($director, 'sanctum')->putJson("/api/branches/{$agency->id}", [
            'name' => 'Nom Modifié',
        ]);
        $updateResponse->assertStatus(403);

        // Attempt to delete agency -> 403 Forbidden
        $deleteResponse = $this->actingAs($director, 'sanctum')->deleteJson("/api/branches/{$agency->id}");
        $deleteResponse->assertStatus(403);
    }

    public function test_director_cannot_view_subscription_nor_quota(): void
    {
        $company = Company::create(['name' => 'Fintech SA']);
        $director = User::factory()->create([
            'company_id' => $company->id,
            'role' => User::ROLE_DIRECTEUR,
        ]);

        // Attempt to fetch company subscription endpoint -> 403 Forbidden
        $subResponse = $this->actingAs($director, 'sanctum')->getJson('/api/company/subscription');
        $subResponse->assertStatus(403);

        // Attempt to change or pay subscription -> 403 Forbidden
        $this->actingAs($director, 'sanctum')->postJson('/api/company/subscription/change', ['plan_id' => 1])
            ->assertStatus(403);
        $this->actingAs($director, 'sanctum')->postJson('/api/company/subscription/pay', ['plan_id' => 1])
            ->assertStatus(403);
        $this->actingAs($director, 'sanctum')->getJson('/api/company/payments')
            ->assertStatus(403);

        // When director fetches branches list, quota must be null
        $branchesResponse = $this->actingAs($director, 'sanctum')->getJson('/api/branches');
        $branchesResponse->assertStatus(200);
        $branchesResponse->assertJsonPath('quota', null);

        // When director views company profile, currentSubscription must not be returned
        $profileResponse = $this->actingAs($director, 'sanctum')->getJson('/api/company/profile');
        $profileResponse->assertStatus(200);
        $profileResponse->assertJsonMissingPath('company.current_subscription');
    }

    public function test_company_admin_can_view_subscription_quota_and_create_agencies(): void
    {
        $company = Company::create(['name' => 'Admin Corp']);
        $companyAdmin = User::factory()->create([
            'company_id' => $company->id,
            'role' => User::ROLE_COMPANY_ADMIN,
        ]);

        // Company admin can view subscription
        $subResponse = $this->actingAs($companyAdmin, 'sanctum')->getJson('/api/company/subscription');
        $subResponse->assertStatus(200);

        // Company admin can view quota
        $branchesResponse = $this->actingAs($companyAdmin, 'sanctum')->getJson('/api/branches');
        $branchesResponse->assertStatus(200);
        $branchesResponse->assertJsonStructure(['quota' => ['current', 'max', 'is_unlimited', 'can_create']]);

        // Company admin can create an agency
        $createResponse = $this->actingAs($companyAdmin, 'sanctum')->postJson('/api/branches', [
            'name' => 'Agence Calavi Centre',
        ]);
        $createResponse->assertStatus(201);
    }
}

