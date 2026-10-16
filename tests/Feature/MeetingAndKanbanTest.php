<?php

namespace Tests\Feature;

use App\Mail\MeetingConvocationMail;
use App\Mail\MeetingFilteredReportMail;
use App\Models\KanbanTask;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\MeetingTopic;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeetingAndKanbanTest extends TestCase
{
    use RefreshDatabase;

    protected User $director;
    protected User $rhUser;
    protected User $employee1;
    protected User $employee2;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        // Création des utilisateurs de test
        $this->director = User::firstOrCreate(
            ['email' => 'directeur.test@wakati.test'],
            ['name' => 'Directeur Général', 'role' => User::ROLE_DIRECTEUR, 'password' => bcrypt('password')]
        );

        $this->rhUser = User::firstOrCreate(
            ['email' => 'rh.test@wakati.test'],
            ['name' => 'Responsable RH', 'role' => User::ROLE_RH, 'password' => bcrypt('password')]
        );

        $this->employee1 = User::firstOrCreate(
            ['email' => 'employe1.test@wakati.test'],
            ['name' => 'Alice Martin', 'role' => User::ROLE_EMPLOYE, 'password' => bcrypt('password')]
        );

        $this->employee2 = User::firstOrCreate(
            ['email' => 'employe2.test@wakati.test'],
            ['name' => 'Bob Dupont', 'role' => User::ROLE_EMPLOYE, 'password' => bcrypt('password')]
        );
    }

    /**
     * Test 1: Création d'une réunion avec participants, sujets et convocations.
     */
    public function test_can_create_meeting_with_participants_and_topics(): void
    {
        Sanctum::actingAs($this->rhUser);

        $payload = [
            'title' => 'Comité Stratégique Trimestriel',
            'date' => Carbon::today()->toDateString(),
            'time' => '10:00',
            'location' => 'Salle du Conseil A',
            'agenda' => "1. Bilan opérationnel\n2. Validation des effectifs\n3. Objectifs Q4",
            'drh_notes' => 'Notes confidentielles DRH : deux recrutements et trois départs prévus.',
            'participant_ids' => [$this->employee1->id, $this->employee2->id],
            'topics' => [
                [
                    'subject' => 'Refonte de la pointeuse',
                    'current_situation' => 'Pointages parfois lents',
                    'evolution_decision' => 'Déploiement du nouveau lecteur RFID',
                    'responsible_id' => $this->employee1->id,
                    'due_date' => Carbon::today()->addDays(7)->toDateString(),
                ],
            ],
            'send_convocations' => true,
        ];

        $response = $this->postJson('/api/meetings', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('title', 'Comité Stratégique Trimestriel');

        $meetingId = $response->json('id');
        $this->assertDatabaseHas('meetings', ['id' => $meetingId]);
        $this->assertDatabaseHas('meeting_participants', ['meeting_id' => $meetingId, 'user_id' => $this->employee1->id]);
        $this->assertDatabaseHas('meeting_topics', ['meeting_id' => $meetingId, 'subject' => 'Refonte de la pointeuse']);

        // Vérifier l'envoi des mails de convocation
        Mail::assertSent(MeetingConvocationMail::class);
    }

    /**
     * Test 2: Self-check-in autonome de l'employé le Jour J.
     */
    public function test_employee_can_self_check_in_on_meeting_day(): void
    {
        $meeting = Meeting::create([
            'title' => 'Point d\'équipe du matin',
            'date' => Carbon::today()->toDateString(),
            'time' => '09:00',
            'location' => 'Espace Collaboratif',
            'redactor_id' => $this->rhUser->id,
            'agenda' => 'Revue des urgences',
            'status' => Meeting::STATUS_CONVOKED,
        ]);

        MeetingParticipant::create([
            'meeting_id' => $meeting->id,
            'user_id' => $this->employee1->id,
            'attendance_status' => MeetingParticipant::STATUS_INVITED,
        ]);

        Sanctum::actingAs($this->employee1);

        $response = $this->postJson("/api/meetings/{$meeting->id}/self-check-in", [
            'method' => 'web',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Présence validée avec succès !')
            ->assertJsonPath('participant.attendance_status', 'present')
            ->assertJsonPath('participant.checkin_method', 'web');

        $this->assertDatabaseHas('meeting_participants', [
            'meeting_id' => $meeting->id,
            'user_id' => $this->employee1->id,
            'attendance_status' => 'present',
            'checkin_method' => 'web',
        ]);
    }

    /**
     * Test 3: Échec du Self-check-in si la réunion n'a pas lieu aujourd'hui.
     */
    public function test_self_check_in_fails_if_not_today(): void
    {
        $futureMeeting = Meeting::create([
            'title' => 'Réunion Future',
            'date' => Carbon::today()->addDays(5)->toDateString(),
            'time' => '14:00',
            'location' => 'Salle B',
            'redactor_id' => $this->rhUser->id,
            'agenda' => 'Planification',
            'status' => Meeting::STATUS_CONVOKED,
        ]);

        MeetingParticipant::create([
            'meeting_id' => $futureMeeting->id,
            'user_id' => $this->employee1->id,
            'attendance_status' => MeetingParticipant::STATUS_INVITED,
        ]);

        Sanctum::actingAs($this->employee1);

        $response = $this->postJson("/api/meetings/{$futureMeeting->id}/self-check-in");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    /**
     * Test 4: Émargement via QR Code / Badge RFID.
     */
    public function test_check_in_via_badge_uid(): void
    {
        $meeting = Meeting::create([
            'title' => 'Assemblée Générale',
            'date' => Carbon::today()->toDateString(),
            'time' => '15:00',
            'location' => 'Amphithéâtre',
            'redactor_id' => $this->director->id,
            'agenda' => 'Bilan annuel',
            'status' => Meeting::STATUS_ONGOING,
        ]);

        $this->employee2->ensureCardUid();
        $cardUid = $this->employee2->card_uid;

        Sanctum::actingAs($this->rhUser);

        $response = $this->postJson("/api/meetings/{$meeting->id}/check-in", [
            'badge_code' => $cardUid,
            'method' => 'rfid',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('attendance_status', 'present')
            ->assertJsonPath('checkin_method', 'rfid');
    }

    /**
     * Test 5: Calcul automatique des statistiques d'émargement.
     */
    public function test_attendance_stats_calculation(): void
    {
        $meeting = Meeting::create([
            'title' => 'Session Formation',
            'date' => Carbon::today()->toDateString(),
            'time' => '11:00',
            'location' => 'Salle Formation',
            'redactor_id' => $this->rhUser->id,
            'agenda' => 'Sécurité au poste',
            'status' => Meeting::STATUS_ONGOING,
        ]);

        MeetingParticipant::create([
            'meeting_id' => $meeting->id,
            'user_id' => $this->employee1->id,
            'attendance_status' => MeetingParticipant::STATUS_PRESENT,
        ]);

        MeetingParticipant::create([
            'meeting_id' => $meeting->id,
            'user_id' => $this->employee2->id,
            'attendance_status' => MeetingParticipant::STATUS_INVITED,
        ]);

        $stats = $meeting->attendance_stats;

        $this->assertEquals(2, $stats['total_invited']);
        $this->assertEquals(1, $stats['total_present']);
        $this->assertEquals(50.0, $stats['attendance_rate_percent']);
        $this->assertEquals('1 / 2 (50%)', $stats['ratio_label']);
    }

    /**
     * Test 6: Conversion d'un sujet en tâche Kanban et mise à jour de statut.
     */
    public function test_convert_topic_to_kanban_task_and_update_status(): void
    {
        $meeting = Meeting::create([
            'title' => 'Revue de Sprint',
            'date' => Carbon::today()->toDateString(),
            'time' => '10:00',
            'location' => 'Salle Dev',
            'redactor_id' => $this->rhUser->id,
            'agenda' => 'Priorisation des tickets',
            'status' => Meeting::STATUS_ONGOING,
        ]);

        $topic = MeetingTopic::create([
            'meeting_id' => $meeting->id,
            'subject' => 'Mise à niveau du module pointage',
            'current_situation' => 'Version 1.0 en production',
            'evolution_decision' => 'Développer le self-checkin',
            'responsible_id' => $this->employee1->id,
            'due_date' => Carbon::today()->addDays(3)->toDateString(),
        ]);

        Sanctum::actingAs($this->rhUser);

        // 1. Conversion en tâche Kanban
        $convertResponse = $this->postJson("/api/meetings/{$meeting->id}/topics/{$topic->id}/convert-to-task", [
            'assigned_to' => $this->employee1->id,
        ]);

        $convertResponse->assertStatus(201)
            ->assertJsonPath('title', 'Mise à niveau du module pointage')
            ->assertJsonPath('status', 'todo');

        $taskId = $convertResponse->json('id');

        // 2. Mise à jour de statut (Drag & Drop)
        $statusResponse = $this->patchJson("/api/kanban-tasks/{$taskId}/status", [
            'status' => 'in_progress',
        ]);

        $statusResponse->assertStatus(200)
            ->assertJsonPath('status', 'in_progress');

        // 3. Récupération groupée par statut pour le board
        $groupedResponse = $this->getJson('/api/kanban-tasks?grouped=true');
        $groupedResponse->assertStatus(200)
            ->assertJsonStructure(['todo', 'in_progress', 'review', 'done']);
    }

    /**
     * Test 7: Génération de la fiche Word (.docx).
     */
    public function test_docx_generation(): void
    {
        $meeting = Meeting::create([
            'title' => 'Conseil d\'Administration',
            'date' => Carbon::today()->toDateString(),
            'time' => '16:00',
            'location' => 'Siège Social',
            'redactor_id' => $this->director->id,
            'agenda' => 'Validation des budgets et orientations stratégiques.',
            'summary_decisions' => 'Budget validé à l\'unanimité.',
            'drh_notes' => 'Effectifs stables, climat social serein.',
            'status' => Meeting::STATUS_ONGOING,
        ]);

        MeetingParticipant::create([
            'meeting_id' => $meeting->id,
            'user_id' => $this->employee1->id,
            'attendance_status' => MeetingParticipant::STATUS_PRESENT,
            'checked_in_at' => Carbon::now(),
            'checkin_method' => 'web',
        ]);

        MeetingTopic::create([
            'meeting_id' => $meeting->id,
            'subject' => 'Extension des locaux',
            'current_situation' => 'Baux en cours de finalisation',
            'evolution_decision' => 'Signature du bail le 15 du mois',
            'responsible_id' => $this->director->id,
            'due_date' => Carbon::today()->addDays(10)->toDateString(),
        ]);

        Sanctum::actingAs($this->director);

        $response = $this->postJson("/api/meetings/{$meeting->id}/generate-docx");

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'docx_file_path', 'download_url']);

        $meeting->refresh();
        $this->assertNotNull($meeting->docx_file_path);
        $this->assertTrue(File::exists(storage_path('app/' . $meeting->docx_file_path)));
    }

    /**
     * Test 8: Clôture de la réunion et diffusion filtrée des comptes-rendus par e-mail.
     * Vérification de la sécurité : l'employé 1 ne reçoit que ses propres tâches et aucune note DRH.
     */
    public function test_close_and_send_filtered_reports(): void
    {
        $meeting = Meeting::create([
            'title' => 'Session Clôture Projet',
            'date' => Carbon::today()->toDateString(),
            'time' => '17:00',
            'location' => 'Salle D',
            'redactor_id' => $this->director->id,
            'agenda' => 'Bilan final et clôture.',
            'summary_decisions' => 'Projet approuvé pour livraison client.',
            'drh_notes' => 'SECRET : Prime exceptionnelle accordée uniquement à Alice.',
            'status' => Meeting::STATUS_ONGOING,
        ]);

        MeetingParticipant::create([
            'meeting_id' => $meeting->id,
            'user_id' => $this->employee1->id,
            'attendance_status' => MeetingParticipant::STATUS_PRESENT,
        ]);

        MeetingParticipant::create([
            'meeting_id' => $meeting->id,
            'user_id' => $this->employee2->id,
            'attendance_status' => MeetingParticipant::STATUS_PRESENT,
        ]);

        // Tâche assignée à Alice (employee1)
        KanbanTask::create([
            'meeting_id' => $meeting->id,
            'title' => 'Préparer le PV final',
            'assigned_to' => $this->employee1->id,
            'created_by' => $this->director->id,
            'status' => 'todo',
        ]);

        // Tâche assignée à Bob (employee2)
        KanbanTask::create([
            'meeting_id' => $meeting->id,
            'title' => 'Sauvegarder les serveurs',
            'assigned_to' => $this->employee2->id,
            'created_by' => $this->director->id,
            'status' => 'todo',
        ]);

        Sanctum::actingAs($this->director);

        $response = $this->postJson("/api/meetings/{$meeting->id}/close-and-send-reports");

        $response->assertStatus(200)
            ->assertJsonPath('meeting.status', 'completed');

        // Vérifier l'envoi des mails filtrés
        Mail::assertSent(MeetingFilteredReportMail::class, function ($mail) {
            if ($mail->hasTo($this->employee1->email)) {
                // Alice ne doit avoir QUE sa tâche
                return $mail->userTasks->count() === 1
                    && $mail->userTasks->first()->title === 'Préparer le PV final';
            }
            if ($mail->hasTo($this->employee2->email)) {
                // Bob ne doit avoir QUE sa tâche
                return $mail->userTasks->count() === 1
                    && $mail->userTasks->first()->title === 'Sauvegarder les serveurs';
            }
            return false;
        });
    }

    /**
     * Test 9: Isolation stricte des tâches Kanban pour les employés (Confidentialité).
     */
    public function test_employee_cannot_see_or_modify_other_employees_kanban_tasks(): void
    {
        $meeting = Meeting::create([
            'title' => 'Point d\'équipe',
            'date' => Carbon::today()->toDateString(),
            'time' => '09:00',
            'location' => 'Salle B',
            'redactor_id' => $this->rhUser->id,
            'agenda' => 'Attribution des actions',
            'status' => Meeting::STATUS_ONGOING,
        ]);

        // Tâche assignée à Tresor (employee1)
        $task1 = KanbanTask::create([
            'meeting_id' => $meeting->id,
            'title' => 'Tâche de Tresor',
            'assigned_to' => $this->employee1->id,
            'created_by' => $this->rhUser->id,
            'status' => 'todo',
        ]);

        // Tâche assignée à Tresor AHIDAZAN (employee2)
        $task2 = KanbanTask::create([
            'meeting_id' => $meeting->id,
            'title' => 'Tâche confidentielle de Tresor AHIDAZAN',
            'assigned_to' => $this->employee2->id,
            'created_by' => $this->rhUser->id,
            'status' => 'todo',
        ]);

        // 1. Connexion en tant que simple employé (employee1)
        Sanctum::actingAs($this->employee1);

        // La liste standard ne doit contenir QUE la tâche assignée à employee1
        $listResponse = $this->getJson('/api/kanban-tasks');
        $listResponse->assertStatus(200);
        $taskTitles = collect($listResponse->json())->pluck('title')->all();
        $this->assertContains('Tâche de Tresor', $taskTitles);
        $this->assertNotContains('Tâche confidentielle de Tresor AHIDAZAN', $taskTitles);

        // La vue groupée (tableau Kanban) ne doit contenir QUE la tâche d'employee1
        $groupedResponse = $this->getJson('/api/kanban-tasks?grouped=true');
        $groupedResponse->assertStatus(200);
        $groupedTitles = collect($groupedResponse->json('todo'))->pluck('title')->all();
        $this->assertContains('Tâche de Tresor', $groupedTitles);
        $this->assertNotContains('Tâche confidentielle de Tresor AHIDAZAN', $groupedTitles);

        // Tentative d'accès direct (IDOR) à la tâche de l'autre employé -> 403
        $showResponse = $this->getJson("/api/kanban-tasks/{$task2->id}");
        $showResponse->assertStatus(403);

        // Tentative de modification directe de la tâche de l'autre employé -> 403
        $updateResponse = $this->patchJson("/api/kanban-tasks/{$task2->id}/status", [
            'status' => 'done',
        ]);
        $updateResponse->assertStatus(403);

        // 2. Connexion en tant que RH : vue globale sur toutes les tâches
        Sanctum::actingAs($this->rhUser);

        $rhListResponse = $this->getJson('/api/kanban-tasks');
        $rhListResponse->assertStatus(200);
        $rhTaskTitles = collect($rhListResponse->json())->pluck('title')->all();
        $this->assertContains('Tâche de Tresor', $rhTaskTitles);
        $this->assertContains('Tâche confidentielle de Tresor AHIDAZAN', $rhTaskTitles);
    }

    /**
     * Test 10: Activation et suspension explicite du Mode Live.
     */
    public function test_start_and_pause_live_session(): void
    {
        $meeting = Meeting::create([
            'title' => 'Session Live Test',
            'date' => Carbon::today()->toDateString(),
            'time' => '11:00',
            'location' => 'Salle Virtuelle',
            'redactor_id' => $this->director->id,
            'agenda' => 'Ordre du jour live',
            'status' => Meeting::STATUS_CONVOKED,
        ]);

        Sanctum::actingAs($this->director);

        // 1. Démarrer le live
        $startResponse = $this->postJson("/api/meetings/{$meeting->id}/start");
        $startResponse->assertStatus(200)
            ->assertJsonPath('meeting.status', 'ongoing');

        $this->assertEquals(Meeting::STATUS_ONGOING, $meeting->fresh()->status);

        // 2. Suspendre le live
        $pauseResponse = $this->postJson("/api/meetings/{$meeting->id}/pause");
        $pauseResponse->assertStatus(200)
            ->assertJsonPath('meeting.status', 'convoked');

        $this->assertEquals(Meeting::STATUS_CONVOKED, $meeting->fresh()->status);
    }
}
