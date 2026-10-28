<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Requests\Employee\UpdateOwnStatusRequest;
use App\Models\AuditLog;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()
            ->with([
                'department:id,name',
                'agency:id,name',
                'positions:id,department_id,title,permissions,is_sensitive',
            ])
            ->select(['id', 'company_id', 'agency_id', 'name', 'email', 'phone', 'role', 'job_title', 'department_id', 'status', 'last_login_at', 'card_uid', 'created_at']);

        if (in_array($request->user()->role, [User::ROLE_EMPLOYEE, User::ROLE_EMPLOYE], true) && ! $request->user()->isAdmin() && ! $request->user()->hasPermission('rh')) {
            $query->where('id', $request->user()->id);
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('job_title', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($departmentId = $request->query('department_id')) {
            $query->where('department_id', $departmentId);
        }

        $perPage = (int) $request->query('per_page', 15);

        return response()->json(
            $query->orderBy('name')->paginate($perPage)
        );
    }

    public function store(StoreEmployeeRequest $request)
    {
        $this->authorize('create', User::class);

        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['status'] = User::STATUS_PAS_POINTE;
        $data['role'] = $data['role'] ?? User::ROLE_EMPLOYEE;

        // Pré-vérification de poste sensible avant création
        $this->checkSensitivePositionsOnCreate($request, $data);

        $employee = User::create($data);

        $this->syncUserPositions($request, $employee, $data);

        $employee->load([
            'department:id,name',
            'agency:id,name',
            'positions:id,department_id,title,permissions,is_sensitive',
        ]);

        return response()->json($employee, 201);
    }

    public function update(UpdateEmployeeRequest $request, User $user)
    {
        $this->authorize('update', $user);

        $data = $request->validated();
        $assigningAgency = array_key_exists('agency_id', $data) && ! empty($data['agency_id']);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if ($assigningAgency && in_array($user->role, [User::ROLE_EMPLOYEE, User::ROLE_EMPLOYE], true) && empty($data['role'])) {
            $data['role'] = User::ROLE_DIRECTEUR;
            if (empty($data['job_title'])) {
                $agency = \App\Models\Agency::find($data['agency_id']);
                $data['job_title'] = $agency ? "Directeur d'agence - {$agency->name}" : "Directeur d'agence";
            }
        }

        $this->syncUserPositions($request, $user, $data);

        $user->update($data);
        $user->load([
            'department:id,name',
            'agency:id,name',
            'positions:id,department_id,title,permissions,is_sensitive',
        ]);

        return response()->json($user);
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ], 422);
        }

        if ($user->hasSensitiveRole() && ! $request->user()->isAdmin()) {
            abort(403, "Action non autorisée. Seul un administrateur peut supprimer un compte doté d'accès sensibles.");
        }

        $user->delete();

        return response()->json(['message' => 'Employé supprimé.']);
    }

    public function updateOwnStatus(UpdateOwnStatusRequest $request)
    {
        $data = $request->validated();

        $user = $request->user();
        $user->update(['status' => $data['status']]);

        return response()->json(['status' => $user->status]);
    }

    protected function checkSensitivePositionsOnCreate(Request $request, array $data): void
    {
        $targetIds = array_filter(array_merge(
            isset($data['primary_position_id']) ? [$data['primary_position_id']] : [],
            (array) ($data['secondary_position_ids'] ?? []),
            (array) ($data['position_ids'] ?? [])
        ));

        if (! empty($targetIds)) {
            $hasSensitive = Position::whereIn('id', $targetIds)->where('is_sensitive', true)->exists();
            if ($hasSensitive && ! $request->user()->isAdmin()) {
                abort(403, "Action non autorisée. Seul un administrateur peut attribuer un poste à responsabilité sensible.");
            }
        }
    }

    protected function syncUserPositions(Request $request, User $user, array $data): void
    {
        $hasPrimary = array_key_exists('primary_position_id', $data);
        $hasSecondary = array_key_exists('secondary_position_ids', $data);
        $hasPositions = array_key_exists('position_ids', $data);

        if (! $hasPrimary && ! $hasSecondary && ! $hasPositions) {
            return;
        }

        $primaryId = $data['primary_position_id'] ?? null;
        $secondaryIds = (array) ($data['secondary_position_ids'] ?? []);

        if ($hasPositions && ! $primaryId && ! empty($data['position_ids'])) {
            $primaryId = $data['position_ids'][0];
            $secondaryIds = array_slice($data['position_ids'], 1);
        }

        $currentSensitiveIds = $user->positions()->where('is_sensitive', true)->pluck('positions.id')->all();

        $allTargetIds = array_unique(array_filter(array_merge($primaryId ? [$primaryId] : [], $secondaryIds)));
        $targetPositions = Position::whereIn('id', $allTargetIds)->get();
        $targetSensitiveIds = $targetPositions->where('is_sensitive', true)->pluck('id')->all();

        $addedSensitive = array_diff($targetSensitiveIds, $currentSensitiveIds);
        $removedSensitive = array_diff($currentSensitiveIds, $targetSensitiveIds);

        if ((count($addedSensitive) > 0 || count($removedSensitive) > 0) && ! $request->user()->isAdmin()) {
            abort(403, "Action non autorisée. Seul un administrateur peut attribuer ou retirer un poste sensible.");
        }

        $sync = [];
        if ($primaryId) {
            $sync[$primaryId] = ['is_primary' => true];
        }
        foreach ($secondaryIds as $secId) {
            if ($secId != $primaryId) {
                $sync[$secId] = ['is_primary' => false];
            }
        }
        $user->positions()->sync($sync);

        if ($primaryId && ($primaryPos = $targetPositions->firstWhere('id', $primaryId))) {
            $updates = [];
            if (empty($data['job_title'])) {
                $updates['job_title'] = $primaryPos->title;
            }
            if (empty($data['department_id'])) {
                $updates['department_id'] = $primaryPos->department_id;
            }
            if (! empty($updates)) {
                $user->update($updates);
            }
        }

        foreach ($addedSensitive as $posId) {
            $pos = $targetPositions->firstWhere('id', $posId);
            AuditLog::record(
                userId: $request->user()->id,
                targetUserId: $user->id,
                action: 'position.sensitive_assigned',
                details: [
                    'position_id' => $posId,
                    'position_title' => $pos?->title,
                ],
                ip: $request->ip()
            );
        }

        foreach ($removedSensitive as $posId) {
            $pos = Position::find($posId);
            AuditLog::record(
                userId: $request->user()->id,
                targetUserId: $user->id,
                action: 'position.sensitive_revoked',
                details: [
                    'position_id' => $posId,
                    'position_title' => $pos?->title,
                ],
                ip: $request->ip()
            );
        }
    }
}
