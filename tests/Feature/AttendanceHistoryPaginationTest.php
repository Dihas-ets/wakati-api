<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceHistoryPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_attendances(): void
    {
        $response = $this->getJson('/api/attendances');
        $response->assertStatus(401);
    }

    public function test_employee_can_fetch_own_attendances_only(): void
    {
        $employee1 = User::factory()->create(['role' => 'employe']);
        $employee2 = User::factory()->create(['role' => 'employe']);

        Attendance::create([
            'user_id' => $employee1->id,
            'date' => Carbon::today()->toDateString(),
            'check_in_time' => '08:00:00',
            'status' => 'on_time',
        ]);

        Attendance::create([
            'user_id' => $employee2->id,
            'date' => Carbon::today()->toDateString(),
            'check_in_time' => '08:15:00',
            'status' => 'on_time',
        ]);

        $response = $this->actingAs($employee1, 'sanctum')
            ->getJson('/api/attendances');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals($employee1->id, $data[0]['user_id']);
    }

    public function test_rh_or_directeur_can_filter_attendances_by_user_id_and_date(): void
    {
        $rh = User::factory()->create(['role' => 'rh']);
        $employee = User::factory()->create(['role' => 'employe']);

        Attendance::create([
            'user_id' => $employee->id,
            'date' => '2026-10-01',
            'check_in_time' => '08:00:00',
            'status' => 'on_time',
        ]);

        Attendance::create([
            'user_id' => $employee->id,
            'date' => '2026-10-10',
            'check_in_time' => '08:30:00',
            'status' => 'late',
        ]);

        $response = $this->actingAs($rh, 'sanctum')
            ->getJson("/api/attendances?user_id={$employee->id}&from=2026-10-05&to=2026-10-15");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals('late', $data[0]['status']);
    }
}
