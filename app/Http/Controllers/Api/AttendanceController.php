<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\CheckInRequest;
use App\Http\Requests\Attendance\SyncOfflineRequest;
use App\Models\Absence;
use App\Models\Attendance;
use App\Models\BadgePairing;
use App\Models\CompanySetting;
use App\Models\Leave;
use App\Models\Schedule;
use App\Models\User;
use App\Models\WorkPattern;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /**
     * GET /api/attendances?from=&to=&user_id=
     */
    public function index(Request $request)
    {
        $query = Attendance::query();

        if (in_array($request->user()->role, ['employe', 'comptable'])) {
            $query->where('user_id', $request->user()->id);
        } elseif ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('from')) {
            $query->where('date', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('date', '<=', $request->to);
        }

        return response()->json(
            $query->with('user:id,name,email')->get()
        );
    }

    public function checkIn(CheckInRequest $request)
    {
         $user = $request->user();
        $clientIp = $request->ip();
        $now = Carbon::now(CompanySetting::get('timezone', config('app.timezone')));
        $today = $now->toDateString();

        $validated = $request->validated();
        $mode = $validated['mode'] ?? 'manual';

        if ($mode === 'qr') {
            $badgeUser = User::where('card_uid', $validated['badge_code'])->first();

            if (! $badgeUser) {
                return response()->json([
                    'message' => 'Badge QR inconnu. Ce code ne correspond à aucun employé.',
                ], 422);
            }

            if ($badgeUser->id !== $user->id) {
                return response()->json([
                    'message' => 'Ce badge ne correspond pas à votre compte.',
                ], 403);
            }
        }

        if ($mode === 'auto') {
            $modeSetting = CompanySetting::where('key', 'auto_checkin_mode')->first();
            $autoCheckinMode = $modeSetting ? $modeSetting->value : 'disabled';

            if ($autoCheckinMode === 'disabled') {
                return response()->json([
                    'message' => 'Le pointage automatique est désactivé pour votre entreprise.',
                ], 422);
            }
        }

        if (Leave::where('user_id', $user->id)->whereDate('date', $today)->exists()) {
            return response()->json([
                'message' => "Vous êtes en congé aujourd'hui, le pointage n'est pas nécessaire.",
            ], 422);
        }

        $isDirector = $user->role === User::ROLE_DIRECTEUR;
        $schedule = Schedule::where('user_id', $user->id)->whereDate('date', $today)->first();

        $workPattern = null;
        $startTime = null;
        $endTime = null;
        $isRemote = false;

        if ($schedule) {
            $startTime = $schedule->start_time;
            $endTime = $schedule->end_time;
            $isRemote = $schedule->is_remote;
        } else {
            $workPattern = WorkPattern::activeFor($user->id, $now);

            if (! $workPattern) {
                if ($isDirector) {
                    $startTime = '08:00';
                    $endTime = '23:59';
                } else {
                    return response()->json([
                        'message' => "Aucun créneau de travail prévu pour vous aujourd'hui.",
                    ], 404);
                }
            } else {
                $startTime = $workPattern->start_time;
                $endTime = $workPattern->end_time;
            }
        }

        $tz = CompanySetting::get('timezone', config('app.timezone'));
        $startShift = Carbon::parse($today.' '.$startTime, $tz);
        $endShift = Carbon::parse($today.' '.$endTime, $tz);
        if ($endShift->lt($startShift)) {
            $endShift->addDay();
        }

        if (
            ! $isDirector && (
                $now->copy()->addMinutes(45)->lt($startShift)
                || $now->gt($endShift)
            )
        ) {
            return response()->json([
                'message' => 'Aucun créneau de travail actif trouvé pour vous à cette heure.',
            ], 404);
        }

        if (Attendance::where('user_id', $user->id)->whereDate('date', $today)->exists()) {
            return response()->json(['message' => 'Vous avez déjà pointé aujourd\'hui.'], 422);
        }

        $clientIpv4 = $this->normalizeToIpv4($clientIp);

        if (! $isRemote) {
            $allowedIpsSetting = CompanySetting::where('key', 'allowed_ips')->first();
            $allowedIps = $allowedIpsSetting ? $allowedIpsSetting->value : [];

            if (! empty($allowedIps) && ! $this->isIpv4Allowed($clientIp, $allowedIps)) {
                return response()->json([
                    'message' => "Pointage refusé. Vous devez être connecté au réseau Wi-Fi de l'entreprise.",
                ], 403);
            }
        }

        $attendance = $this->recordAttendance(
            $user->id, $now, $startTime, $schedule?->id, $workPattern?->id, $clientIpv4 ?? $clientIp,
            $mode === 'auto' ? 'auto' : ($mode === 'qr' ? 'qr' : 'online'),
            null,
            $validated['latitude'] ?? null,
            $validated['longitude'] ?? null
        );

        return response()->json([
            'message' => $attendance->status === 'late' ? 'Pointage enregistré (En retard).' : 'Pointage réussi (À l\'heure).',
            'attendance' => $attendance,
        ], 201);
    }

    public function checkOut(Request $request)
    {
        $user = $request->user();
        $now = Carbon::now(CompanySetting::get('timezone', config('app.timezone')));
        $today = $now->toDateString();

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if (! $attendance) {
            return response()->json([
                'message' => "Vous n'avez pas encore pointé votre arrivée aujourd'hui.",
            ], 422);
        }

        if ($attendance->check_out_time) {
            return response()->json([
                'message' => "Vous avez déjà pointé votre départ aujourd'hui.",
            ], 422);
        }
        
        // Grade-fou anti-erreur : un depart pointé après l'arrivée est
        // quasi toujours accidentel (double tap, requete rejouée, appli rechargéé)

        $minDelayMinutes == max(0, (int) CompanySetting::get('min_checkout_delay_minutes', 5));
        if ($minDelayMinutes > 0) {
            $earliestCheckOut = $attendance->check_in_time->copy()->addMinutes($minDelayMinutes);
            $remainingSeconds = $earliestCheckOut->getTimestamp() - $now->getTimestamp();

            if ($remainingSeconds > 0) {
                $remainingMinutes = max(1, (int) ceil($remainingSeconds / 60));

                return response()->json([
                    'message' => "Départ refusé: vous venez de pointer votre attivée. "
                         ."Réessayez dasn {$remainingMinutes} minute(s) si ce départ est voulu. ",
                ], 422);
            }
        }

         // Ne pas écraser le statut de ponctualité (late / on_time) :
        // la fin de journée est indiquée par check_out_time uniquement.
        $attendance->update([
            'check_out_time' => $now,
        ]);

        return response()->json([
            'message' => 'Départ pointé avec succès.',
            'attendance' => $attendance,
        ]);
    }


    /**
     * GET /api/attendance/valid-badges
     * Permet à un client hors-ligne (kiosk PWA ou terminal Python) de
     * télécharger/rafraîchir sa table locale de badges valides *avant*
     * même d'avoir un scan à synchroniser (ex: démarrage du kiosk,
     * rafraîchissement périodique pendant qu'il est en ligne).
     * Protégée par le middleware 'offline-sync' (secret partagé), comme
     * sync-offline : ces clients n'ont pas de compte utilisateur/session.
     */
    public function validBadges()
    {
        return response()->json([
            'valid_badges' => $this->validBadgesPayload(),
        ]);
    }

    /**
     * POST /api/attendance/sync-offline
     * Appelée par le daemon du terminal de secours, protégée par le
     * middleware 'offline-sync' (secret partagé, pas de compte utilisateur).
     * Body : { scans: [{ card_uid, scanned_at, client_scan_id }, ...] }
     */
    public function syncOffline(SyncOfflineRequest $request)
    {
        $validated = $request->validated();

        // Du plus ancien au plus récent : le premier scan du jour fait foi pour
        // le retard, les suivants sont des "duplicate_day". L'ordre d'arrivée
        // (IndexedDB trie par identifiant) n'est pas chronologique.
        $scans = collect($validated['scans'])
            ->sortBy(fn (array $scan) => Carbon::parse($scan['scanned_at'])->getTimestamp())
            ->values()
            ->all();

        $results = DB::transaction(function () use ($scans) {
            $batchResults = [];
            foreach ($scans as $scan) {
                $batchResults[] = $this->processOfflineScan($scan);
            }
            return $batchResults;
        });

        // Renvoie aussi la liste à jour des badges valides, pour que le
        // terminal rafraîchisse sa base locale valid_badges.
        return response()->json([
            'results' => $results,
            'valid_badges' => $this->validBadgesPayload(),
        ]);
    }

    /**
     * Table complète des badges valides, au format attendu par les stores
     * locaux (IndexedDB côté PWA, SQLite côté terminal Python) :
     * card_uid => { user_id, name }. Inclure le nom permet un affichage
     * immédiat ("Bonjour Jean Dupont") sans attendre une synchronisation.
     *
     * @return array<string, array{user_id: int, name: string}>
     */
    private function validBadgesPayload(): array
    {
        return User::whereNotNull('card_uid')
            ->get(['id', 'name', 'card_uid'])
            ->mapWithKeys(fn (User $user) => [
                User::normalizeCardUid($user->card_uid) => ['user_id' => $user->id, 'name' => $user->name],
            ])
            ->all();
    }

    private function processOfflineScan(array $scan): array
    {
        // Idempotence : si ce scan a déjà été synchronisé (retry réseau du
        // daemon), on ne le traite pas deux fois.
        if (Attendance::where('sync_uuid', $scan['client_scan_id'])->exists()) {
            return ['client_scan_id' => $scan['client_scan_id'], 'status' => 'already_synced'];
        }

        $cardUid = User::normalizeCardUid($scan['card_uid']);
        $user = User::where('card_uid', $cardUid)->first();

        if (! $user) {
            $pairing = BadgePairing::activePairing();

            if (! $pairing) {
                return [
                    'client_scan_id' => $scan['client_scan_id'],
                    'status' => 'unknown_badge',
                    'card_uid' => $cardUid,
                ];
            }

            $user = $pairing->user;
            $user->update(['card_uid' => $cardUid]);
            $pairing->update(['used_at' => now()]);
        }

        $scannedAt = Carbon::parse($scan['scanned_at'])
            ->setTimezone(CompanySetting::get('timezone', config('app.timezone')));

        // Vérification de fraîcheur temporelle (Anti-Replay Attack)
        if ($scannedAt->gt(now()->addMinutes(15))) {
            return [
                'client_scan_id' => $scan['client_scan_id'],
                'status' => 'rejected_future_timestamp',
                'message' => 'Horodatage rejeté : timestamp futur non valide.',
            ];
        }

        $date = $scannedAt->toDateString();

        if (Attendance::where('user_id', $user->id)->whereDate('date', $date)->exists()) {
            return ['client_scan_id' => $scan['client_scan_id'], 'status' => 'duplicate_day', 'user_id' => $user->id];
        }

        $schedule = Schedule::where('user_id', $user->id)->whereDate('date', $date)->first();
        $workPattern = $schedule ? null : WorkPattern::activeFor($user->id, $scannedAt);
        $startTime = $schedule?->start_time ?? $workPattern?->start_time;

        if (! $startTime) {
            return ['client_scan_id' => $scan['client_scan_id'], 'status' => 'no_schedule', 'user_id' => $user->id];
        }

        $attendance = $this->recordAttendance(
            $user->id, $scannedAt, $startTime, $schedule?->id, $workPattern?->id,
            null, 'offline_sync', $scan['client_scan_id']
        );

        // Si une absence auto avait déjà été créée pour ce jour (le terminal
        // n'avait pas pu synchroniser à temps), on la reconnaît justifiée :
        // l'employé était bien présent.
        Absence::where('user_id', $user->id)
            ->whereDate('date', $date)
            ->where('status', 'unexcused')
            ->update([
                'status' => 'justified',
                'reason' => 'Pointage hors-ligne synchronisé a posteriori.',
            ]);

        return [
            'client_scan_id' => $scan['client_scan_id'],
            'status' => 'recorded',
            'user_id' => $user->id,
            'attendance_status' => $attendance->status,
        ];
    }

    private function recordAttendance(
        int $userId, Carbon $timestamp, string $startTime,
        ?int $scheduleId, ?int $workPatternId, ?string $ip, string $source, ?string $syncUuid = null,
        ?float $latitude = null, ?float $longitude = null
    ): Attendance {
        $user = User::find($userId);
        $toleranceSetting = CompanySetting::where('key', 'late_tolerance_minutes')->first();
        $toleranceMinutes = $toleranceSetting ? (int) $toleranceSetting->value : 10;

        if ($user?->role === User::ROLE_DIRECTEUR && ! $scheduleId && ! $workPatternId) {
            $status = 'on_time';
        } else {
            $scheduledStart = Carbon::parse($timestamp->toDateString().' '.$startTime);
            $status = $timestamp->greaterThan($scheduledStart->copy()->addMinutes($toleranceMinutes))
                ? 'late' : 'on_time';
        }

        return Attendance::create([
            'user_id' => $userId,
            'schedule_id' => $scheduleId,
            'work_pattern_id' => $workPatternId,
            'date' => $timestamp->toDateString(),
            'check_in_time' => $timestamp,
            'status' => $status,
            'ip_address' => $ip,
            'source' => $source,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'sync_uuid' => $syncUuid,
        ]);
    }

    /**
     * Normalise une chaîne IP vers une adresse IPv4 standard.
     * Convertit '::1' en '127.0.0.1' et retire les préfixes IPv4 mappés en IPv6 ('::ffff:').
     */
    public function normalizeToIpv4(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        $ip = trim($ip);

        if ($ip === '::1') {
            return '127.0.0.1';
        }

        if (preg_match('/^::ffff:(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/i', $ip, $matches)) {
            $ip = $matches[1];
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $ip;
        }

        return null;
    }

    /**
     * Vérifie si l'adresse IP cliente (normalisée en IPv4) est autorisée par la liste d'IPs.
     * La liste autorisée peut contenir des IPv4 exactes (ex: '192.168.1.15', '127.0.0.1'),
     * des masques CIDR (ex: '192.168.1.0/24') ou des motifs génériques (ex: '192.168.1.*').
     */
    public function isIpv4Allowed(string $clientIp, array $allowedIps): bool
    {
        $clientIpv4 = $this->normalizeToIpv4($clientIp);

        if (! $clientIpv4) {
            return false;
        }

        foreach ($allowedIps as $allowed) {
            $allowed = trim((string) $allowed);
            if ($allowed === '') {
                continue;
            }

            // 1. Support CIDR (ex: 192.168.1.0/24)
            if (str_contains($allowed, '/')) {
                [$subnet, $mask] = explode('/', $allowed, 2);
                $subnetIpv4 = $this->normalizeToIpv4($subnet);
                if ($subnetIpv4 && is_numeric($mask)) {
                    $mask = (int) $mask;
                    if ($mask >= 0 && $mask <= 32) {
                        $ipLong = ip2long($clientIpv4);
                        $subnetLong = ip2long($subnetIpv4);
                        if ($ipLong !== false && $subnetLong !== false) {
                            $maskLong = $mask === 0 ? 0 : (~0 << (32 - $mask));
                            if (($ipLong & $maskLong) === ($subnetLong & $maskLong)) {
                                return true;
                            }
                        }
                    }
                }
                continue;
            }

            // 2. Support Wildcard (ex: 192.168.1.*)
            if (str_contains($allowed, '*')) {
                $pattern = '/^'.str_replace(['.', '*'], ['\.', '\d+'], $allowed).'$/';
                if (preg_match($pattern, $clientIpv4)) {
                    return true;
                }
                continue;
            }

            // 3. Correspondance IPv4 exacte après normalisation
            $allowedIpv4 = $this->normalizeToIpv4($allowed);
            if ($allowedIpv4 && $clientIpv4 === $allowedIpv4) {
                return true;
            }
        }

        return false;
    }
}