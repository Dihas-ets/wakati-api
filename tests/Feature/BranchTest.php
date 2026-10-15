<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase;

    private function createCompany(array $attributes = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp-' . uniqid(),
            'access_type' => Company::ACCESS_TYPE_FREE_GRANT,
            'status' => Company::STATUS_ACTIVE,
        ], $attributes));
    }

    private function createUser(Company $company, array $attributes = []): User
    {
        return User::create(array_merge([
            'company_id' => $company->id,
            'name' => 'John Doe',
            'email' => 'user-' . uniqid() . '@acme.test',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_COMPANY_ADMIN,
        ], $attributes));
    }

    public function test_company_admin_can_list_and_create_branches(): void
    {
        $company = $this->createCompany();
        $admin = $this->createUser($company, ['role' => User::ROLE_COMPANY_ADMIN]);

        $response = $this->actingAs($admin)->postJson('/api/branches', [
            'name' => 'Agence Akpakpa',
            'code' => 'AKP',
            'address' => 'Cotonou, Bénin',
            'timezone' => 'Africa/Porto-Novo',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('branches', [
            'company_id' => $company->id,
            'name' => 'Agence Akpakpa',
            'code' => 'AKP',
        ]);

        $listResponse = $this->actingAs($admin)->getJson('/api/branches');
        $listResponse->assertStatus(200);
        $listResponse->assertJsonFragment(['name' => 'Agence Akpakpa']);
    }

    public function test_branch_creation_is_blocked_when_plan_quota_is_exceeded(): void
    {
        $plan = Plan::create([
            'name' => 'Basic Plan',
            'slug' => 'basic-plan-' . uniqid(),
            'price' => 10000,
            'currency' => 'FCFA',
            'max_branches' => 1,
            'max_employees' => 5,
        ]);

        $company = $this->createCompany([
            'access_type' => Company::ACCESS_TYPE_SUBSCRIPTION,
        ]);

        Subscription::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'start_date' => now(),
            'end_date' => now()->addMonth(),
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $admin = $this->createUser($company, ['role' => User::ROLE_COMPANY_ADMIN]);

        // 1ère agence (quota 1/1 atteint)
        Branch::create([
            'company_id' => $company->id,
            'name' => 'Siège',
        ]);

        // Tentative de créer une 2ème agence
        $response = $this->actingAs($admin)->postJson('/api/branches', [
            'name' => 'Seconde Agence',
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['code' => 'QUOTA_EXCEEDED_BRANCHES']);
    }

    public function test_branch_manager_is_scoped_to_their_branch(): void
    {
        $company = $this->createCompany();

        $branch1 = Branch::create(['company_id' => $company->id, 'name' => 'Agence 1']);
        $branch2 = Branch::create(['company_id' => $company->id, 'name' => 'Agence 2']);

        $manager = $this->createUser($company, [
            'branch_id' => $branch1->id,
            'role' => User::ROLE_BRANCH_MANAGER,
        ]);

        $response = $this->actingAs($manager)->getJson('/api/branches');
        $response->assertStatus(200);

        $branches = $response->json('branches');
        $this->assertCount(1, $branches);
        $this->assertEquals($branch1->id, $branches[0]['id']);
    }
}
