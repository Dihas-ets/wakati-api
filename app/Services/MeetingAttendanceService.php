<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class MeetingAttendanceService
{
    /**
     * Émargement autonome par l'employé connecté (Self-Check-in Jour J).
     *
     * @throws ValidationException
     */
    public function selfCheckIn(Meeting $meeting, User $user, string $method = 'web'): MeetingParticipant
    {
        // 1. Vérification que la réunion est bien aujourd'hui
        if (! $meeting->is_today) {
            throw ValidationException::withMessages([
                'date' => ['Le pointage autonome n\'est disponible que le jour de la réunion programmée (' . Carbon::parse($meeting->date)->format('d/m/Y') . ').'],
            ]);
        }

        // 2. Vérification de l'état de la réunion
        if (in_array($meeting->status, [Meeting::STATUS_COMPLETED, Meeting::STATUS_DRAFT])) {
            throw ValidationException::withMessages([
                'status' => ['Cette réunion n\'est pas ouverte aux émargements (statut : ' . $meeting->status . ').'],
            ]);
        }

        // 3. Recherche du participant
        $participant = MeetingParticipant::where('meeting_id', $meeting->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $participant) {
            throw ValidationException::withMessages([
                'user' => ['Vous ne faites pas partie de la liste des collaborateurs convoqués pour cette réunion.'],
            ]);
        }

        if ($participant->attendance_status === MeetingParticipant::STATUS_PRESENT) {
            return $participant; // Déjà pointé présent
        }

        $chosenMethod = in_array($method, [MeetingParticipant::METHOD_MOBILE_APP, MeetingParticipant::METHOD_WEB])
            ? $method
            : MeetingParticipant::METHOD_WEB;

        $participant->update([
            'attendance_status' => MeetingParticipant::STATUS_PRESENT,
            'checked_in_at' => Carbon::now(),
            'checkin_method' => $chosenMethod,
        ]);

        // Si la réunion était convoquée et que les pointages commencent, on la bascule si besoin en cours
        if ($meeting->status === Meeting::STATUS_CONVOKED) {
            $meeting->update(['status' => Meeting::STATUS_ONGOING]);
        }

        // Journalisation de l'activité
        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'meeting_self_checkin',
            'description' => "Émargement autonome à la réunion '{$meeting->title}' via {$chosenMethod}.",
            'metadata' => [
                'meeting_id' => $meeting->id,
                'method' => $chosenMethod,
                'time' => Carbon::now()->toIso8601String(),
            ],
        ]);

        return $participant->fresh(['user']);
    }

    /**
     * Émargement via QR Code ou Badge RFID physique.
     *
     * @throws ValidationException
     */
    public function checkInByBadge(Meeting $meeting, string $rawCode, string $method = 'qr_code'): array
    {
        $normalizedCode = User::normalizeCardUid($rawCode);

        $user = User::where('card_uid', $normalizedCode)
            ->orWhere('card_uid', $rawCode)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'badge' => ['Identifiant de badge ou QR code non reconnu.'],
            ]);
        }

        $participant = MeetingParticipant::firstOrCreate(
            ['meeting_id' => $meeting->id, 'user_id' => $user->id],
            ['attendance_status' => MeetingParticipant::STATUS_INVITED]
        );

        $checkMethod = in_array($method, [MeetingParticipant::METHOD_QR, MeetingParticipant::METHOD_RFID])
            ? $method
            : MeetingParticipant::METHOD_QR;

        $participant->update([
            'attendance_status' => MeetingParticipant::STATUS_PRESENT,
            'checked_in_at' => Carbon::now(),
            'checkin_method' => $checkMethod,
        ]);

        return [
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'job_title' => $user->job_title,
            ],
            'attendance_status' => MeetingParticipant::STATUS_PRESENT,
            'checked_in_at' => $participant->checked_in_at->toIso8601String(),
            'checkin_method' => $checkMethod,
            'stats' => $meeting->fresh()->attendance_stats,
        ];
    }

    /**
     * Émargement manuel par l'animateur de la réunion ou le service RH.
     */
    public function checkInManual(
        Meeting $meeting,
        int $userId,
        string $status = 'present',
        ?string $method = 'manual'
    ): MeetingParticipant {
        $allowedStatuses = [
            MeetingParticipant::STATUS_PRESENT,
            MeetingParticipant::STATUS_ABSENT,
            MeetingParticipant::STATUS_EXCUSED,
            MeetingParticipant::STATUS_INVITED,
        ];

        if (! in_array($status, $allowedStatuses)) {
            $status = MeetingParticipant::STATUS_PRESENT;
        }

        $participant = MeetingParticipant::firstOrCreate(
            ['meeting_id' => $meeting->id, 'user_id' => $userId],
            ['attendance_status' => MeetingParticipant::STATUS_INVITED]
        );

        $participant->update([
            'attendance_status' => $status,
            'checked_in_at' => $status === MeetingParticipant::STATUS_PRESENT ? Carbon::now() : null,
            'checkin_method' => $status === MeetingParticipant::STATUS_PRESENT ? ($method ?? 'manual') : null,
        ]);

        return $participant->fresh(['user']);
    }
}
