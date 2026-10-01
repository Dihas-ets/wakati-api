<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use App\Models\WorkPattern;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineSyncFlowTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-offline-sync-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.offline_sync.secret' => self::SECRET]);

        // Lundi 28 septembre 2026, 10:00 (fuseau de l'application).
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function headers(): array
    {
        return ['X-Sync-Secret' => self::SECRET];
    }

    private function at(string $time): string
    {
        return Carbon::parse("2026-09-28 {$time}:00", config('app.timezone'))->toIso8601String();
    }

    private function employeeWithMondayPattern(string $cardUid): User
    {
        $employee = User::factory()->create(['role' => 'employe', 'card_uid' => $cardUid]);

        WorkPattern::create([
            'user_id' => $employee->id,
            'day_of_week' => 1, // lundi
            'start_time' => '09:00',
            'end_time' => '17:00',
            'effective_from' => '2026-09-01',
            'is_active' => true,
        ]);

        return $employee;
    }

    public function test_sync_routes_are_refused_when_the_server_secret_is_not_configured(): void
    {
        // Cause du bug "la synchro ne marche pas" : OFFLINE_SYNC_SECRET absent du .env.
        config(['services.offline_sync.secret' => null]);

        $this->getJson('/api/attendance/valid-badges', ['X-Sync-Secret' => ''])->assertStatus(401);
        $this->getJson('/api/attendance/valid-badges', ['X-Sync-Secret' => 'nimporte-quoi'])->assertStatus(401);
        $this->postJson('/api/attendance/sync-offline', ['scans' => []], ['X-Sync-Secret' => 'nimporte-quoi'])
            ->assertStatus(401);
    }

    public function test_the_earliest_scan_of_the_day_wins_whatever_the_arrival_order(): void
    {
        $employee = $this->employeeWithMondayPattern('WAK-TEST000001');

        $early = (string) Str::uuid();
        $late = (string) Str::uuid();

        // Envoyés dans le désordre (IndexedDB trie par identifiant, pas par heure).
        $response = $this->postJson('/api/attendance/sync-offline', [
            'scans' => [
                ['card_uid' => 'WAK-TEST000001', 'scanned_at' => $this->at('09:30'), 'client_scan_id' => $late],
                ['card_uid' => 'WAK-TEST000001', 'scanned_at' => $this->at('08:50'), 'client_scan_id' => $early],
            ],
        ], $this->headers());

        $response->assertOk();

        $byId = collect($response->json('results'))->keyBy('client_scan_id');
        $this->assertSame('recorded', $byId[$early]['status']);
        $this->assertSame('duplicate_day', $byId[$late]['status']);

        $attendance = Attendance::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame('08:50', $attendance->check_in_time->format('H:i'));
        $this->assertSame('on_time', $attendance->status);
    }

    public function test_badge_is_recognised_whatever_the_reader_format(): void
    {
        $this->employeeWithMondayPattern('04A1B2C3');

        // Web NFC (Android) renvoie "04:a1:b2:c3" pour le même badge.
        $response = $this->postJson('/api/attendance/sync-offline', [
            'scans' => [[
                'card_uid' => '04:a1:b2:c3',
                'scanned_at' => $this->at('08:55'),
                'client_scan_id' => (string) Str::uuid(),
            ]],
        ], $this->headers());

        $response->assertOk();
        $this->assertSame('recorded', $response->json('results.0.status'));
    }

    public function test_valid_badges_are_returned_with_normalised_keys(): void
    {
        $user = User::factory()->create(['role' => 'employe', 'card_uid' => '04:a1:b2:c3', 'name' => 'Jean Dupont']);

        $response = $this->getJson('/api/attendance/valid-badges', $this->headers());

        $response->assertOk();
        $response->assertJsonPath('valid_badges.04A1B2C3.user_id', $user->id);
        $response->assertJsonPath('valid_badges.04A1B2C3.name', 'Jean Dupont');
    }

    public function test_unknown_badge_is_reported_back_and_not_recorded(): void
    {
        $id = (string) Str::uuid();

        $response = $this->postJson('/api/attendance/sync-offline', [
            'scans' => [['card_uid' => 'WAK-INCONNU0001', 'scanned_at' => $this->at('08:55'), 'client_scan_id' => $id]],
        ], $this->headers());

        $response->assertOk();
        $response->assertJsonPath('results.0.client_scan_id', $id);
        $response->assertJsonPath('results.0.status', 'unknown_badge');
        $this->assertSame(0, Attendance::count());
    }

    public function test_card_uid_normalisation_rules(): void
    {
        $this->assertSame('04A1B2C3', User::normalizeCardUid('04:a1:b2:c3'));
        $this->assertSame('04A1B2C3D4E5F6', User::normalizeCardUid('04-A1-B2-C3-D4-E5-F6'));
        $this->assertSame('04A1B2C3', User::normalizeCardUid('04A1B2C3'));
        $this->assertSame('WAK-55CFA57334', User::normalizeCardUid(' wak-55cfa57334 '));
    }
}
