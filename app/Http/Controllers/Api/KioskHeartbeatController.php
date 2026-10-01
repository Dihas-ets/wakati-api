<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kiosk\KioskHeartbeatRequest;
use App\Models\KioskHeartbeat;
use Carbon\Carbon;
use Illuminate\Http\Request;

class KioskHeartbeatController extends Controller
{
    /**
     * POST /api/kiosk/heartbeat
     * Enregistre le signal de vie périodique d'une borne ou d'un terminal de pointage.
     * Protégé contre les attaques par rejeu avec vérification de fraîcheur temporelle.
     */
    public function heartbeat(KioskHeartbeatRequest $request)
    {
        $validated = $request->validated();

        // Vérification de la fraîcheur du timestamp (Anti-Replay Attack)
        if (! empty($validated['timestamp'])) {
            $clientTime = Carbon::parse($validated['timestamp']);
            $now = now();

            // Rejeter si l'horodatage est dans le futur (+5 min) ou trop ancien (> 10 min)
            if ($clientTime->gt($now->copy()->addMinutes(5)) || $clientTime->lt($now->copy()->subMinutes(10))) {
                return response()->json([
                    'message' => 'Horodatage rejeté : écart temporel trop important (Anti-Replay).',
                    'server_time' => $now->toIso8601String(),
                ], 422);
            }
        }

        $heartbeat = KioskHeartbeat::updateOrCreate(
            ['kiosk_id' => $validated['kiosk_id']],
            [
                'ip_address' => $validated['ip_address'],
                'status' => $validated['status'] ?? 'online',
                'battery_level' => $validated['battery_level'] ?? null,
                'app_version' => $validated['app_version'] ?? null,
                'meta' => $validated['meta'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'status' => 'acknowledged',
            'server_time' => now()->toIso8601String(),
            'kiosk_id' => $heartbeat->kiosk_id,
        ]);
    }

    /**
     * GET /api/kiosk/heartbeats
     * Liste l'état en temps réel des bornes pour les RH et Directeurs.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', \App\Models\CompanySetting::class);

        $kiosks = KioskHeartbeat::orderBy('last_seen_at', 'desc')->get();

        return response()->json($kiosks);
    }
}
