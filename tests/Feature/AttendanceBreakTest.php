<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceBreakTest extends TestCase
{
    use RefreshDatabase;

    private function createCheckedInAttendance(User $user, int $minutesAgo = 60): Attendance
    {
        $tz = CompanySetting::get('timezone', config('app.timezone'));
        $checkIn = Carbon::now($tz)->subMinutes($minutesAgo);

        return Attendance::create([
            'user_id' => $user->id,
            'date' => $checkIn->toDateString(),
            'check_in_time' => $checkIn,
            'status' => 'on_time',
            'source' => 'online',
        ]);
    }

    public function test_cannot_start_break_without_check_in(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/attendance/break-start');

        $response->assertStatus(422)
            ->assertJsonPath('message', "Vous n'avez pas encore pointé votre arrivée aujourd'hui.");
    }

    public function test_can_start_and_end_break_successfully(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);
        $attendance = $this->createCheckedInAttendance($employee, 120);

        // 1. Début de pause
        $startRes = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/attendance/break-start');

        $startRes->assertOk()
            ->assertJsonPath('attendance.is_on_break', true);

        $this->assertTrue($attendance->fresh()->is_on_break);
        $this->assertNotNull($attendance->fresh()->current_break_start);

        // 2. Tenter de redémarrer une pause alors qu'on est déjà en pause
        $duplicateStart = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/attendance/break-start');
        $duplicateStart->assertStatus(422);

        // Simuler une pause de 30 minutes
        $attendance->fresh()->update([
            'current_break_start' => Carbon::now()->subMinutes(30),
        ]);

        // 3. Fin de pause
        $endRes = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/attendance/break-end');

        $endRes->assertOk()
            ->assertJsonPath('attendance.is_on_break', false);

        $fresh = $attendance->fresh();
        $this->assertFalse($fresh->is_on_break);
        $this->assertNull($fresh->current_break_start);
        $this->assertGreaterThanOrEqual(30, $fresh->total_break_minutes);
        $this->assertCount(1, $fresh->break_history);
    }

    public function test_check_out_auto_closes_ongoing_break(): void
    {
        CompanySetting::create(['key' => 'min_checkout_delay_minutes', 'value' => 0]);

        $employee = User::factory()->create(['role' => 'employe']);
        $attendance = $this->createCheckedInAttendance($employee, 90);

        // Mettre en pause depuis 20 minutes
        $attendance->update([
            'is_on_break' => true,
            'current_break_start' => Carbon::now()->subMinutes(20),
        ]);

        // Pointer le départ
        $res = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/attendance/check-out');

        $res->assertOk();

        $fresh = $attendance->fresh();
        $this->assertFalse($fresh->is_on_break);
        $this->assertNull($fresh->current_break_start);
        $this->assertNotNull($fresh->check_out_time);
        $this->assertGreaterThanOrEqual(20, $fresh->total_break_minutes);
    }
}
