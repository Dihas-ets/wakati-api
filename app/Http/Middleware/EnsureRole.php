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

        if (! $user || ! in_array($user->role, $roles, true)) {
            return response()->json([
                'message' => "Vous n'avez pas les droits pour accéder à cette ressource.",
            ], 403);
        }

        return $next($request);
    }
}
