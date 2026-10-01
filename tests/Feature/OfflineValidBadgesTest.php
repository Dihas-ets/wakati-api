<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfflineValidBadgesTest extends TestCase
{
    use RefreshDatabase;

    private const SYNC_SECRET = 'test-offline-sync-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.offline_sync.secret' => self::SYNC_SECRET]);
    }

    public function test_rejects_request_without_valid_sync_secret(): void
    {
        $response = $this->getJson('/api/attendance/valid-badges');

        $response->assertStatus(401);
    }

    public function test_returns_card_uid_to_user_mapping_with_names(): void
    {
        $paired = User::factory()->create(['role' => 'employe', 'card_uid' => 'CARD-0001', 'name' => 'Jean Dupont']);
        User::factory()->create(['role' => 'employe', 'card_uid' => null]);

        $response = $this->getJson('/api/attendance/valid-badges', [
            'X-Sync-Secret' => self::SYNC_SECRET,
        ]);

        $response->assertOk();
        $response->assertJson([
            'valid_badges' => [
                'CARD-0001' => ['user_id' => $paired->id, 'name' => 'Jean Dupont'],
            ],
        ]);
        $this->assertCount(1, $response->json('valid_badges'));
    }
}