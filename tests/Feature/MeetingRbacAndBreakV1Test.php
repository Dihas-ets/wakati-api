<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\TimeBreak;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingRbacAndBreakV1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CompanySetting::create(['key' => 'timezone', 'value' => 'Africa/Porto-Novo']);
    }

    public function test_v1_break_endpoints_create_time_break_and_deduct_effective_time(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);
        $now = Carbon::now('Africa/Porto-Novo');

        $attendance = Attendance::create([
            'user_id' => $employee->id,
            'date' => $now->toDateString(),
            'check_in_time' => $now->copy()->subHours(4),
            'status' => 'on_time',
            'source' => 'online',
        ]);

        // 1. Démarrer une pause via /api/v1/breaks/start
        $startRes = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/breaks/start', ['break_type' => 'lunch']);

        $startRes->assertOk()
            ->assertJsonPath('attendance.is_on_break', true)
            ->assertJsonPath('time_break.break_type', 'lunch');

        $this->assertDatabaseHas('time_breaks', [
            'user_id' => $employee->id,
            'break_type' => 'lunch',
            'break_end' => null,
        ]);

        // Simuler 45 minutes de pause
        $activeBreak = TimeBreak::where('user_id', $employee->id)->whereNull('break_end')->first();
        $activeBreak->update(['break_start' => $now->copy()->subMinutes(45)]);
        $attendance->update(['current_break_start' => $now->copy()->subMinutes(45)]);

        // 2. Vérifier l'état actuel via /api/v1/breaks/current
        $statusRes = $this->actingAs($employee, 'sanctum')
            ->getJson('/api/v1/breaks/current');

        $statusRes->assertOk()
            ->assertJsonPath('is_on_break', true)
            ->assertJsonPath('has_checked_in', true);

        // 3. Terminer la pause via /api/v1/breaks/end
        $endRes = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/breaks/end');

        $endRes->assertOk()
            ->assertJsonPath('attendance.is_on_break', false);

        $fresh = $attendance->fresh();
        $this->assertFalse($fresh->is_on_break);
        $this->assertGreaterThanOrEqual(45, $fresh->total_break_minutes);

        // Vérifier que le temps effectif déduit bien les pauses
        // 4 heures (240 min) - 45 min pause = ~195 min effectives
        $this->assertLessThan(240, $fresh->effective_work_minutes);
        $this->assertGreaterThan(0, $fresh->effective_work_minutes);
        $this->assertNotEmpty($fresh->effective_work_duration);
    }

    public function test_employee_cannot_create_meeting(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/meetings', [
                'title' => 'Tentative Réunion Employé',
                'date' => Carbon::tomorrow()->toDateString(),
                'time' => '14:00',
                'location' => 'Salle B',
                'agenda' => 'Ordre du jour non autorisé',
            ]);

        $response->assertStatus(403);
    }

    public function test_rh_or_director_can_create_meeting(): void
    {
        $rh = User::factory()->create(['role' => 'rh']);

        $response = $this->actingAs($rh, 'sanctum')
            ->postJson('/api/meetings', [
                'title' => 'Comité de Direction',
                'date' => Carbon::tomorrow()->toDateString(),
                'time' => '09:30',
                'location' => 'Salle Principale',
                'agenda' => 'Bilan stratégique mensuel',
                'drh_notes' => 'Notes RH confidentielles sur la masse salariale',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('title', 'Comité de Direction');

        $this->assertDatabaseHas('meetings', [
            'title' => 'Comité de Direction',
            'redactor_id' => $rh->id,
        ]);
    }

    public function test_employee_cannot_view_confidential_drh_notes(): void
    {
        $rh = User::factory()->create(['role' => 'rh']);
        $employee = User::factory()->create(['role' => 'employe']);

        $meeting = Meeting::create([
            'title' => 'Réunion Trimestrielle',
            'date' => Carbon::today()->toDateString(),
            'time' => '11:00',
            'location' => 'Salle Conseil',
            'redactor_id' => $rh->id,
            'agenda' => 'Revue des objectifs',
            'drh_notes' => 'SECRET_DRH_CONFIDENTIEL',
            'status' => 'convoked',
        ]);

        MeetingParticipant::create([
            'meeting_id' => $meeting->id,
            'user_id' => $employee->id,
            'attendance_status' => 'invited',
        ]);

        // L'employé consulte les détails
        $resEmployee = $this->actingAs($employee, 'sanctum')
            ->getJson("/api/meetings/{$meeting->id}");

        $resEmployee->assertOk();
        $this->assertNull($resEmployee->json('drh_notes'));

        // Les RH consultent les détails
        $resRh = $this->actingAs($rh, 'sanctum')
            ->getJson("/api/meetings/{$meeting->id}");

        $resRh->assertOk();
        $this->assertEquals('SECRET_DRH_CONFIDENTIEL', $resRh->json('drh_notes'));
    }

    public function test_employee_cannot_pilot_live_session(): void
    {
        $rh = User::factory()->create(['role' => 'rh']);
        $employee = User::factory()->create(['role' => 'employe']);

        $meeting = Meeting::create([
            'title' => 'Point d’équipe',
            'date' => Carbon::today()->toDateString(),
            'time' => '10:00',
            'location' => 'Salle Open',
            'redactor_id' => $rh->id,
            'agenda' => 'Avancement projets',
            'status' => 'convoked',
        ]);

        MeetingParticipant::create([
            'meeting_id' => $meeting->id,
            'user_id' => $employee->id,
            'attendance_status' => 'invited',
        ]);

        $res = $this->actingAs($employee, 'sanctum')
            ->postJson("/api/meetings/{$meeting->id}/start");

        $res->assertStatus(403);
        $this->assertEquals('convoked', $meeting->fresh()->status);
    }

    public function test_employee_cannot_download_docx(): void
    {
        $rh = User::factory()->create(['role' => 'rh']);
        $employee = User::factory()->create(['role' => 'employe']);

        $meeting = Meeting::create([
            'title' => 'Fiche Officielle Test',
            'date' => Carbon::today()->toDateString(),
            'time' => '10:00',
            'location' => 'Salle Direction',
            'redactor_id' => $rh->id,
            'agenda' => 'Validation procès-verbal',
            'status' => 'completed',
        ]);

        MeetingParticipant::create([
            'meeting_id' => $meeting->id,
            'user_id' => $employee->id,
            'attendance_status' => 'present',
        ]);

        $genRes = $this->actingAs($employee, 'sanctum')
            ->postJson("/api/meetings/{$meeting->id}/generate-docx");
        $genRes->assertStatus(403);

        $downloadRes = $this->actingAs($employee, 'sanctum')
            ->get("/api/meetings/{$meeting->id}/download-docx");
        $downloadRes->assertStatus(403);
    }

    public function test_employee_can_self_check_in_on_meeting_day(): void
    {
        $rh = User::factory()->create(['role' => 'rh']);
        $employee = User::factory()->create(['role' => 'employe']);

        $meeting = Meeting::create([
            'title' => 'Réunion Aujourd’hui',
            'date' => Carbon::today()->toDateString(),
            'time' => '09:00',
            'location' => 'Auditorium',
            'redactor_id' => $rh->id,
            'agenda' => 'Ordre du jour Jour J',
            'status' => 'convoked',
        ]);

        $mp = MeetingParticipant::create([
            'meeting_id' => $meeting->id,
            'user_id' => $employee->id,
            'attendance_status' => 'invited',
        ]);

        $res = $this->actingAs($employee, 'sanctum')
            ->postJson("/api/meetings/{$meeting->id}/self-check-in", ['method' => 'web']);

        $res->assertOk()
            ->assertJsonPath('participant.attendance_status', 'present');

        $this->assertEquals('present', $mp->fresh()->attendance_status);
        $this->assertNotNull($mp->fresh()->checked_in_at);
    }
}
