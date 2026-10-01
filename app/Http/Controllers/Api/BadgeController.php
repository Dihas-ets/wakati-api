<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BadgePairing;
use App\Models\User;
use Illuminate\Http\Request;

class BadgeController extends Controller
{
    /**
     * POST /api/employees/{user}/badge-pairing
     * RH/Directeur met un employé "en attente de scan" pendant 5 minutes.
     * Le prochain badge inconnu scanné sur le terminal sera associé à lui.
     */
    public function startPairing(Request $request, User $user)
    {
        $pairing = BadgePairing::create([
            'user_id' => $user->id,
            'initiated_by' => $request->user()->id,
            'expires_at' => now()->addMinutes(5),
        ]);

        return response()->json([
            'message' => "En attente du scan du badge pour {$user->name} (5 minutes).",
            'expires_at' => $pairing->expires_at,
        ], 201);
    }

    /**
     * POST /api/employees/{user}/ensure-badge
     * Garantit un card_uid unique (QR + badge physique) pour l'employé.
     */
    public function ensureBadge(User $user)
    {
        $user->ensureCardUid();
        $user->load('department:id,name');

        return response()->json($user);
    }
}