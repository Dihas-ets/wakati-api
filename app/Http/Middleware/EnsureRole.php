<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage dans routes/api.php :
 *   Route::middleware(['auth:sanctum', 'role:directeur'])->group(...)
 *   Route::middleware(['auth:sanctum', 'role:rh,directeur'])->group(...) // plusieurs rôles autorisés
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Non authentifié.',
            ], 401);
        }

        // Super-admin : accès total à toute la plateforme
        if ($user->role === \App\Models\User::ROLE_SUPER_ADMIN) {
            return $next($request);
        }

        // Rôle directement présent dans les rôles autorisés
        if (in_array($user->role, $roles, true)) {
            return $next($request);
        }

        // Cumul des permissions dynamiques issues des postes (RBAC)
        foreach ($roles as $r) {
            if ($user->hasPermission($r)) {
                return $next($request);
            }
        }

        // Company admin : droits de gestion pour son entreprise (équivalent directeur / rh / manager)
        if ($user->role === \App\Models\User::ROLE_COMPANY_ADMIN) {
            if (array_intersect($roles, ['company_admin', 'directeur', 'rh', 'manager'])) {
                return $next($request);
            }
        }

        // Directeur : directeur d'agence ou d'entreprise (accès aux actions directeur, manager, rh)
        if ($user->role === \App\Models\User::ROLE_DIRECTEUR) {
            if (array_intersect($roles, ['directeur', 'manager', 'rh'])) {
                return $next($request);
            }
        }

        // Manager : directeur d'agence (accès aux actions directeur / manager d'agence)
        if ($user->role === \App\Models\User::ROLE_MANAGER) {
            if (array_intersect($roles, ['manager', 'directeur', 'rh'])) {
                return $next($request);
            }
        }

        // Équivalence employee / employe
        if ($user->role === \App\Models\User::ROLE_EMPLOYEE && in_array('employe', $roles, true)) {
            return $next($request);
        }
        if ($user->role === \App\Models\User::ROLE_EMPLOYE && in_array('employee', $roles, true)) {
            return $next($request);
        }

        return response()->json([
            'message' => "Vous n'avez pas les droits pour accéder à cette ressource.",
        ], 403);
    }
}
