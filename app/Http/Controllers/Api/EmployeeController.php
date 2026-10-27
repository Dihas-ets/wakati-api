<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Requests\Employee\UpdateOwnStatusRequest;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /api/employees
     * Query params optionnels :
     *   - q          : recherche texte (nom ou email)
     *   - status     : au_travail | en_pause | pas_pointe
     *   - department_id
     *   - per_page   : défaut 15
     *
     * Accessible à tous les rôles authentifiés (lecture seule pour
     * comptable/employe — c'est le front-end qui masque les actions,
     * mais on ne renvoie jamais le mot de passe de toute façon).
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()
            ->with(['department:id,name', 'agency:id,name'])
            ->select(['id', 'company_id', 'agency_id', 'name', 'email', 'role', 'job_title', 'department_id', 'status', 'last_login_at', 'card_uid', 'created_at']);

        if (in_array($request->user()->role, [User::ROLE_EMPLOYEE, User::ROLE_EMPLOYE], true)) {
            $query->where('id', $request->user()->id);
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
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

    /**
     * POST /api/employees
     * Réservé à rh/directeur (voir routes/api.php).
     */
    public function store(StoreEmployeeRequest $request)
    {
        $this->authorize('create', User::class);

        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['status'] = User::STATUS_PAS_POINTE;

        $employee = User::create($data);
        $employee->load('department:id,name');

        return response()->json($employee, 201);
    }

    /**
     * PUT /api/employees/{user}
     * Réservé à rh/directeur.
     */
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

        // Si l'employé est assigné à une agence et qu'aucun autre rôle n'est spécifié, il devient Directeur
        if ($assigningAgency && in_array($user->role, [User::ROLE_EMPLOYEE, User::ROLE_EMPLOYE], true) && empty($data['role'])) {
            $data['role'] = User::ROLE_DIRECTEUR;
            if (empty($data['job_title'])) {
                $agency = \App\Models\Agency::find($data['agency_id']);
                $data['job_title'] = $agency ? "Directeur d'agence - {$agency->name}" : "Directeur d'agence";
            }
        }

        $user->update($data);
        $user->load('department:id,name');

        return response()->json($user);
    }

    /**
     * DELETE /api/employees/{user}
     * Réservé à rh/directeur. On empêche la suppression de son propre compte,
     * comme pour la gestion des comptes admin.
     */
    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ], 422);
        }

        $user->delete();

        return response()->json(['message' => 'Employé supprimé.']);
    }

    /**
     * PATCH /api/employees/me/status
     * Permet à l'utilisateur connecté de basculer sa propre pause,
     * puisque "en_pause" ne peut pas se déduire de la seule connexion.
     */
    public function updateOwnStatus(UpdateOwnStatusRequest $request)
    {
        $data = $request->validated();

        $user = $request->user();
        $user->update(['status' => $data['status']]);

        return response()->json(['status' => $user->status]);
    }
}
