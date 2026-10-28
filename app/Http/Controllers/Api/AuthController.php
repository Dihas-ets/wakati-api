<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/login
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

        if ($user->status === 'blocked') {
            throw ValidationException::withMessages([
                'email' => ["Ce compte a été bloqué par l'administrateur."],
            ]);
        }

        $token = $user->createToken('wakati-frontend')->plainTextToken;

        $user->update([
            'status' => User::STATUS_AU_TRAVAIL,
            'last_login_at' => now(),
        ]);

        $user->load(['department:id,name', 'positions:id,department_id,title,permissions,is_sensitive']);

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'job_title' => $user->job_title,
                'department' => $user->department,
                'department_id' => $user->department_id,
                'status' => $user->status,
                'card_uid' => $user->card_uid ?? $user->ensureCardUid(),
                'positions' => $user->positions,
                'is_admin' => $user->isAdmin(),
                'has_sensitive_role' => $user->hasSensitiveRole(),
            ],
        ]);
    }

    /**
     * POST /api/logout
     */
    public function logout(Request $request)
    {
        $request->user()->update(['status' => User::STATUS_PAS_POINTE]);
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    /**
     * GET /api/me
     */
    public function me(Request $request)
    {
        $user = $request->user()->load(['department:id,name', 'positions:id,department_id,title,permissions,is_sensitive']);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'job_title' => $user->job_title,
            'department' => $user->department,
            'department_id' => $user->department_id,
            'status' => $user->status,
            'card_uid' => $user->card_uid ?? $user->ensureCardUid(),
            'positions' => $user->positions,
            'is_admin' => $user->isAdmin(),
            'has_sensitive_role' => $user->hasSensitiveRole(),
        ]);
    }
}
