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
                'message' => "Non authentifié.",
            ], 401);
        }

        // Le super_admin a tous les droits d'administration
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Le company_admin a tous les droits d'administration pour son entreprise
        if ($user->isCompanyAdmin() && (
            in_array('company_admin', $roles, true) ||
            in_array('branch_manager', $roles, true) ||
            in_array('directeur', $roles, true) ||
            in_array('hr_manager', $roles, true) ||
            in_array('rh', $roles, true)
        )) {
            return $next($request);
        }

        // Vérifier si l'un des rôles demandés correspond au profil utilisateur
        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => "Vous n'avez pas les droits pour accéder à cette ressource.",
        ], 403);
    }
}
