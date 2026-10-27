<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /api/users
     * Accessible au Directeur et aux RH.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = User::with('department:id,name')
            ->select('id', 'name', 'email', 'role', 'job_title', 'department_id', 'status', 'card_uid', 'created_at');

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
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

    /**
     * POST /api/users
     * Accessible au Directeur et aux RH.
     */
    public function store(StoreUserRequest $request)
    {
        $this->authorize('create', User::class);

        $data = $request->validated();

        // Un RH ne peut pas créer de compte Directeur
        if ($request->user()->role === User::ROLE_RH && $data['role'] === User::ROLE_DIRECTEUR) {
            return response()->json([
                'message' => "Un responsable RH ne peut pas créer de compte Directeur.",
            ], 403);
        }

        // Contrôle du quota d'employés du forfait de l'entreprise (max_employees)
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

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'job_title' => $data['job_title'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'agency_id' => $data['agency_id'] ?? null,
            'status' => User::STATUS_PAS_POINTE,
            'card_uid' => User::newCardUid(),
            'email_verified_at' => now(),
        ]);

        $user->load('department:id,name');

        return response()->json($user, 201);
    }

    /**
     * PUT /api/users/{user}
     * Accessible au Directeur et aux RH.
     */
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

        // Un RH ne peut pas modifier un compte Directeur ni promouvoir un compte au rôle Directeur
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

        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        if (isset($data['email'])) {
            $user->email = $data['email'];
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
            // Promotion automatique en Directeur si assigné à une agence sans rôle explicite
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

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();
        $user->load('department:id,name');

        return response()->json($user);
    }

    /**
     * DELETE /api/users/{user}
     */
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

        $user->delete();

        return response()->json(['message' => 'Compte supprimé.']);
    }
}