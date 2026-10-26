<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgencyController extends Controller
{
    /**
     * GET /api/branches ou /api/agencies
     * Liste des agences de l'entreprise connectée avec quotas.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Agency::query();

        if ($user->role !== User::ROLE_SUPER_ADMIN) {
            if (! $user->company_id) {
                return response()->json([
                    'branches' => [],
                    'quota' => ['current' => 0, 'max' => 0, 'can_create' => false],
                ]);
            }
            $query->where('company_id', $user->company_id);
        } elseif ($request->filled('company_id')) {
            $query->where('company_id', $request->query('company_id'));
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
                if (\Illuminate\Support\Facades\Schema::hasColumn('agencies', 'code')) {
                    $q->orWhere('code', 'like', "%{$search}%");
                }
            });
        }

        if ($request->has('is_active') && \Illuminate\Support\Facades\Schema::hasColumn('agencies', 'is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $agencies = $query->with([
            'users' => function ($q) {
                $q->select('id', 'name', 'email', 'role', 'agency_id', 'company_id');
            },
        ])
        ->withCount(['users', 'attendances'])
        ->orderBy('id', 'asc')
        ->get();

        $formatted = $agencies->map(function (Agency $agency) {
            $managers = $agency->users
                ->filter(fn (User $u) => in_array($u->role, [User::ROLE_MANAGER, 'branch_manager', User::ROLE_DIRECTEUR], true))
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role,
                    'phone' => null,
                ])
                ->values();

            return [
                'id' => $agency->id,
                'company_id' => $agency->company_id,
                'name' => $agency->name,
                'code' => $agency->code ?? null,
                'address' => $agency->address ?? null,
                'phone' => $agency->phone ?? null,
                'timezone' => $agency->timezone ?? 'Africa/Porto-Novo',
                'is_active' => (bool) ($agency->is_active ?? true),
                'users_count' => $agency->users_count,
                'attendances_count' => $agency->attendances_count,
                'managers' => $managers,
                'created_at' => $agency->created_at?->toIso8601String(),
                'updated_at' => $agency->updated_at?->toIso8601String(),
            ];
        });

        $currentCount = $agencies->count();
        $maxAgencies = null; // null = illimité

        if ($user->company_id) {
            $company = Company::find($user->company_id);
            $plan = $company?->getActivePlan();
            if ($plan) {
                $maxAgencies = $plan->max_branches;
            }
        }

        $canCreate = $maxAgencies === null || $currentCount < $maxAgencies;

        return response()->json([
            'branches' => $formatted,
            'quota' => [
                'current' => $currentCount,
                'max' => $maxAgencies,
                'is_unlimited' => $maxAgencies === null,
                'can_create' => $canCreate,
            ],
        ]);
    }

    /**
     * GET /api/branches/{agency} ou /api/agencies/{agency}
     */
    public function show(Request $request, Agency $agency): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== User::ROLE_SUPER_ADMIN && $agency->company_id !== $user->company_id) {
            return response()->json(['message' => 'Accès non autorisé à cette agence.'], 403);
        }

        $agency->loadCount(['users', 'attendances']);

        return response()->json(['branch' => $agency]);
    }

    /**
     * POST /api/branches ou /api/agencies
     * Création d'une agence avec isolation stricte du company_id.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN, User::ROLE_DIRECTEUR], true)) {
            return response()->json(['message' => 'Action réservée aux administrateurs.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'company_id' => ['nullable', 'exists:companies,id'],
        ]);

        $companyId = $user->role === User::ROLE_SUPER_ADMIN && ! empty($validated['company_id'])
            ? (int) $validated['company_id']
            : $user->company_id;

        if (! $companyId) {
            return response()->json(['message' => 'Aucune entreprise rattachée.'], 422);
        }

        // Vérification du quota d'agences du forfait (max_branches)
        if ($user->role !== User::ROLE_SUPER_ADMIN) {
            $company = Company::find($companyId);
            $plan = $company?->getActivePlan();
            if ($plan && $plan->max_branches !== null) {
                $count = Agency::where('company_id', $companyId)->count();
                if ($count >= $plan->max_branches) {
                    return response()->json([
                        'message' => "Limite d'agences atteinte pour votre forfait ({$plan->max_branches} max). Veuillez passer à un forfait supérieur pour ajouter plus d'agences.",
                    ], 422);
                }
            }
        }

        $agencyData = [
            'company_id' => $companyId,
            'name' => trim($validated['name']),
            'address' => $validated['address'] ?? null,
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('agencies', 'code')) {
            $agencyData['code'] = $validated['code'] ?? null;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('agencies', 'phone')) {
            $agencyData['phone'] = $validated['phone'] ?? null;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('agencies', 'timezone')) {
            $agencyData['timezone'] = $validated['timezone'] ?? 'Africa/Porto-Novo';
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('agencies', 'is_active')) {
            $agencyData['is_active'] = $validated['is_active'] ?? true;
        }

        $agency = Agency::create($agencyData);

        return response()->json([
            'message' => 'Agence créée avec succès.',
            'branch' => $agency,
        ], 201);
    }

    /**
     * PUT /api/branches/{agency} ou /api/agencies/{agency}
     */
    public function update(Request $request, Agency $agency): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== User::ROLE_SUPER_ADMIN && $agency->company_id !== $user->company_id) {
            return response()->json(['message' => 'Accès non autorisé à cette agence.'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $agency->update($validated);

        return response()->json([
            'message' => 'Agence mise à jour avec succès.',
            'branch' => $agency->fresh(),
        ]);
    }

    /**
     * DELETE /api/branches/{agency} ou /api/agencies/{agency}
     */
    public function destroy(Request $request, Agency $agency): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== User::ROLE_SUPER_ADMIN && $agency->company_id !== $user->company_id) {
            return response()->json(['message' => 'Accès non autorisé à cette agence.'], 403);
        }

        $agency->delete();

        return response()->json([
            'message' => 'Agence supprimée avec succès.',
        ]);
    }

    /**
     * POST /api/branches/{agency}/assign-manager ou /api/agencies/{agency}/assign-manager
     */
    public function assignManager(Request $request, Agency $agency): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== User::ROLE_SUPER_ADMIN && $agency->company_id !== $user->company_id) {
            return response()->json(['message' => 'Accès non autorisé à cette agence.'], 403);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['nullable', 'string'],
        ]);

        $targetUser = User::withoutGlobalScopes()->find($validated['user_id']);

        if (! $targetUser || ($user->role !== User::ROLE_SUPER_ADMIN && $targetUser->company_id !== $user->company_id)) {
            return response()->json(['message' => "L'utilisateur spécifié n'appartient pas à votre entreprise."], 422);
        }

        $targetUser->agency_id = $agency->id;
        $targetUser->role = User::ROLE_MANAGER;
        $targetUser->save();

        return response()->json([
            'message' => 'Responsable assigné à l\'agence avec succès.',
            'user' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'role' => $targetUser->role,
                'agency_id' => $targetUser->agency_id,
            ],
        ]);
    }
}
