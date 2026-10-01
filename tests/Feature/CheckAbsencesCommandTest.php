<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\Leave;
use App\Models\User;
use App\Models\WorkPattern;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckAbsencesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function seedAbsenceDelay(int $minutes = 60): void
    {
        CompanySetting::create(['key' => 'absence_delay_minutes', 'value' => $minutes]);
    }

    public function test_absence_is_created_when_work_pattern_start_is_past_the_delay_without_attendance(): void
    {
        $this->seedAbsenceDelay(60);

        $now = Carbon::now();
        $employee = User::factory()->create(['role' => 'employe']);

        WorkPattern::create([
            'user_id' => $employee->id,
            'day_of_week' => $now->dayOfWeek,
            'start_time' => $now->copy()->subMinutes(90)->format('H:i'),
            'end_time' => $now->copy()->addHours(4)->format('H:i'),
            'effective_from' => $now->toDateString(),
            'is_active' => true,
        ]);

        $this->artisan('attendance:check-absences')->assertExitCode(0);

        $this->assertDatabaseHas('absences', [
            'user_id' => $employee->id,
            'status' => 'unexcused',
        ]);
    }

    public function test_no_absence_is_created_when_attendance_already_exists(): void
    {
        $this->seedAbsenceDelay(60);

        $now = Carbon::now();
        $employee = User::factory()->create(['role' => 'employe']);

        WorkPattern::create([
            'user_id' => $employee->id,
            'day_of_week' => $now->dayOfWeek,
            'start_time' => $now->copy()->subMinutes(90)->format('H:i'),
            'end_time' => $now->copy()->addHours(4)->format('H:i'),
            'effective_from' => $now->toDateString(),
            'is_active' => true,
        ]);

        Attendance::create([
            'user_id' => $employee->id,
            'date' => $now->toDateString(),
            'check_in_time' => $now->copy()->subMinutes(85),
            'status' => 'on_time',
            'ip_address' => '127.0.0.1',
        ]);

        $this->artisan('attendance:check-absences')->assertExitCode(0);

        $this->assertDatabaseMissing('absences', ['user_id' => $employee->id]);
    }

    public function test_no_absence_is_created_when_employee_is_on_leave(): void
    {
        $this->seedAbsenceDelay(60);

        $now = Carbon::now();
        $employee = User::factory()->create(['role' => 'employe']);

        WorkPattern::create([
            'user_id' => $employee->id,
            'day_of_week' => $now->dayOfWeek,
            'start_time' => $now->copy()->subMinutes(90)->format('H:i'),
            'end_time' => $now->copy()->addHours(4)->format('H:i'),
            'effective_from' => $now->toDateString(),
            'is_active' => true,
        ]);

        Leave::create([
            'user_id' => $employee->id,
            'leave_type' => 'maladie',
            'date' => $now->toDateString(),
            'work_mode' => 'presentiel',
        ]);

        $this->artisan('attendance:check-absences')->assertExitCode(0);

        $this->assertDatabaseMissing('absences', ['user_id' => $employee->id]);
    }

    public function test_no_absence_is_created_before_the_delay_has_elapsed(): void
    {
        $this->seedAbsenceDelay(60);

        $now = Carbon::now();
        $employee = User::factory()->create(['role' => 'employe']);

        WorkPattern::create([
            'user_id' => $employee->id,
            'day_of_week' => $now->dayOfWeek,
            'start_time' => $now->copy()->subMinutes(20)->format('H:i'), // < 60 min de délai
            'end_time' => $now->copy()->addHours(4)->format('H:i'),
            'effective_from' => $now->toDateString(),
            'is_active' => true,
        ]);

        $this->artisan('attendance:check-absences')->assertExitCode(0);

        $this->assertDatabaseMissing('absences', ['user_id' => $employee->id]);
    }
}