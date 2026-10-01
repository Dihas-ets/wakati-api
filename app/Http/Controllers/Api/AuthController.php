<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/login
     * Body attendu : { "email": "...", "password": "..." }
     * Réponse : { "token": "...", "user": { id, name, email, role } }
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        // Un seul token actif à la fois par appareil ; on peut nommer le token
        // avec le user-agent pour le retrouver plus tard si besoin.
        $token = $user->createToken('wakati-frontend')->plainTextToken;

        $user->update([
            'status' => User::STATUS_AU_TRAVAIL,
            'last_login_at' => now(),
        ]);

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'card_uid' => $user->card_uid ?? $user->ensureCardUid(),
            ],
        ]);
    }

    /**
     * POST /api/logout
     * Nécessite d'être authentifié (Bearer token).
     * Révoque uniquement le token utilisé pour cette requête.
     */
    public function logout(Request $request)
    {
        $request->user()->update(['status' => User::STATUS_PAS_POINTE]);
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    /**
     * GET /api/me
     * Retourne l'utilisateur actuellement authentifié.
     */
    public function me(Request $request)
    {
        $user = $request->user()->load('department:id,name');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'job_title' => $user->job_title,
            'department' => $user->department,
            'status' => $user->status,
            'card_uid' => $user->card_uid ?? $user->ensureCardUid(),
        ]);
    }
}
