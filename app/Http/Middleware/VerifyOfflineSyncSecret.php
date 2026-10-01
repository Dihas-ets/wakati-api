<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyOfflineSyncSecret
{
    /**
     * Valide l'accès aux flux de synchronisation et de surveillance des bornes (heartbeat).
     * Autorise :
     * 1. Les terminaux matériels autonomes (Raspberry Pi/Python) via le header X-Sync-Secret et hash_equals().
     * 2. Les bornes PWA connectées avec une session Sanctum d'un utilisateur autorisé (Directeur ou RH).
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Authentification Sanctum pour utilisateur RH/Directeur (mode Kiosk PWA)
        $user = auth('sanctum')->user() ?? $request->user();
        if ($user && in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH], true)) {
            return $next($request);
        }

        // 2. Authentification par secret partagé pour terminal matériel (Raspberry Pi / daemon Python)
        $provided = (string) $request->header('X-Sync-Secret');
        $expected = (string) config('services.offline_sync.secret');

        if ($expected !== '' && $provided !== '' && hash_equals($expected, $provided)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Accès non autorisé : secret de synchronisation invalide ou session non privilégiée.',
        ], 401);
    }
}