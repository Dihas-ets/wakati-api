<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = User::with([
            'department:id,name',
            'agency:id,name',
            'positions:id,department_id,title,permissions,is_sensitive',
        ])->select('id', 'company_id', 'agency_id', 'name', 'email', 'phone', 'role', 'job_title', 'department_id', 'status', 'card_uid', 'created_at');

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('job_title', 'like', "%{$search}%");
            });
        }

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        if ($departmentId = $request->query('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json(
            $query->orderBy('name')->get()
        );
    }

    public function store(StoreUserRequest $request)
    {
        $this->authorize('create', User::class);

        $data = $request->validated();
        $targetRole = $data['role'] ?? User::ROLE_EMPLOYEE;

        // Un RH ne peut pas créer de compte Directeur ou Company Admin
        if ($request->user()->role === User::ROLE_RH && in_array($targetRole, [User::ROLE_DIRECTEUR, User::ROLE_COMPANY_ADMIN, User::ROLE_SUPER_ADMIN], true)) {
            return response()->json([
                'message' => "Un responsable RH ne peut pas créer de compte de direction.",
            ], 403);
        }

        // Contrôle du quota d'employés du forfait
        $companyId = $request->user()->company_id;
        if ($request->user()->role !== User::ROLE_SUPER_ADMIN && $companyId) {
            $company = Company::find($companyId);
            $plan = $company?->getActivePlan();
            if ($plan && $plan->max_employees !== null) {
                $currentEmployees = User::where('company_id', $companyId)->count();
                if ($currentEmployees >= $plan->max_employees) {
                    return response()->json([
                        'message' => "Limite d'employés atteinte pour votre forfait ({$plan->max_employees} max). Veuillez passer à un forfait supérieur pour ajouter plus d'employés.",
                    ], 422);
                }
            }
        }

        // Pré-vérification : attribution de poste sensible interdite aux non-admins
        $this->checkSensitivePositionsOnCreate($request, $data);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => $targetRole,
            'job_title' => $data['job_title'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'agency_id' => $data['agency_id'] ?? null,
            'status' => User::STATUS_PAS_POINTE,
            'card_uid' => User::newCardUid(),
            'email_verified_at' => now(),
        ]);

        $this->syncUserPositions($request, $user, $data);

        $user->load([
            'department:id,name',
            'agency:id,name',
            'positions:id,department_id,title,permissions,is_sensitive',
        ]);

        return response()->json($user, 201);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $this->authorize('update', $user);

        $data = $request->validated();

        // Protection : un utilisateur ne peut pas modifier son propre rôle
        if ($request->user()->id === $user->id && isset($data['role']) && $data['role'] !== $user->role) {
            return response()->json([
                'message' => "Vous ne pouvez pas modifier votre propre rôle.",
            ], 422);
        }

        // Un RH ne peut pas modifier un compte Directeur ni promouvoir au rôle Directeur
        if ($request->user()->role === User::ROLE_RH) {
            if ($user->role === User::ROLE_DIRECTEUR) {
                return response()->json([
                    'message' => "Un compte RH ne peut pas modifier un compte Directeur.",
                ], 403);
            }
            if (isset($data['role']) && $data['role'] === User::ROLE_DIRECTEUR) {
                return response()->json([
                    'message' => "Un compte RH ne peut pas attribuer le rôle Directeur.",
                ], 403);
            }
        }

        $this->syncUserPositions($request, $user, $data);

        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        if (isset($data['email'])) {
            $user->email = $data['email'];
        }
        if (isset($data['phone'])) {
            $user->phone = $data['phone'];
        }
        if (isset($data['role'])) {
            $user->role = $data['role'];
        }
        if (array_key_exists('job_title', $data)) {
            $user->job_title = $data['job_title'];
        }
        if (array_key_exists('department_id', $data)) {
            $user->department_id = $data['department_id'];
        }
        if (array_key_exists('agency_id', $data)) {
            $user->agency_id = $data['agency_id'];
            if (! empty($data['agency_id']) && in_array($user->role, [User::ROLE_EMPLOYEE, User::ROLE_EMPLOYE], true) && empty($data['role'])) {
                $user->role = User::ROLE_DIRECTEUR;
                if (empty($data['job_title'])) {
                    $agency = \App\Models\Agency::find($data['agency_id']);
                    $user->job_title = $agency ? "Directeur d'agence - {$agency->name}" : "Directeur d'agence";
                }
            }
        }

        // Gestion du blocage / déblocage
        if (array_key_exists('is_blocked', $data)) {
            if ($data['is_blocked']) {
                $user->status = 'blocked';
            } elseif ($user->status === 'blocked') {
                $user->status = User::STATUS_PAS_POINTE;
            }
        } elseif (isset($data['status'])) {
            $user->status = $data['status'];
        }

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();
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

        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => "Vous ne pouvez pas supprimer votre propre compte.",
            ], 422);
        }

        if ($request->user()->role === User::ROLE_RH && $user->role === User::ROLE_DIRECTEUR) {
            return response()->json([
                'message' => "Un compte RH ne peut pas supprimer un compte Directeur.",
            ], 403);
        }

        if ($user->hasSensitiveRole() && ! $request->user()->isAdmin()) {
            abort(403, "Action non autorisée. Seul un administrateur peut supprimer un compte doté d'accès sensibles.");
        }

        $user->delete();

        return response()->json(['message' => 'Compte supprimé.']);
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