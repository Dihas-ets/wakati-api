<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    /**
     * Récupère l'entreprise courante
     */
    protected function getCompany(Request $request): Company
    {
        $user = $request->user();

        if ($user->isSuperAdmin() && $request->filled('company_id')) {
            return Company::findOrFail($request->integer('company_id'));
        }

        if (! $user->company_id) {
            abort(404, "Aucune entreprise rattachée à cet utilisateur.");
        }

        return Company::findOrFail($user->company_id);
    }

    /**
     * GET /api/branches
     * Liste des agences de l'entreprise
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $this->getCompany($request);

        $query = Branch::query()->where('company_id', $company->id);

        // Si l'utilisateur est restreint à une agence spécifique (ex: DIRECTEUR ou RH d'agence)
        if (! $user->isSuperAdmin() && ! $user->isCompanyAdmin() && $user->branch_id) {
            $query->where('id', $user->branch_id);
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $branches = $query->withCount([
            'users',
            'employees',
            'managers',
            'attendances',
        ])->get();

        return response()->json([
            'branches' => $branches,
            'quota' => [
                'current' => $company->branches()->count(),
                'max' => $company->maxBranchesAllowed(),
                'can_create' => $company->canCreateBranch(),
            ],
        ]);
    }

    /**
     * POST /api/branches
     * Création d'une nouvelle agence (avec vérification du quota du plan SaaS)
     */
    public function store(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        // Vérification des quotas du forfait
        if (! $company->canCreateBranch()) {
            $max = $company->maxBranchesAllowed();
            return response()->json([
                'message' => "Quota maximum d'agences atteint ({$max}). Veuillez mettre à niveau votre forfait SaaS pour créer de nouvelles agences.",
                'code' => 'QUOTA_EXCEEDED_BRANCHES',
                'quota' => [
                    'current' => $company->branches()->count(),
                    'max' => $max,
                ],
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'timezone' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        // Vérifier l'unicité du nom au sein de la même entreprise
        $exists = Branch::where('company_id', $company->id)
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => "Une agence portant ce nom existe déjà au sein de votre entreprise.",
            ], 422);
        }

        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'timezone' => $validated['timezone'] ?? 'Africa/Porto-Novo',
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $company->recordHistory(
            'create_branch',
            "Création de la nouvelle agence \"{$branch->name}\" par {$request->user()->name}.",
            $request->user()->id,
            ['branch_id' => $branch->id]
        );

        return response()->json([
            'message' => 'Agence créée avec succès.',
            'branch' => $branch->loadCount(['users', 'employees', 'attendances']),
        ], 201);
    }

    /**
     * GET /api/branches/{branch}
     * Détails d'une agence
     */
    public function show(Request $request, Branch $branch): JsonResponse
    {
        $user = $request->user();
        $this->authorizeBranchAccess($user, $branch);

        $branch->load([
            'managers',
            'company:id,name,logo_url',
        ])->loadCount(['users', 'employees', 'attendances']);

        return response()->json([
            'branch' => $branch,
        ]);
    }

    /**
     * PUT /api/branches/{branch}
     * Mise à jour d'une agence
     */
    public function update(Request $request, Branch $branch): JsonResponse
    {
        $user = $request->user();
        $this->authorizeBranchAccess($user, $branch);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'timezone' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        // Vérifier l'unicité du nom en ignorant l'agence actuelle
        $exists = Branch::where('company_id', $branch->company_id)
            ->where('name', $validated['name'])
            ->where('id', '!=', $branch->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => "Une autre agence porte déjà ce nom dans votre entreprise.",
            ], 422);
        }

        $branch->update($validated);

        return response()->json([
            'message' => 'Agence mise à jour avec succès.',
            'branch' => $branch->fresh()->loadCount(['users', 'employees', 'attendances']),
        ]);
    }

    /**
     * DELETE /api/branches/{branch}
     * Suppression d'une agence
     */
    public function destroy(Request $request, Branch $branch): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && ! $user->isCompanyAdmin()) {
            return response()->json([
                'message' => 'Seul un administrateur d’entreprise ou super-admin peut supprimer une agence.',
            ], 403);
        }

        $this->authorizeBranchAccess($user, $branch);

        // Vérifier qu'il ne s'agit pas de la seule agence
        if (Branch::where('company_id', $branch->company_id)->count() <= 1) {
            return response()->json([
                'message' => "Impossible de supprimer la seule agence restante de l'entreprise.",
            ], 422);
        }

        $employeesCount = $branch->users()->count();
        if ($employeesCount > 0) {
            return response()->json([
                'message' => "Impossible de supprimer cette agence : {$employeesCount} employé(s) y sont rattaché(s). Veuillez d'abord les réaffecter.",
            ], 422);
        }

        $branchName = $branch->name;
        $branch->delete();

        return response()->json([
            'message' => "L'agence \"{$branchName}\" a été supprimée.",
        ]);
    }

    /**
     * POST /api/branches/{branch}/assign-manager
     * Affecter ou nommer un manager / RH à cette agence
     */
    public function assignManager(Request $request, Branch $branch): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && ! $user->isCompanyAdmin()) {
            return response()->json([
                'message' => 'Non autorisé.',
            ], 403);
        }

        $this->authorizeBranchAccess($user, $branch);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:branch_manager,hr_manager,directeur,rh',
        ]);

        $targetUser = User::where('company_id', $branch->company_id)->findOrFail($validated['user_id']);

        $targetUser->update([
            'branch_id' => $branch->id,
            'role' => $validated['role'],
        ]);

        return response()->json([
            'message' => "L'utilisateur {$targetUser->name} a été assigné en tant que responsable de l'agence {$branch->name}.",
            'user' => $targetUser->fresh(),
        ]);
    }

    /**
     * Vérifie les droits d'accès à l'agence
     */
    protected function authorizeBranchAccess(User $user, Branch $branch): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if ($user->company_id !== $branch->company_id) {
            abort(403, "Cette agence n'appartient pas à votre entreprise.");
        }

        if (! $user->isCompanyAdmin() && $user->branch_id !== $branch->id) {
            abort(403, "Vous n'avez pas accès aux informations de cette agence.");
        }
    }
}
