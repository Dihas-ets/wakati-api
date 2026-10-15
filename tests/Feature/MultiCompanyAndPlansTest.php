<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiCompanyAndPlansTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\SuperAdminSeeder::class);
    }

    public function test_can_list_public_plans(): void
    {
        $response = $this->getJson('/api/plans');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'name',
                'price',
                'currency',
                'duration',
                'duration_unit',
                'features',
            ],
        ]);
    }

    public function test_company_registration_creates_company_admin_and_subscription(): void
    {
        $plan = Plan::where('slug', 'pack-decouverte')->first();

        $payload = [
            'company_name' => 'Acme Corporation',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+229 97 11 22 33',
            'email' => 'john.doe@acme.test',
            'password' => 'secret123',
            'plan_id' => $plan->id,
            'registration_number' => 'RCCM-ACME-2026',
        ];

        $response = $this->postJson('/api/register-company', $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'token',
            'user' => [
                'id',
                'name',
                'role',
                'company_id',
                'company' => [
                    'id',
                    'name',
                    'access_type',
                    'status',
                ],
                'subscription' => [
                    'id',
                    'plan_name',
                    'start_date',
                    'end_date',
                ],
            ],
        ]);

        $this->assertDatabaseHas('companies', [
            'name' => 'Acme Corporation',
            'registration_number' => 'RCCM-ACME-2026',
            'has_used_trial' => 1,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@acme.test',
            'role' => User::ROLE_COMPANY_ADMIN,
        ]);

        $company = Company::where('name', 'Acme Corporation')->first();
        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'is_trial' => 1,
        ]);

        $this->assertDatabaseHas('company_histories', [
            'company_id' => $company->id,
            'action' => 'registration',
        ]);
    }

    public function test_free_trial_cannot_be_reused_once_used(): void
    {
        // Enregistrer une entreprise avec un plan d'essai
        $plan = Plan::where('slug', 'pack-decouverte')->first();

        $registerRes = $this->postJson('/api/register-company', [
            'company_name' => 'Beta Tech',
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'phone' => '+229 96 00 00 00',
            'email' => 'alice@betatech.test',
            'password' => 'secret123',
            'plan_id' => $plan->id,
        ]);

        $token = $registerRes->json('token');

        // Tenter de réactiver le plan gratuit d'essai
        $changeRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/company/subscription/change', [
                'plan_id' => $plan->id,
            ]);

        $changeRes->assertStatus(422);
        $changeRes->assertJsonValidationErrors(['plan_id']);
    }

    public function test_super_admin_can_manage_companies_and_change_access(): void
    {
        $superAdmin = User::where('role', User::ROLE_SUPER_ADMIN)->first();
        $token = $superAdmin->createToken('test')->plainTextToken;

        // Stats
        $statsRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/super-admin/stats');
        $statsRes->assertStatus(200);
        $statsRes->assertJsonStructure(['total_companies', 'active_subscriptions', 'total_revenue']);

        // Modifier l'accès d'une entreprise (free_grant vs subscription)
        $company = Company::first();

        $patchRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/super-admin/companies/{$company->id}/access", [
                'access_type' => 'free_grant',
            ]);

        $patchRes->assertStatus(200);
        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'access_type' => 'free_grant',
        ]);

        $this->assertDatabaseHas('company_histories', [
            'company_id' => $company->id,
            'action' => 'access_type_changed',
        ]);
    }

    public function test_super_admin_can_create_and_manage_plans(): void
    {
        $superAdmin = User::where('role', User::ROLE_SUPER_ADMIN)->first();
        $token = $superAdmin->createToken('test')->plainTextToken;

        $planPayload = [
            'name' => 'Forfait Sur Mesure',
            'description' => 'Un forfait customisé pour grands comptes.',
            'price' => 300000,
            'currency' => 'FCFA',
            'duration' => 6,
            'duration_unit' => 'month',
            'duration_type' => 'recurring',
            'is_trial' => false,
            'trial_period_days' => 0,
            'features' => ['Support 24/7', 'Serveur dédié'],
            'max_employees' => 500,
            'is_active' => true,
        ];

        $createRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/super-admin/plans', $planPayload);

        $createRes->assertStatus(201);
        $this->assertDatabaseHas('plans', [
            'name' => 'Forfait Sur Mesure',
            'price' => 300000,
        ]);
    }

    public function test_company_admin_can_update_logo_and_references(): void
    {
        $company = Company::first();
        $admin = User::create([
            'name' => 'Directeur Test',
            'email' => 'admin.test@wakati.test',
            'password' => bcrypt('password'),
            'role' => User::ROLE_COMPANY_ADMIN,
            'company_id' => $company->id,
        ]);
        $token = $admin->createToken('test')->plainTextToken;

        $updateRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/company/profile', [
                'name' => 'Wakati Pilote Mis à Jour',
                'registration_number' => 'IFU-998877665544',
                'logo_url' => 'https://example.com/logo.png',
                'phone' => '+229 97 99 99 99',
            ]);

        $updateRes->assertStatus(200);
        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Wakati Pilote Mis à Jour',
            'registration_number' => 'IFU-998877665544',
            'logo_url' => 'https://example.com/logo.png',
        ]);
    }
}
