<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class KioskSecurityController extends Controller
{
    /**
     * POST /api/kiosk/verify-access
     * Vérifie si l'utilisateur a le droit d'accéder à la page /pointage-offline.
     * Réservé exclusivement au DIRECTEUR et aux RH.
     * Si un employé tente de s'y connecter, le serveur enregistre l'incident
     * et envoie immédiatement une alerte par email au(x) directeur(s).
     */
    public function verifyAccess(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'authorized' => false,
                'message' => 'Non authentifié. Veuillez vous connecter.',
            ], 401);
        }

        $isAuthorized = in_array($user->role, [
            User::ROLE_DIRECTEUR,
            User::ROLE_RH,
            User::ROLE_COMPANY_ADMIN,
            User::ROLE_SUPER_ADMIN,
        ], true);

        if (! $isAuthorized) {
            // 1. Enregistrement dans le journal d'activité de sécurité
            ActivityLog::record(
                $request,
                'security.unauthorized_kiosk_access',
                $user,
                [
                    'employee_id' => $user->id,
                    'employee_name' => $user->name,
                    'employee_email' => $user->email,
                    'employee_role' => $user->role,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'attempted_at' => now()->toIso8601String(),
                ]
            );

            // 2. Journal système
            Log::warning("Tentative d'accès non autorisée à la borne de pointage par l'utilisateur ID: {$user->id} ({$user->name}, {$user->email}, rôle: {$user->role}) depuis l'adresse IP: {$request->ip()}");

            // 3. Alerte envoyée au(x) directeur(s)
            $directors = User::where('role', User::ROLE_DIRECTEUR)->get();

            foreach ($directors as $director) {
                try {
                    $ip = $request->ip() ?: 'Non renseignée';
                    $userAgent = $request->userAgent() ?: 'Inconnu';
                    $date = now()->format('d/m/Y à H:i:s');

                    Mail::raw(
                        "⚠️ ALERTE DE SÉCURITÉ WAKATI ⚠️\n\n" .
                        "Une tentative d'accès non autorisée à la borne de pointage (/pointage-offline) a été détectée et bloquée par le système.\n\n" .
                        "INFORMATIONS SUR L'UTILISATEUR :\n" .
                        "--------------------------------------------------\n" .
                        "• Nom de l'employé : {$user->name}\n" .
                        "• Adresse email    : {$user->email}\n" .
                        "• Rôle actuel      : {$user->role}\n" .
                        "• Adresse IP       : {$ip}\n" .
                        "• Date et heure    : {$date}\n" .
                        "• Navigateur/App   : {$userAgent}\n\n" .
                        "MESURE DE PROTECTION :\n" .
                        "L'accès a été immédiatement refusé. L'employé a été consigné dans les journaux d'activité.\n\n" .
                        "— Le Système de Contrôle d'Accès Wakati",
                        function ($message) use ($director, $user) {
                            $message->to($director->email)
                                ->subject("🚨 ALERTE SÉCURITÉ WAKATI : Tentative d'accès non autorisée de {$user->name}");
                        }
                    );
                } catch (\Throwable $e) {
                    Log::error("Échec de l'envoi de l'alerte email au directeur ({$director->email}): " . $e->getMessage());
                }
            }

            return response()->json([
                'authorized' => false,
                'role' => $user->role,
                'message' => "Accès refusé. La borne de pointage est strictement réservée au Directeur et aux RH. Une alerte de sécurité a été transmise à la Direction.",
            ], 403);
        }

        return response()->json([
            'authorized' => true,
            'role' => $user->role,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'message' => "Accès autorisé à la borne de pointage.",
        ]);
    }
}
