<?php

namespace Tests\Feature;

use App\Models\ExplanationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExplanationRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_rh_sees_all_explanation_requests(): void
    {
        $rh = User::factory()->create(['role' => 'rh']);
        $employee = User::factory()->create(['role' => 'employe']);

        ExplanationRequest::create([
            'user_id' => $employee->id,
            'date' => now()->toDateString(),
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($rh, 'sanctum')->getJson('/api/explanation-requests');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    public function test_employee_only_sees_their_own_explanation_requests(): void
    {
        $employeeA = User::factory()->create(['role' => 'employe']);
        $employeeB = User::factory()->create(['role' => 'employe']);

        ExplanationRequest::create(['user_id' => $employeeA->id, 'date' => now()->toDateString(), 'sent_at' => now()]);
        ExplanationRequest::create(['user_id' => $employeeB->id, 'date' => now()->toDateString(), 'sent_at' => now()]);

        $response = $this->actingAs($employeeA, 'sanctum')->getJson('/api/explanation-requests');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    public function test_employee_can_respond_to_their_own_request(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);
        $request = ExplanationRequest::create([
            'user_id' => $employee->id,
            'date' => now()->toDateString(),
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson("/api/explanation-requests/{$request->id}/respond", [
                'response' => 'Embouteillage inhabituel ce matin.',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('explanation_requests', [
            'id' => $request->id,
            'response' => 'Embouteillage inhabituel ce matin.',
        ]);
    }

    public function test_employee_cannot_respond_to_someone_elses_request(): void
    {
        $employeeA = User::factory()->create(['role' => 'employe']);
        $employeeB = User::factory()->create(['role' => 'employe']);

        $request = ExplanationRequest::create([
            'user_id' => $employeeB->id,
            'date' => now()->toDateString(),
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($employeeA, 'sanctum')
            ->postJson("/api/explanation-requests/{$request->id}/respond", [
                'response' => 'Tentative non autorisée.',
            ]);

        $response->assertStatus(403);
    }
}