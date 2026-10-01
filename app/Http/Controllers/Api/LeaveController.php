<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\DecideLeaveRequest;
use App\Http\Requests\Leave\StoreLeaveRequest;
use App\Http\Requests\Leave\UpdateLeaveRequest;
use App\Models\ActivityLog;
use App\Models\Leave;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Leave::class);

        $query = Leave::with(['user:id,name,email', 'createdBy:id,name', 'decidedBy:id,name']);

        if (in_array($request->user()->role, ['employe', 'comptable'])) {
            $query->where('user_id', $request->user()->id);
        } elseif ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('from')) {
            $query->where('date', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('date', '<=', $request->to);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json($query->orderBy('date')->get());
    }

    /**
     * POST /api/leaves
     * RH/Directeur : crée un congé directement approuvé, pour n'importe quel employé.
     * Employé/Comptable : crée une DEMANDE en attente, pour lui-même uniquement.
     */
    public function store(StoreLeaveRequest $request)
    {
        $this->authorize('create', Leave::class);

        $isManager = in_array($request->user()->role, ['rh', 'directeur']);
        $validated = $request->validated();

        $leave = Leave::create([
            ...$validated,
            'user_id' => $isManager ? $validated['user_id'] : $request->user()->id,
            'created_by' => $request->user()->id,
            'status' => $isManager ? Leave::STATUS_APPROVED : Leave::STATUS_PENDING,
            'decided_by' => $isManager ? $request->user()->id : null,
            'decided_at' => $isManager ? now() : null,
        ]);

        ActivityLog::record(
            $request,
            $isManager ? 'leave.created' : 'leave.requested',
            $leave,
            ['leave_type' => $leave->leave_type, 'date' => $leave->date->toDateString()]
        );

        return response()->json([
            'message' => $isManager
                ? 'Congé enregistré avec succès.'
                : 'Votre demande de congé a été envoyée pour validation.',
            'leave' => $leave->load('user:id,name'),
        ], 201);
    }

    public function update(UpdateLeaveRequest $request, Leave $leave)
    {
        $this->authorize('update', $leave);

        $validated = $request->validated();

        $leave->update($validated);

        return response()->json(['message' => 'Congé mis à jour.', 'leave' => $leave]);
    }

    /**
     * PATCH /api/leaves/{leave}/decide
     * RH/Directeur approuve ou rejette une demande en attente.
     */
    public function decide(DecideLeaveRequest $request, Leave $leave)
    {
        $this->authorize('decide', $leave);

        $validated = $request->validated();

        $previousStatus = $leave->status;

        $leave->update([
            'status' => $validated['status'],
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        ActivityLog::record(
            $request,
            $validated['status'] === Leave::STATUS_APPROVED ? 'leave.approved' : 'leave.rejected',
            $leave,
            ['previous_status' => $previousStatus]
        );

        return response()->json([
            'message' => $validated['status'] === Leave::STATUS_APPROVED
                ? 'Demande de congé approuvée.'
                : 'Demande de congé rejetée.',
            'leave' => $leave,
        ]);
    }

    public function destroy(Request $request, Leave $leave)
    {
        $this->authorize('delete', $leave);

        $leave->delete();

        ActivityLog::record($request, 'leave.deleted', $leave);

        return response()->json(['message' => 'Congé supprimé.']);
    }
}