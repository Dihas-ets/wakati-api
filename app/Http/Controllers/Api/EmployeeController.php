<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Requests\Employee\UpdateOwnStatusRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /api/employees
     * Liste des employés selon les droits (Vue consolidée 360° pour Company Admin, cloisonnée pour Branch Manager).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $currentUser = $request->user();

        $query = User::query()
            ->with([
                'department:id,name',
                'branch:id,name,code',
            ])
            ->select([
                'id',
                'company_id',
                'branch_id',
                'name',
                'first_name',
                'last_name',
                'phone',
                'email',
                'role',
                'job_title',
                'department_id',
                'status',
                'last_login_at',
                'card_uid',
                'created_at',
            ]);

        // Cloisonnement Tenant (Entreprise)
        if (! $currentUser->isSuperAdmin()) {
            $query->where('company_id', $currentUser->company_id);
        } elseif ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        // Cloisonnement ou Filtrage Multi-Agences
        if ($currentUser->isEmployee()) {
            // L'employé ne consulte que sa propre fiche
            $query->where('id', $currentUser->id);
        } elseif ($currentUser->isBranchManager() || $currentUser->isHrManager()) {
            // Le manager d'agence ne voit que les employés de son agence
            $query->where('branch_id', $currentUser->branch_id);
        } elseif ($request->filled('branch_id')) {
            // Le Company Admin ou Super Admin peut filtrer sur une agence spécifique
            $query->where('branch_id', $request->integer('branch_id'));
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
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
     * Création d'un employé avec vérification du quota d'employés du forfait
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $currentUser = $request->user();
        $companyId = $currentUser->company_id;

        if ($currentUser->isSuperAdmin() && $request->filled('company_id')) {
            $companyId = $request->integer('company_id');
        }

        $company = Company::findOrFail($companyId);

        // 1. Contrôle des quotas du forfait SaaS
        if (! $company->canCreateEmployee()) {
            $max = $company->maxEmployeesAllowed();
            return response()->json([
                'message' => "Quota maximum d'employés atteint ({$max}) pour votre forfait SaaS. Veuillez mettre à niveau votre abonnement.",
                'code' => 'QUOTA_EXCEEDED_EMPLOYEES',
                'quota' => [
                    'current' => $company->users()->whereIn('role', [User::ROLE_EMPLOYEE, User::ROLE_EMPLOYE])->count(),
                    'max' => $max,
                ],
            ], 403);
        }

        $data = $request->validated();

        // 2. Détermination de l'agence
        $branchId = null;
        if ($currentUser->isBranchManager() || $currentUser->isHrManager()) {
            $branchId = $currentUser->branch_id;
        } elseif (! empty($data['branch_id'])) {
            // Vérifier que la branche appartient bien à l'entreprise
            $branch = Branch::where('company_id', $company->id)->findOrFail($data['branch_id']);
            $branchId = $branch->id;
        } else {
            // Agence par défaut (première agence active de l'entreprise)
            $branchId = $company->branches()->value('id');
        }

        $data['company_id'] = $company->id;
        $data['branch_id'] = $branchId;
        $data['password'] = Hash::make($data['password']);
        $data['status'] = User::STATUS_PAS_POINTE;

        if (empty($data['name'])) {
            $data['name'] = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        }

        $employee = User::create($data);
        $employee->load([
            'department:id,name',
            'branch:id,name,code',
        ]);

        $company->recordHistory(
            'create_employee',
            "Création de l'employé {$employee->name} par {$currentUser->name}.",
            $currentUser->id,
            ['employee_id' => $employee->id, 'branch_id' => $branchId]
        );

        return response()->json($employee, 201);
    }

    /**
     * PUT /api/employees/{user}
     */
    public function update(UpdateEmployeeRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $currentUser = $request->user();
        $data = $request->validated();

        if (! empty($data['branch_id']) && ! $currentUser->isSuperAdmin() && ! $currentUser->isCompanyAdmin()) {
            unset($data['branch_id']); // Seul l'admin entreprise peut transférer un employé d'une agence à une autre
        }

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if (isset($data['first_name']) || isset($data['last_name'])) {
            $fn = $data['first_name'] ?? $user->first_name;
            $ln = $data['last_name'] ?? $user->last_name;
            $data['name'] = trim("{$fn} {$ln}");
        }

        $user->update($data);
        $user->load([
            'department:id,name',
            'branch:id,name,code',
        ]);

        return response()->json($user);
    }

    /**
     * DELETE /api/employees/{user}
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ], 422);
        }

        $userName = $user->name;
        $user->delete();

        return response()->json(['message' => "L'employé {$userName} a été supprimé."]);
    }

    /**
     * PATCH /api/employees/me/status
     */
    public function updateOwnStatus(UpdateOwnStatusRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = $request->user();
        $user->update(['status' => $data['status']]);

        return response()->json(['status' => $user->status]);
    }
}
