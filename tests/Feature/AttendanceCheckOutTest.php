<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceCheckOutTest extends TestCase
{
    use RefreshDatabase;

    private function attendanceCheckedInMinutesAgo(User $user, int $minutes): Attendance
    {
        $tz = CompanySetting::get('timezone', config('app.timezone'));
        $checkIn = Carbon::now($tz)->subMinutes($minutes);

        return Attendance::create([
            'user_id' => $user->id,
            'date' => $checkIn->toDateString(),
            'check_in_time' => $checkIn,
            'status' => 'on_time',
            'source' => 'online',
        ]);
    }

    public function test_check_out_right_after_check_in_is_rejected(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);
        $attendance = $this->attendanceCheckedInMinutesAgo($employee, 1);

        $this->actingAs($employee, 'sanctum')
            ->postJson('/api/attendance/check-out')
            ->assertStatus(422);

        $this->assertNull($attendance->fresh()->check_out_time);
    }

    public function test_check_out_after_minimum_delay_is_accepted(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);
        $attendance = $this->attendanceCheckedInMinutesAgo($employee, 30);

        $this->actingAs($employee, 'sanctum')
            ->postJson('/api/attendance/check-out')
            ->assertOk();

        $this->assertNotNull($attendance->fresh()->check_out_time);
    }

    public function test_minimum_delay_is_configurable_and_can_be_disabled(): void
    {
        CompanySetting::create(['key' => 'min_checkout_delay_minutes', 'value' => 0]);

        $employee = User::factory()->create(['role' => 'employe']);
        $this->attendanceCheckedInMinutesAgo($employee, 0);

        $this->actingAs($employee, 'sanctum')
            ->postJson('/api/attendance/check-out')
            ->assertOk();
    }

    public function test_second_check_out_is_still_rejected(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);
        $this->attendanceCheckedInMinutesAgo($employee, 30);

        $this->actingAs($employee, 'sanctum')->postJson('/api/attendance/check-out')->assertOk();
        $this->actingAs($employee, 'sanctum')->postJson('/api/attendance/check-out')->assertStatus(422);
    }
}