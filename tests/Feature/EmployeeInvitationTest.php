<?php

namespace Tests\Feature;

use App\Mail\EmployeeInvitationMail;
use App\Models\Department;
use App\Models\EmployeeInvitation;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmployeeInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rh_can_send_invitation_with_queued_email(): void
    {
        Mail::fake();

        $rh = User::factory()->create([
            'role' => User::ROLE_RH,
        ]);

        $department = Department::create(['name' => 'Ingénierie Test']);
        $position = Position::create([
            'department_id' => $department->id,
            'title' => 'Développeur FullStack Test',
            'permissions' => ['employe'],
        ]);

        $response = $this->actingAs($rh)->postJson('/api/invitations/send', [
            'email' => 'nouveau.collab@wakati.test',
            'first_name' => 'Marc',
            'last_name' => 'Koffi',
            'department_id' => $department->id,
            'position_id' => $position->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'invitation' => [
                    'id',
                    'email',
                    'token',
                    'status',
                    'expires_at',
                ],
            ]);

        $this->assertDatabaseHas('employee_invitations', [
            'email' => 'nouveau.collab@wakati.test',
            'first_name' => 'Marc',
            'last_name' => 'Koffi',
            'status' => 'pending',
            'position_id' => $position->id,
        ]);

        // Vérification de l'envoi asynchrone (Queue)
        Mail::assertQueued(EmployeeInvitationMail::class, function ($mail) {
            return $mail->hasTo('nouveau.collab@wakati.test')
                && $mail->invitation->first_name === 'Marc';
        });
    }

    public function test_regular_employee_cannot_send_invitation(): void
    {
        $employee = User::factory()->create([
            'role' => User::ROLE_EMPLOYE,
        ]);

        $response = $this->actingAs($employee)->postJson('/api/invitations/send', [
            'email' => 'forbidden@wakati.test',
            'first_name' => 'Test',
            'last_name' => 'User',
            'position_id' => 1,
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_invite_existing_user_email(): void
    {
        $rh = User::factory()->create([
            'role' => User::ROLE_RH,
        ]);

        User::factory()->create([
            'email' => 'deja.inscrit@wakati.test',
        ]);

        $department = Department::create(['name' => 'Finance Test']);
        $position = Position::create([
            'department_id' => $department->id,
            'title' => 'Comptable Test',
        ]);

        $response = $this->actingAs($rh)->postJson('/api/invitations/send', [
            'email' => 'deja.inscrit@wakati.test',
            'first_name' => 'Paul',
            'last_name' => 'Sossa',
            'department_id' => $department->id,
            'position_id' => $position->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_public_verify_token_endpoint(): void
    {
        $department = Department::create(['name' => 'Design Test']);
        $position = Position::create([
            'department_id' => $department->id,
            'title' => 'Designer UI/UX',
        ]);

        $invitation = EmployeeInvitation::create([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'email' => 'designer@wakati.test',
            'first_name' => 'Amina',
            'last_name' => 'Tidjani',
            'token' => 'secure_test_token_1234567890',
            'status' => 'pending',
            'expires_at' => now()->addHours(48),
        ]);

        // Token valide
        $response = $this->getJson('/api/invitations/verify/secure_test_token_1234567890');
        $response->assertStatus(200)
            ->assertJson([
                'valid' => true,
                'email' => 'designer@wakati.test',
                'first_name' => 'Amina',
                'last_name' => 'Tidjani',
                'department_name' => 'Design Test',
                'position_title' => 'Designer UI/UX',
            ]);

        // Token inexistant
        $this->getJson('/api/invitations/verify/invalid_token')
            ->assertStatus(404);

        // Token expiré
        $invitation->update(['expires_at' => now()->subHour()]);
        $this->getJson('/api/invitations/verify/secure_test_token_1234567890')
            ->assertStatus(400)
            ->assertJsonFragment(['message' => "Ce lien d'invitation a expiré (validité 48 heures). Veuillez contacter votre administrateur RH."]);
    }

    public function test_public_accept_invitation_creates_user_and_attaches_position(): void
    {
        $department = Department::create(['name' => 'Logistique Test']);
        $position = Position::create([
            'department_id' => $department->id,
            'title' => 'Gestionnaire Flotte Test',
        ]);

        $invitation = EmployeeInvitation::create([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'email' => 'onboarding.success@wakati.test',
            'first_name' => 'Gérard',
            'last_name' => 'Mensah',
            'token' => 'token_accept_test_abc123',
            'status' => 'pending',
            'expires_at' => now()->addHours(48),
        ]);

        $response = $this->postJson('/api/invitations/accept', [
            'token' => 'token_accept_test_abc123',
            'first_name' => 'Gérard',
            'last_name' => 'Mensah',
            'phone' => '+229 01 98 76 54',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'positions',
                ],
            ]);

        // Vérification dans la table users
        $this->assertDatabaseHas('users', [
            'email' => 'onboarding.success@wakati.test',
            'name' => 'Gérard Mensah',
            'phone' => '+229 01 98 76 54',
            'role' => User::ROLE_EMPLOYE,
            'department_id' => $department->id,
        ]);

        $createdUser = User::where('email', 'onboarding.success@wakati.test')->first();
        $this->assertTrue(Hash::check('SecurePass123!', $createdUser->password));

        // Vérification du rattachement dans user_positions
        $this->assertDatabaseHas('user_positions', [
            'user_id' => $createdUser->id,
            'position_id' => $position->id,
            'is_primary' => 1,
        ]);

        // Statut de l'invitation passé à accepted
        $this->assertDatabaseHas('employee_invitations', [
            'id' => $invitation->id,
            'status' => 'accepted',
        ]);

        // Tentative de réutilisation du même token -> refusée
        $this->postJson('/api/invitations/accept', [
            'token' => 'token_accept_test_abc123',
            'first_name' => 'Gérard',
            'last_name' => 'Mensah',
            'phone' => '+229 01 98 76 54',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ])->assertStatus(400);
    }
}
