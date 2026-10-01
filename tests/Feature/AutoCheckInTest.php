<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\User;
use App\Models\WorkPattern;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoCheckInTest extends TestCase
{
    use RefreshDatabase;

    private function seedActiveWorkPattern(User $employee, Carbon $now): void
    {
        WorkPattern::create([
            'user_id' => $employee->id,
            'day_of_week' => $now->dayOfWeek,
            'start_time' => $now->format('H:i'),
            'end_time' => $now->copy()->addHours(8)->format('H:i'),
            'effective_from' => $now->toDateString(),
            'is_active' => true,
        ]);
    }

    public function test_auto_checkin_is_rejected_when_mode_is_disabled(): void
    {
        CompanySetting::create(['key' => 'auto_checkin_mode', 'value' => 'disabled']);
        CompanySetting::create(['key' => 'allowed_ips', 'value' => ['127.0.0.1']]);

        $employee = User::factory()->create(['role' => 'employe']);
        $this->seedActiveWorkPattern($employee, Carbon::now());

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/attendance/check-in', ['mode' => 'auto']);

        $response->assertStatus(422);
    }

    public function test_auto_checkin_succeeds_when_mode_is_full_auto(): void
    {
        CompanySetting::create(['key' => 'auto_checkin_mode', 'value' => 'full_auto']);
        CompanySetting::create(['key' => 'allowed_ips', 'value' => ['127.0.0.1']]);

        $employee = User::factory()->create(['role' => 'employe']);
        $this->seedActiveWorkPattern($employee, Carbon::now());

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/attendance/check-in', ['mode' => 'auto']);

        $response->assertStatus(201);
        $this->assertDatabaseHas('attendances', [
            'user_id' => $employee->id,
            'source' => 'auto',
        ]);
    }

    public function test_auto_checkin_succeeds_when_mode_is_confirm_required(): void
    {
        // confirm_required autorise l'appel côté serveur : c'est le client qui
        // décide de demander confirmation avant d'appeler — le serveur ne
        // distingue pas confirm_required de full_auto sur ce point.
        CompanySetting::create(['key' => 'auto_checkin_mode', 'value' => 'confirm_required']);
        CompanySetting::create(['key' => 'allowed_ips', 'value' => ['127.0.0.1']]);

        $employee = User::factory()->create(['role' => 'employe']);
        $this->seedActiveWorkPattern($employee, Carbon::now());

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/attendance/check-in', ['mode' => 'auto']);

        $response->assertStatus(201);
    }

    public function test_auto_checkin_still_enforces_ip_whitelist(): void
    {
        CompanySetting::create(['key' => 'auto_checkin_mode', 'value' => 'full_auto']);
        CompanySetting::create(['key' => 'allowed_ips', 'value' => ['203.0.113.10']]);

        $employee = User::factory()->create(['role' => 'employe']);
        $this->seedActiveWorkPattern($employee, Carbon::now());

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/attendance/check-in', ['mode' => 'auto']);

        $response->assertStatus(403);
    }

    public function test_auto_checkin_stores_geolocation_when_provided(): void
    {
        CompanySetting::create(['key' => 'auto_checkin_mode', 'value' => 'full_auto']);
        CompanySetting::create(['key' => 'allowed_ips', 'value' => ['127.0.0.1']]);

        $employee = User::factory()->create(['role' => 'employe']);
        $this->seedActiveWorkPattern($employee, Carbon::now());

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/attendance/check-in', [
                'mode' => 'auto',
                'latitude' => 6.4969,
                'longitude' => 2.6289,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('attendances', [
            'user_id' => $employee->id,
            'latitude' => 6.4969,
            'longitude' => 2.6289,
        ]);
    }

    public function test_manual_checkin_still_uses_online_source(): void
    {
        CompanySetting::create(['key' => 'allowed_ips', 'value' => ['127.0.0.1']]);

        $employee = User::factory()->create(['role' => 'employe']);
        $this->seedActiveWorkPattern($employee, Carbon::now());

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/attendance/check-in');

        $response->assertStatus(201);
        $this->assertDatabaseHas('attendances', [
            'user_id' => $employee->id,
            'source' => 'online',
        ]);
    }
}