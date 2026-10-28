<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage dans routes/api.php :
 *   Route::middleware(['auth:sanctum', 'role:directeur'])->group(...)
 *   Route::middleware(['auth:sanctum', 'role:rh,directeur'])->group(...)
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => "Vous n'avez pas les droits pour accéder à cette ressource.",
            ], 403);
        }

        // Si l'utilisateur a le rôle directement
        if (in_array($user->role, $roles, true)) {
            return $next($request);
        }

        // Si l'utilisateur est admin/directeur
        if ($user->isAdmin()) {
            return $next($request);
        }

        // Vérification par permissions dérivées des postes
        foreach ($roles as $role) {
            if ($user->hasPermission($role)) {
                return $next($request);
            }
            if ($role === 'comptable' && ($user->hasPermission('accounting') || $user->hasPermission('comptabilite'))) {
                return $next($request);
            }
            if ($role === 'rh' && ($user->hasPermission('hr') || $user->hasPermission('human_resources'))) {
                return $next($request);
            }
            if ($role === 'directeur' && ($user->hasPermission('admin') || $user->hasPermission('direction'))) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => "Vous n'avez pas les droits pour accéder à cette ressource.",
        ], 403);
    }
}
