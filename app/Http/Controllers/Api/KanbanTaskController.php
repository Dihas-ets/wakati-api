<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\KanbanTask;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KanbanTaskController extends Controller
{
    /**
     * Liste des tâches Kanban, avec possibilité de groupement par statut (Colonnes Kanban).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = KanbanTask::with([
            'assignedUser:id,name,email,job_title',
            'creator:id,name,email',
            'meeting:id,title,date',
            'topic:id,subject',
        ]);

        if ($request->has('meeting_id')) {
            $query->where('meeting_id', $request->meeting_id);
        }

        // Règle de sécurité et d'isolation stricte :
        // Un simple employé ne voit QUE ses propres tâches assignées (évite toute fuite entre collègues)
        if ($user->role === User::ROLE_EMPLOYE) {
            $query->where('assigned_to', $user->id);
        } else {
            if ($request->has('assigned_to')) {
                $query->where('assigned_to', $request->assigned_to);
            } elseif ($request->boolean('my_tasks')) {
                $query->where('assigned_to', $user->id);
            }
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $tasks = $query->orderBy('due_date', 'asc')->orderBy('created_at', 'desc')->get();

        if ($request->boolean('grouped')) {
            return response()->json([
                'todo' => $tasks->whereIn('status', [KanbanTask::STATUS_TODO, KanbanTask::STATUS_INITIAL])->values(),
                'in_progress' => $tasks->where('status', KanbanTask::STATUS_IN_PROGRESS)->values(),
                'review' => $tasks->where('status', KanbanTask::STATUS_REVIEW)->values(),
                'done' => $tasks->where('status', KanbanTask::STATUS_DONE)->values(),
            ]);
        }

        return response()->json($tasks);
    }

    /**
     * Création d'une nouvelle tâche Kanban.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'meeting_id' => 'nullable|exists:meetings,id',
            'meeting_topic_id' => 'nullable|exists:meeting_topics,id',
            'assigned_to' => 'required|exists:users,id',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'status' => ['nullable', Rule::in(KanbanTask::STATUSES)],
        ]);

        $task = KanbanTask::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'meeting_id' => $validated['meeting_id'] ?? null,
            'meeting_topic_id' => $validated['meeting_topic_id'] ?? null,
            'assigned_to' => $validated['assigned_to'],
            'created_by' => $request->user()->id,
            'start_date' => $validated['start_date'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'status' => $validated['status'] ?? KanbanTask::STATUS_TODO,
        ]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'kanban_task_created',
            'description' => "Création de la tâche '{$task->title}' assignée à {$task->assignedUser?->name}.",
            'metadata' => [
                'task_id' => $task->id,
                'meeting_id' => $task->meeting_id,
            ],
        ]);

        return response()->json($task->load(['assignedUser', 'creator', 'meeting']), 201);
    }

    /**
     * Détails d'une tâche.
     */
    /**
     * Détails d'une tâche.
     */
    public function show(Request $request, KanbanTask $kanbanTask): JsonResponse
    {
        $user = $request->user();
        if ($user->role === User::ROLE_EMPLOYE && $kanbanTask->assigned_to !== $user->id && $kanbanTask->created_by !== $user->id) {
            abort(403, 'Accès non autorisé à cette tâche.');
        }

        return response()->json($kanbanTask->load([
            'assignedUser:id,name,email,job_title',
            'creator:id,name,email',
            'meeting:id,title,date',
            'topic:id,subject',
        ]));
    }

    /**
     * Mise à jour globale d'une tâche.
     */
    public function update(Request $request, KanbanTask $kanbanTask): JsonResponse
    {
        $user = $request->user();
        if ($user->role === User::ROLE_EMPLOYE && $kanbanTask->assigned_to !== $user->id && $kanbanTask->created_by !== $user->id) {
            abort(403, 'Vous ne pouvez pas modifier cette tâche.');
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to' => 'sometimes|required|exists:users,id',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'status' => ['sometimes', 'required', Rule::in(KanbanTask::STATUSES)],
        ]);

        $kanbanTask->update($validated);

        return response()->json($kanbanTask->fresh(['assignedUser', 'creator', 'meeting', 'topic']));
    }

    /**
     * Changement rapide de colonne / statut (Drag & Drop Kanban).
     */
    public function updateStatus(Request $request, KanbanTask $kanbanTask): JsonResponse
    {
        $user = $request->user();
        if ($user->role === User::ROLE_EMPLOYE && $kanbanTask->assigned_to !== $user->id && $kanbanTask->created_by !== $user->id) {
            abort(403, 'Vous ne pouvez pas modifier le statut d\'une tâche qui ne vous est pas assignée.');
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(KanbanTask::STATUSES)],
        ]);

        $oldStatus = $kanbanTask->status;
        $kanbanTask->update(['status' => $validated['status']]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'kanban_task_status_updated',
            'description' => "Tâche '{$kanbanTask->title}' passée de {$oldStatus} à {$kanbanTask->status}.",
            'metadata' => [
                'task_id' => $kanbanTask->id,
                'old_status' => $oldStatus,
                'new_status' => $kanbanTask->status,
            ],
        ]);

        return response()->json($kanbanTask->fresh(['assignedUser', 'creator', 'meeting']));
    }

    /**
     * Suppression d'une tâche Kanban.
     */
    public function destroy(Request $request, KanbanTask $kanbanTask): JsonResponse
    {
        $user = $request->user();
        if ($user->role === User::ROLE_EMPLOYE && $kanbanTask->created_by !== $user->id) {
            abort(403, 'Vous n\'êtes pas autorisé à supprimer cette tâche.');
        }

        $kanbanTask->delete();

        return response()->json(['message' => 'Tâche Kanban supprimée avec succès.']);
    }
}
