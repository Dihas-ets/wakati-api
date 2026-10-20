<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\KanbanTask;
use App\Models\Meeting;
use App\Models\Plan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_registration_creates_company_subscription_and_company_admin(): void
    {
        $plan = Plan::create([
            'name' => 'Plan Pro',
            'price' => 15000,
            'currency' => 'XOF',
            'duration' => 1,
            'duration_unit' => 'month',
            'is_trial' => false,
            'max_employees' => 20,
        ]);

        $response = $this->postJson('/api/register-tenant', [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@entreprise-a.test',
            'password' => 'password123',
            'phone' => '+229 97 11 22 33',
            'company_name' => 'Entreprise A',
            'plan_id' => $plan->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'token',
                'user' => ['id', 'name', 'email', 'role', 'company_id', 'agency_id'],
                'company' => ['id', 'name', 'phone'],
                'subscription' => ['id', 'plan_id', 'plan_name', 'status', 'start_date', 'end_date'],
            ]);

        $this->assertDatabaseHas('companies', [
            'name' => 'Entreprise A',
            'phone' => '+229 97 11 22 33',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jean.dupont@entreprise-a.test',
            'name' => 'Jean Dupont',
            'role' => User::ROLE_COMPANY_ADMIN,
        ]);

        $user = User::where('email', 'jean.dupont@entreprise-a.test')->first();
        $this->assertNotNull($user->company_id);
        $this->assertNull($user->agency_id);
    }

    public function test_company_admin_is_automatically_isolated_by_company_id(): void
    {
        $companyA = Company::create(['name' => 'Entreprise A']);
        $companyB = Company::create(['name' => 'Entreprise B']);

        $adminA = User::factory()->create([
            'role' => User::ROLE_COMPANY_ADMIN,
            'company_id' => $companyA->id,
            'agency_id' => null,
        ]);

        $adminB = User::factory()->create([
            'role' => User::ROLE_COMPANY_ADMIN,
            'company_id' => $companyB->id,
            'agency_id' => null,
        ]);

        // Créer des entités pour chaque entreprise
        $taskA = KanbanTask::create([
            'company_id' => $companyA->id,
            'title' => 'Tâche Entreprise A',
            'assigned_to' => $adminA->id,
            'created_by' => $adminA->id,
        ]);

        $taskB = KanbanTask::create([
            'company_id' => $companyB->id,
            'title' => 'Tâche Entreprise B',
            'assigned_to' => $adminB->id,
            'created_by' => $adminB->id,
        ]);

        // En tant qu'admin A, les requêtes doivent uniquement retourner les données de Company A
        $this->actingAs($adminA);
        $visibleTasks = KanbanTask::all();
        $this->assertTrue($visibleTasks->contains('id', $taskA->id));
        $this->assertFalse($visibleTasks->contains('id', $taskB->id));

        // En tant qu'admin B, seules les données de Company B sont visibles
        $this->actingAs($adminB);
        $visibleTasksB = KanbanTask::all();
        $this->assertFalse($visibleTasksB->contains('id', $taskA->id));
        $this->assertTrue($visibleTasksB->contains('id', $taskB->id));
    }

    public function test_creating_hook_automatically_sets_tenant_ids(): void
    {
        $company = Company::create(['name' => 'Entreprise Test']);
        $agency = Agency::create(['company_id' => $company->id, 'name' => 'Agence Cotonou']);

        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'company_id' => $company->id,
            'agency_id' => $agency->id,
        ]);

        $this->actingAs($manager);

        // Création sans passer company_id ni agency_id
        $meeting = Meeting::create([
            'title' => 'Réunion Agence',
            'date' => Carbon::tomorrow()->toDateString(),
            'time' => '10:00',
            'location' => 'Salle 1',
            'redactor_id' => $manager->id,
            'agenda' => 'Ordre du jour',
        ]);

        $this->assertEquals($company->id, $meeting->company_id);
        $this->assertEquals($agency->id, $meeting->agency_id);
    }

    public function test_manager_and_employee_are_isolated_by_agency_id(): void
    {
        $company = Company::create(['name' => 'Entreprise Multi-Agences']);
        $agency1 = Agency::create(['company_id' => $company->id, 'name' => 'Agence 1']);
        $agency2 = Agency::create(['company_id' => $company->id, 'name' => 'Agence 2']);

        $emp1 = User::factory()->create([
            'role' => User::ROLE_EMPLOYEE,
            'company_id' => $company->id,
            'agency_id' => $agency1->id,
        ]);

        $emp2 = User::factory()->create([
            'role' => User::ROLE_EMPLOYEE,
            'company_id' => $company->id,
            'agency_id' => $agency2->id,
        ]);

        $att1 = Attendance::create([
            'company_id' => $company->id,
            'agency_id' => $agency1->id,
            'user_id' => $emp1->id,
            'date' => Carbon::today()->toDateString(),
            'check_in_time' => Carbon::now(),
            'status' => 'on_time',
        ]);

        $att2 = Attendance::create([
            'company_id' => $company->id,
            'agency_id' => $agency2->id,
            'user_id' => $emp2->id,
            'date' => Carbon::today()->toDateString(),
            'check_in_time' => Carbon::now(),
            'status' => 'on_time',
        ]);

        // Emp1 voit uniquement son agence
        $this->actingAs($emp1);
        $visibleAttendances = Attendance::all();
        $this->assertTrue($visibleAttendances->contains('id', $att1->id));
        $this->assertFalse($visibleAttendances->contains('id', $att2->id));

        // Emp2 voit uniquement son agence
        $this->actingAs($emp2);
        $visibleAttendances2 = Attendance::all();
        $this->assertFalse($visibleAttendances2->contains('id', $att1->id));
        $this->assertTrue($visibleAttendances2->contains('id', $att2->id));
    }

    public function test_super_admin_has_unrestricted_access(): void
    {
        $companyA = Company::create(['name' => 'Entreprise Alpha']);
        $companyB = Company::create(['name' => 'Entreprise Beta']);

        $adminA = User::factory()->create(['company_id' => $companyA->id]);
        $adminB = User::factory()->create(['company_id' => $companyB->id]);

        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'company_id' => null,
            'agency_id' => null,
        ]);

        $this->actingAs($superAdmin);
        $users = User::all();
        $this->assertTrue($users->contains('id', $adminA->id));
        $this->assertTrue($users->contains('id', $adminB->id));
    }
}
