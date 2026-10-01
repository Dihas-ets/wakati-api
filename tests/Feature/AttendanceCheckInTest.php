<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\Leave;
use App\Models\User;
use App\Models\WorkPattern;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceCheckInTest extends TestCase
{
    use RefreshDatabase;

    private function seedSettings(array $allowedIps = ['127.0.0.1'], int $tolerance = 10, int $absenceDelay = 60): void
    {
        CompanySetting::create(['key' => 'allowed_ips', 'value' => $allowedIps]);
        CompanySetting::create(['key' => 'late_tolerance_minutes', 'value' => $tolerance]);
        CompanySetting::create(['key' => 'absence_delay_minutes', 'value' => $absenceDelay]);
    }

    public function test_employee_can_check_in_on_time_within_active_work_pattern(): void
    {
        $this->seedSettings();

        $now = Carbon::now();
        $employee = User::factory()->create(['role' => 'employe']);

        WorkPattern::create([
            'user_id' => $employee->id,
            'day_of_week' => $now->dayOfWeek,
            'start_time' => $now->format('H:i'),
            'end_time' => $now->copy()->addHours(8)->format('H:i'),
            'effective_from' => $now->toDateString(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/attendance/check-in');

        $response->assertStatus(201);
        $response->assertJsonPath('attendance.status', 'on_time');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $employee->id,
            'status' => 'on_time',
        ]);
    }

    public function test_employee_checking_in_after_tolerance_is_marked_late(): void
    {
        $this->seedSettings(tolerance: 10);

        $now = Carbon::now();
        $employee = User::factory()->create(['role' => 'employe']);

        WorkPattern::create([
            'user_id' => $employee->id,
            'day_of_week' => $now->dayOfWeek,
            'start_time' => $now->copy()->subMinutes(20)->format('H:i'),
            'end_time' => $now->copy()->addHours(8)->format('H:i'),
            'effective_from' => $now->toDateString(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/attendance/check-in');

        $response->assertStatus(201);
        $response->assertJsonPath('attendance.status', 'late');
    }

    public function test_check_in_is_rejected_without_any_schedule_or_pattern(): void
    {
        $this->seedSettings();

        $employee = User::factory()->create(['role' => 'employe']);

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/attendance/check-in');

        $response->assertStatus(404);
    }

    public function test_check_in_is_rejected_when_already_checked_in_today(): void
    {
        $this->seedSettings();

        $now = Carbon::now();
        $employee = User::factory()->create(['role' => 'employe']);

        WorkPattern::create([
            'user_id' => $employee->id,
            'day_of_week' => $now->dayOfWeek,
            'start_time' => $now->format('H:i'),
            'end_time' => $now->copy()->addHours(8)->format('H:i'),
            'effective_from' => $now->toDateString(),
            'is_active' => true,
        ]);

        $this->actingAs($employee, 'sanctum')->postJson('/api/attendance/check-in');
        $response = $this->actingAs($employee, 'sanctum')->postJson('/api/attendance/check-in');

        $response->assertStatus(422);
    }

    public function test_check_in_is_rejected_from_an_ip_outside_the_allowed_list(): void
    {
        // Aucune IP autorisée ne correspond à celle du test (127.0.0.1 exclu).
        $this->seedSettings(allowedIps: ['203.0.113.10']);

        $now = Carbon::now();
        $employee = User::factory()->create(['role' => 'employe']);

        WorkPattern::create([
            'user_id' => $employee->id,
            'day_of_week' => $now->dayOfWeek,
            'start_time' => $now->format('H:i'),
            'end_time' => $now->copy()->addHours(8)->format('H:i'),
            'effective_from' => $now->toDateString(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/attendance/check-in');

        $response->assertStatus(403);
    }

    public function test_check_in_is_rejected_when_employee_is_on_leave_today(): void
    {
        $this->seedSettings();

        $now = Carbon::now();
        $employee = User::factory()->create(['role' => 'employe']);

        WorkPattern::create([
            'user_id' => $employee->id,
            'day_of_week' => $now->dayOfWeek,
            'start_time' => $now->format('H:i'),
            'end_time' => $now->copy()->addHours(8)->format('H:i'),
            'effective_from' => $now->toDateString(),
            'is_active' => true,
        ]);

        Leave::create([
            'user_id' => $employee->id,
            'leave_type' => 'annuel',
            'date' => $now->toDateString(),
            'work_mode' => 'presentiel',
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/attendance/check-in');

        $response->assertStatus(422);
    }

    public function test_qr_check_in_succeeds_when_badge_matches_authenticated_user(): void
    {
        $this->seedSettings();

        $now = Carbon::now();
        $employee = User::factory()->create([
            'role' => 'employe',
            'card_uid' => 'WAK-TESTBADGE01',
        ]);

        WorkPattern::create([
            'user_id' => $employee->id,
            'day_of_week' => $now->dayOfWeek,
            'start_time' => $now->format('H:i'),
            'end_time' => $now->copy()->addHours(8)->format('H:i'),
            'effective_from' => $now->toDateString(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/attendance/check-in', [
                'mode' => 'qr',
                'badge_code' => 'WAK-TESTBADGE01',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('attendances', [
            'user_id' => $employee->id,
            'source' => 'qr',
        ]);
    }

    public function test_qr_check_in_is_rejected_for_unknown_or_foreign_badge(): void
    {
        $this->seedSettings();

        $now = Carbon::now();
        $employee = User::factory()->create([
            'role' => 'employe',
            'card_uid' => 'WAK-MINE000001',
        ]);
        User::factory()->create([
            'role' => 'employe',
            'card_uid' => 'WAK-OTHER00001',
        ]);

        WorkPattern::create([
            'user_id' => $employee->id,
            'day_of_week' => $now->dayOfWeek,
            'start_time' => $now->format('H:i'),
            'end_time' => $now->copy()->addHours(8)->format('H:i'),
            'effective_from' => $now->toDateString(),
            'is_active' => true,
        ]);

        $this->actingAs($employee, 'sanctum')
            ->postJson('/api/attendance/check-in', [
                'mode' => 'qr',
                'badge_code' => 'WAK-UNKNOWN',
            ])
            ->assertStatus(422);

        $this->actingAs($employee, 'sanctum')
            ->postJson('/api/attendance/check-in', [
                'mode' => 'qr',
                'badge_code' => 'WAK-OTHER00001',
            ])
            ->assertStatus(403);
    }

    public function test_director_can_check_in_without_rigid_work_pattern(): void
    {
        $this->seedSettings();

        $director = User::factory()->create(['role' => 'directeur']);

        $response = $this->actingAs($director, 'sanctum')
            ->postJson('/api/attendance/check-in');

        $response->assertStatus(201);
        $response->assertJsonPath('attendance.status', 'on_time');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $director->id,
            'status' => 'on_time',
        ]);
    }

    public function test_check_in_with_ipv6_mapped_ipv4_matches_allowed_ipv4(): void
    {
        CompanySetting::create(['key' => 'allowed_ips', 'value' => ['192.168.1.50']]);

        $employee = User::factory()->create(['role' => 'employe']);
        $now = Carbon::now();
        WorkPattern::create([
            'user_id' => $employee->id,
            'day_of_week' => $now->dayOfWeek,
            'start_time' => $now->format('H:i'),
            'end_time' => $now->copy()->addHours(8)->format('H:i'),
            'effective_from' => $now->toDateString(),
            'is_active' => true,
        ]);

        // Simule une requête avec IP mappée en IPv6 (::ffff:192.168.1.50)
        $response = $this->actingAs($employee, 'sanctum')
            ->withServerVariables(['REMOTE_ADDR' => '::ffff:192.168.1.50'])
            ->postJson('/api/attendance/check-in');

        $response->assertStatus(201);
    }

    public function test_check_in_with_cidr_subnet_matches_allowed_ips(): void
    {
        CompanySetting::create(['key' => 'allowed_ips', 'value' => ['192.168.1.0/24']]);

        $employee = User::factory()->create(['role' => 'employe']);
        $now = Carbon::now();
        WorkPattern::create([
            'user_id' => $employee->id,
            'day_of_week' => $now->dayOfWeek,
            'start_time' => $now->format('H:i'),
            'end_time' => $now->copy()->addHours(8)->format('H:i'),
            'effective_from' => $now->toDateString(),
            'is_active' => true,
        ]);

        // IP cliente dans le sous-réseau Wi-Fi 192.168.1.42
        $response = $this->actingAs($employee, 'sanctum')
            ->withServerVariables(['REMOTE_ADDR' => '192.168.1.42'])
            ->postJson('/api/attendance/check-in');

        $response->assertStatus(201);

        // IP hors du sous-réseau
        $employee2 = User::factory()->create(['role' => 'employe']);
        WorkPattern::create([
            'user_id' => $employee2->id,
            'day_of_week' => $now->dayOfWeek,
            'start_time' => $now->format('H:i'),
            'end_time' => $now->copy()->addHours(8)->format('H:i'),
            'effective_from' => $now->toDateString(),
            'is_active' => true,
        ]);

        $responseOutside = $this->actingAs($employee2, 'sanctum')
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->postJson('/api/attendance/check-in');

        $responseOutside->assertStatus(403);
    }
}