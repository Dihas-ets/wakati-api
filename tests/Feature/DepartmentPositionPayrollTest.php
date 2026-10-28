<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Department;
use App\Models\Payroll;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepartmentPositionPayrollTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $director;
    private User $rh;
    private User $comptable;
    private Department $dept;
    private Position $standardPos;
    private Position $sensitivePos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Wakati Test Corp',
            'slug' => 'wakati-test-corp',
            'status' => 'active',
            'access_type' => 'free_grant',
        ]);

        $this->dept = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Finance & Comptabilité',
            'description' => 'Département comptable et gestion',
        ]);

        $this->standardPos = Position::create([
            'company_id' => $this->company->id,
            'department_id' => $this->dept->id,
            'title' => 'Assistant Comptable',
            'permissions' => ['view_accounting'],
            'is_sensitive' => false,
        ]);

        $this->sensitivePos = Position::create([
            'company_id' => $this->company->id,
            'department_id' => $this->dept->id,
            'title' => 'Chef Comptable',
            'permissions' => ['accounting', 'comptable', 'mark_paid'],
            'is_sensitive' => true,
        ]);

        $this->director = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_DIRECTEUR,
            'email' => 'directeur@test.com',
        ]);

        $this->rh = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_RH,
            'email' => 'rh@test.com',
        ]);

        $this->comptable = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_COMPTABLE,
            'email' => 'comptable@test.com',
        ]);
    }

    public function test_user_has_permission_and_sensitive_role_logic(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYE,
        ]);
        $user->positions()->attach($this->standardPos->id, ['is_primary' => true]);

        $this->assertTrue($user->hasPermission('view_accounting'));
        $this->assertFalse($user->hasPermission('accounting'));
        $this->assertFalse($user->hasSensitiveRole());

        // Attach sensitive position
        $user->positions()->attach($this->sensitivePos->id, ['is_primary' => false]);
        $user->unsetRelation('positions');

        $this->assertTrue($user->hasPermission('accounting'));
        $this->assertTrue($user->hasSensitiveRole());
    }

    public function test_rh_cannot_create_sensitive_position(): void
    {
        Sanctum::actingAs($this->rh);

        $response = $this->postJson('/api/positions', [
            'department_id' => $this->dept->id,
            'title' => 'Directeur Financier',
            'is_sensitive' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_director_can_create_sensitive_position_and_audit_logged(): void
    {
        Sanctum::actingAs($this->director);

        $response = $this->postJson('/api/positions', [
            'department_id' => $this->dept->id,
            'title' => 'Directeur Financier',
            'is_sensitive' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('positions', [
            'title' => 'Directeur Financier',
            'is_sensitive' => 1,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->director->id,
            'action' => 'position.sensitive_created',
        ]);
    }

    public function test_rh_cannot_assign_sensitive_position_to_employee(): void
    {
        Sanctum::actingAs($this->rh);

        $response = $this->postJson('/api/employees', [
            'name' => 'Alice Test',
            'email' => 'alice@test.com',
            'password' => 'secret1234',
            'primary_position_id' => $this->sensitivePos->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_director_can_assign_sensitive_position_and_audit_logged(): void
    {
        Sanctum::actingAs($this->director);

        $response = $this->postJson('/api/employees', [
            'name' => 'Alice Sensible',
            'email' => 'alice.sensible@test.com',
            'password' => 'secret1234',
            'primary_position_id' => $this->sensitivePos->id,
        ]);

        $response->assertStatus(201);
        $createdUser = User::where('email', 'alice.sensible@test.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertTrue($createdUser->hasSensitiveRole());

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->director->id,
            'target_user_id' => $createdUser->id,
            'action' => 'position.sensitive_assigned',
        ]);
    }

    public function test_payroll_workflow_rh_validation_and_comptable_payment(): void
    {
        $employee = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYE,
            'department_id' => $this->dept->id,
        ]);

        // 1. Comptable cannot pay an unvalidated payroll
        Sanctum::actingAs($this->comptable);
        $payroll = Payroll::create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'month' => '2026-10',
            'month_year' => '2026-10',
            'base_salary' => 200000,
            'bonus' => 20000,
            'deductions' => 10000,
            'net_salary' => 210000,
            'status_rh' => Payroll::RH_STATUS_DRAFT,
            'payment_status' => Payroll::PAYMENT_STATUS_UNPAID,
        ]);

        $payAttempt = $this->postJson("/api/payrolls/{$payroll->id}/mark-as-paid", [
            'payment_reference' => 'VIR-2026-001',
        ]);
        $payAttempt->assertStatus(422);

        // 2. RH validates the payroll
        Sanctum::actingAs($this->rh);
        $rhResponse = $this->postJson('/api/payrolls/upsert-rh', [
            'user_id' => $employee->id,
            'month' => '2026-10',
            'base_salary' => 200000,
            'bonus' => 25000,
            'deductions' => 5000,
            'rh_status' => 'validated_by_rh',
        ]);
        $rhResponse->assertStatus(200);

        $payroll->refresh();
        $this->assertEquals(Payroll::RH_STATUS_VALIDATED, $payroll->rh_status);
        $this->assertEquals(220000, (float) $payroll->net_salary);

        // 3. Comptable marks as paid
        Sanctum::actingAs($this->comptable);
        $paySuccess = $this->postJson("/api/payrolls/{$payroll->id}/mark-as-paid", [
            'payment_reference' => 'VIR-2026-001',
        ]);
        $paySuccess->assertStatus(200);

        $payroll->refresh();
        $this->assertEquals(Payroll::PAYMENT_STATUS_PAID, $payroll->payment_status);
        $this->assertEquals('VIR-2026-001', $payroll->payment_reference);

        // 4. Audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->comptable->id,
            'target_user_id' => $employee->id,
            'action' => 'payroll.marked_as_paid',
        ]);
    }

    public function test_payroll_csv_export(): void
    {
        Sanctum::actingAs($this->comptable);

        $response = $this->get('/api/payrolls/export/csv?month=2026-10');
        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }
}
