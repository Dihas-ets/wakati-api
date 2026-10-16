<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\MeetingConvocationMail;
use App\Mail\MeetingFilteredReportMail;
use App\Models\ActivityLog;
use App\Models\KanbanTask;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\MeetingTopic;
use App\Models\MeetingValidation;
use App\Models\User;
use App\Services\MeetingAttendanceService;
use App\Services\MeetingDocxGeneratorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MeetingController extends Controller
{
    public function __construct(
        protected MeetingAttendanceService $attendanceService,
        protected MeetingDocxGeneratorService $docxGeneratorService
    ) {}

    /**
     * Liste des réunions avec filtres.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Meeting::with([
            'redactor:id,name,email',
            'meetingParticipants.user:id,name,email,job_title',
        ]);

        // Si l'utilisateur est un simple employé, on lui affiche en priorité les réunions où il est invité
        if ($user->role === User::ROLE_EMPLOYE && ! $request->has('all')) {
            $query->where(function ($q) use ($user) {
                $q->where('redactor_id', $user->id)
                  ->orWhereHas('meetingParticipants', fn ($p) => $p->where('user_id', $user->id));
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('from')) {
            $query->whereDate('date', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->whereDate('date', '<=', $request->to);
        }

        if ($request->boolean('today_only')) {
            $query->today();
        }

        $meetings = $query->orderBy('date', 'desc')->orderBy('time', 'desc')->get();

        // Enrichir chaque réunion avec la participation de l'utilisateur connecté
        $meetings->each(function (Meeting $m) use ($user) {
            $participant = $m->meetingParticipants->firstWhere('user_id', $user->id);
            $m->current_user_participant = $participant;
            $m->can_self_check_in = $m->isSelfCheckInAllowed() && $participant && $participant->attendance_status !== MeetingParticipant::STATUS_PRESENT;
        });

        return response()->json($meetings);
    }

    /**
     * Réunions programmées pour aujourd'hui (Le Jour J).
     * Utilisé pour la vue d'émargement rapide et la notification en bannière.
     */
    public function today(Request $request): JsonResponse
    {
        $user = $request->user();

        $meetings = Meeting::today()
            ->with([
                'redactor:id,name,email',
                'meetingParticipants.user:id,name,email,job_title,card_uid',
                'topics.responsible:id,name',
                'kanbanTasks.assignedUser:id,name',
            ])
            ->orderBy('time', 'asc')
            ->get();

        $meetings->each(function (Meeting $m) use ($user) {
            $participant = $m->meetingParticipants->firstWhere('user_id', $user->id);
            $m->current_user_participant = $participant;
            $m->can_self_check_in = $m->isSelfCheckInAllowed() && $participant && $participant->attendance_status !== MeetingParticipant::STATUS_PRESENT;
        });

        return response()->json([
            'date' => Carbon::today()->toDateString(),
            'total_meetings_today' => $meetings->count(),
            'meetings' => $meetings,
        ]);
    }

    /**
     * Création d'une réunion avec ses participants et ses sujets.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'time' => 'required|string',
            'location' => 'required|string|max:255',
            'agenda' => 'required|string',
            'summary_decisions' => 'nullable|string',
            'drh_notes' => 'nullable|string',
            'status' => ['nullable', Rule::in(Meeting::STATUSES)],
            'participant_ids' => 'nullable|array',
            'participant_ids.*' => 'exists:users,id',
            'topics' => 'nullable|array',
            'topics.*.subject' => 'required|string|max:255',
            'topics.*.current_situation' => 'nullable|string',
            'topics.*.evolution_decision' => 'nullable|string',
            'topics.*.responsible_id' => 'nullable|exists:users,id',
            'topics.*.due_date' => 'nullable|date',
            'send_convocations' => 'nullable|boolean',
        ]);

        return DB::transaction(function () use ($request, $validated) {
            $user = $request->user();

            $meeting = Meeting::create([
                'title' => $validated['title'],
                'date' => $validated['date'],
                'time' => $validated['time'],
                'location' => $validated['location'],
                'redactor_id' => $user->id,
                'agenda' => $validated['agenda'],
                'summary_decisions' => $validated['summary_decisions'] ?? null,
                'drh_notes' => $validated['drh_notes'] ?? null,
                'status' => $validated['status'] ?? Meeting::STATUS_DRAFT,
            ]);

            // Ajout des participants
            $participantIds = collect($validated['participant_ids'] ?? [])
                ->push($user->id) // Le rédacteur participe par défaut
                ->unique();

            foreach ($participantIds as $pId) {
                MeetingParticipant::create([
                    'meeting_id' => $meeting->id,
                    'user_id' => $pId,
                    'attendance_status' => MeetingParticipant::STATUS_INVITED,
                ]);
            }

            // Ajout des sujets à l'ordre du jour
            if (! empty($validated['topics'])) {
                foreach ($validated['topics'] as $topic) {
                    MeetingTopic::create([
                        'meeting_id' => $meeting->id,
                        'subject' => $topic['subject'],
                        'current_situation' => $topic['current_situation'] ?? null,
                        'evolution_decision' => $topic['evolution_decision'] ?? null,
                        'responsible_id' => $topic['responsible_id'] ?? null,
                        'due_date' => $topic['due_date'] ?? null,
                    ]);
                }
            }

            // Création des visas institutionnels DRH & Direction
            MeetingValidation::firstOrCreate([
                'meeting_id' => $meeting->id,
                'role_label' => 'DRH',
            ], [
                'user_id' => $user->id,
            ]);

            MeetingValidation::firstOrCreate([
                'meeting_id' => $meeting->id,
                'role_label' => 'Direction',
            ], [
                'user_id' => $user->id,
            ]);

            // Envoi immédiat des convocations si demandé
            if ($request->boolean('send_convocations')) {
                $this->dispatchConvocations($meeting);
            }

            return response()->json(
                $meeting->fresh(['redactor', 'meetingParticipants.user', 'topics.responsible', 'validations']),
                201
            );
        });
    }

    /**
     * Détails complets d'une réunion.
     */
    public function show(Meeting $meeting, Request $request): JsonResponse
    {
        $user = $request->user();

        $meeting->load([
            'redactor:id,name,email,job_title',
            'meetingParticipants.user:id,name,email,job_title,card_uid',
            'topics.responsible:id,name,email,job_title',
            'kanbanTasks.assignedUser:id,name,email',
            'validations.user:id,name,role',
        ]);

        $participant = $meeting->meetingParticipants->firstWhere('user_id', $user->id);
        $meeting->current_user_participant = $participant;
        $meeting->can_self_check_in = $meeting->isSelfCheckInAllowed() && $participant && $participant->attendance_status !== MeetingParticipant::STATUS_PRESENT;

        return response()->json($meeting);
    }

    /**
     * Mise à jour des informations de réunion.
     */
    public function update(Request $request, Meeting $meeting): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'date' => 'sometimes|required|date',
            'time' => 'sometimes|required|string',
            'location' => 'sometimes|required|string|max:255',
            'agenda' => 'sometimes|required|string',
            'summary_decisions' => 'nullable|string',
            'drh_notes' => 'nullable|string',
            'status' => ['nullable', Rule::in(Meeting::STATUSES)],
        ]);

        $meeting->update($validated);

        return response()->json($meeting->fresh([
            'redactor',
            'meetingParticipants.user',
            'topics.responsible',
            'kanbanTasks.assignedUser',
            'validations',
        ]));
    }

    /**
     * Démarrage officiel de la séance en direct (Mode Live activé).
     */
    public function startLiveSession(Meeting $meeting): JsonResponse
    {
        $meeting->update(['status' => Meeting::STATUS_ONGOING]);

        return response()->json([
            'message' => 'La séance est maintenant officiellement en direct (Mode Live activé).',
            'meeting' => $meeting->fresh([
                'redactor',
                'meetingParticipants.user',
                'topics.responsible',
                'kanbanTasks.assignedUser',
                'validations',
            ]),
        ]);
    }

    /**
     * Suspension ou mise en pause de la séance en direct.
     */
    public function pauseLiveSession(Meeting $meeting): JsonResponse
    {
        $meeting->update(['status' => Meeting::STATUS_CONVOKED]);

        return response()->json([
            'message' => 'La séance en direct a été suspendue.',
            'meeting' => $meeting->fresh([
                'redactor',
                'meetingParticipants.user',
                'topics.responsible',
                'kanbanTasks.assignedUser',
                'validations',
            ]),
        ]);
    }

    /**
     * Suppression d'une réunion.
     */
    public function destroy(Meeting $meeting): JsonResponse
    {
        if ($meeting->docx_file_path) {
            $fullPath = storage_path('app/' . $meeting->docx_file_path);
            if (File::exists($fullPath)) {
                File::delete($fullPath);
            }
        }

        $meeting->delete();

        return response()->json(['message' => 'Réunion supprimée avec succès.']);
    }

    /**
     * Envoi automatisé des convocations aux participants.
     */
    public function sendConvocations(Meeting $meeting): JsonResponse
    {
        $sentCount = $this->dispatchConvocations($meeting);

        return response()->json([
            'message' => "{$sentCount} convocation(s) envoyée(s) avec succès.",
            'meeting' => $meeting->fresh(['meetingParticipants.user']),
        ]);
    }

    /**
     * Pointage autonome (Self-Check-in) par l'employé le jour J.
     */
    public function selfCheckIn(Request $request, Meeting $meeting): JsonResponse
    {
        $user = $request->user();
        $method = $request->input('method', 'web');

        $participant = $this->attendanceService->selfCheckIn($meeting, $user, $method);

        return response()->json([
            'message' => 'Présence validée avec succès !',
            'participant' => $participant,
            'stats' => $meeting->fresh()->attendance_stats,
        ]);
    }

    /**
     * Émargement multicanal (QR Code / RFID / Manuel par animateur).
     */
    public function checkIn(Request $request, Meeting $meeting): JsonResponse
    {
        $request->validate([
            'badge_code' => 'required_without:user_id|string|nullable',
            'user_id' => 'required_without:badge_code|integer|nullable|exists:users,id',
            'method' => 'nullable|string|in:qr_code,rfid,manual,mobile_app,web',
            'status' => 'nullable|string|in:present,absent,excused,invited',
        ]);

        if ($request->filled('badge_code')) {
            $result = $this->attendanceService->checkInByBadge(
                $meeting,
                $request->badge_code,
                $request->input('method', 'qr_code')
            );

            return response()->json($result);
        }

        $participant = $this->attendanceService->checkInManual(
            $meeting,
            (int) $request->user_id,
            $request->input('status', 'present'),
            $request->input('method', 'manual')
        );

        return response()->json([
            'success' => true,
            'participant' => $participant,
            'stats' => $meeting->fresh()->attendance_stats,
        ]);
    }

    /**
     * Ajout d'un point à l'ordre du jour / sujet.
     */
    public function addTopic(Request $request, Meeting $meeting): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'current_situation' => 'nullable|string',
            'evolution_decision' => 'nullable|string',
            'responsible_id' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
        ]);

        $topic = $meeting->topics()->create($validated);

        return response()->json($topic->load('responsible'), 201);
    }

    /**
     * Transformer une action / point abordé en tâche Kanban.
     */
    public function convertTopicToTask(Request $request, Meeting $meeting, MeetingTopic $topic): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            'status' => ['nullable', Rule::in(KanbanTask::STATUSES)],
        ]);

        $task = KanbanTask::create([
            'meeting_id' => $meeting->id,
            'meeting_topic_id' => $topic->id,
            'title' => $validated['title'] ?? $topic->subject,
            'description' => $validated['description'] ?? ($topic->evolution_decision ?: $topic->current_situation),
            'assigned_to' => $validated['assigned_to'] ?? $topic->responsible_id ?? $request->user()->id,
            'created_by' => $request->user()->id,
            'due_date' => $validated['due_date'] ?? $topic->due_date,
            'status' => $validated['status'] ?? KanbanTask::STATUS_TODO,
        ]);

        return response()->json($task->load(['assignedUser', 'creator']), 201);
    }

    /**
     * Génère la fiche de réunion Word modifiable (.docx).
     */
    public function generateDocx(Request $request, Meeting $meeting): JsonResponse
    {
        $filePath = $this->docxGeneratorService->generate($meeting);
        $meeting->refresh();

        $token = $request->bearerToken();
        $downloadUrl = url('/api/meetings/' . $meeting->id . '/download-docx') . ($token ? '?token=' . urlencode($token) : '');

        return response()->json([
            'message' => 'Fiche Word générée avec succès.',
            'docx_file_path' => $meeting->docx_file_path,
            'download_url' => $downloadUrl,
            'stats' => $meeting->attendance_stats,
        ]);
    }

    /**
     * Télécharge le fichier Word généré.
     */
    public function downloadDocx(Request $request, Meeting $meeting): BinaryFileResponse
    {
        $user = $request->user('sanctum') ?? $request->user();

        // 1. Résolution via Bearer Token d'en-tête
        if (! $user && $request->bearerToken()) {
            $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($request->bearerToken());
            if ($pat) {
                $user = $pat->tokenable;
            }
        }

        // 2. Résolution via paramètre URL ?token=
        if (! $user && $request->filled('token')) {
            $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($request->query('token'));
            if ($pat) {
                $user = $pat->tokenable;
            }
        }

        // 3. Fallback session web
        if (! $user && auth()->check()) {
            $user = auth()->user();
        }

        if (! $user) {
            abort(401, 'Non authentifié pour télécharger ce document.');
        }

        if (! $meeting->docx_file_path || ! File::exists(storage_path('app/' . $meeting->docx_file_path))) {
            $this->docxGeneratorService->generate($meeting);
            $meeting->refresh();
        }

        $fullPath = storage_path('app/' . $meeting->docx_file_path);
        $cleanFileName = sprintf('Compte_Rendu_%s_%s.docx', str_replace(' ', '_', $meeting->title), Carbon::parse($meeting->date)->format('Y-m-d'));

        return response()->download($fullPath, $cleanFileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="' . $cleanFileName . '"',
        ]);
    }

    /**
     * Importe / téléverse une fiche Word (.docx) ou PDF modifiée ou signée.
     */
    public function uploadDocx(Request $request, Meeting $meeting): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:docx,pdf,doc|max:20480',
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $fileName = sprintf('Compte_Rendu_Reunion_%d_Imported_%s.%s', $meeting->id, Carbon::now()->format('Ymd_His'), $extension);

        $storedPath = $file->storeAs('meetings', $fileName);

        $meeting->update([
            'docx_file_path' => $storedPath,
        ]);

        return response()->json([
            'message' => 'Document importé et associé à la réunion avec succès.',
            'docx_file_path' => $storedPath,
            'meeting' => $meeting->fresh(),
        ]);
    }

    /**
     * Clôture de la réunion et diffusion filtrée des comptes-rendus par e-mail.
     * Chaque employé ne reçoit QUE ses propres tâches, et les notes DRH sont masquées.
     */
    public function closeAndSendReports(Meeting $meeting): JsonResponse
    {
        return DB::transaction(function () use ($meeting) {
            // 1. Clôture du statut
            $meeting->update(['status' => Meeting::STATUS_COMPLETED]);

            // 2. Génération de la fiche Word officielle finale
            $this->docxGeneratorService->generate($meeting);

            // 3. Diffusion ciblée et filtrée à chaque participant
            $meeting->load(['meetingParticipants.user', 'kanbanTasks']);

            $sentCount = 0;
            foreach ($meeting->meetingParticipants as $mp) {
                $participantUser = $mp->user;
                if (! $participantUser || ! $participantUser->email) {
                    continue;
                }

                try {
                    Mail::to($participantUser->email)->send(
                        new MeetingFilteredReportMail($meeting, $participantUser)
                    );
                    $mp->update(['report_sent' => true]);
                    $sentCount++;
                } catch (\Throwable $e) {
                    // Enregistrement de l'incident d'envoi sans bloquer le reste
                    report($e);
                }
            }

            ActivityLog::create([
                'user_id' => auth()->id() ?? $meeting->redactor_id,
                'action' => 'meeting_closed_reports_distributed',
                'description' => "Clôture de la réunion '{$meeting->title}' et diffusion de {$sentCount} comptes-rendus filtrés.",
                'metadata' => [
                    'meeting_id' => $meeting->id,
                    'reports_sent' => $sentCount,
                    'stats' => $meeting->attendance_stats,
                ],
            ]);

            return response()->json([
                'message' => "Réunion clôturée. {$sentCount} compte(s)-rendu(s) personnalisé(s) et filtré(s) ont été envoyés.",
                'meeting' => $meeting->fresh(['meetingParticipants.user', 'kanbanTasks.assignedUser']),
                'stats' => $meeting->attendance_stats,
            ]);
        });
    }

    /**
     * Dispatcher interne d'envoi de convocations.
     */
    protected function dispatchConvocations(Meeting $meeting): int
    {
        $meeting->load(['meetingParticipants.user', 'redactor']);
        $sentCount = 0;

        foreach ($meeting->meetingParticipants as $mp) {
            $user = $mp->user;
            if (! $user || ! $user->email) {
                continue;
            }

            try {
                Mail::to($user->email)->send(
                    new MeetingConvocationMail($meeting, $user)
                );
                $mp->update(['convocation_sent' => true]);
                $sentCount++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($meeting->status === Meeting::STATUS_DRAFT) {
            $meeting->update(['status' => Meeting::STATUS_CONVOKED]);
        }

        return $sentCount;
    }
}
