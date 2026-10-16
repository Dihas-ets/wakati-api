<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
{
    /**
     * Détermine si l'utilisateur peut voir la liste des pointages.
     * Les directeurs, RH et comptables peuvent lister, les employés ne voient que les leurs.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Détermine si l'utilisateur peut voir un pointage précis (anti-IDOR).
     */
    public function view(User $user, Attendance $attendance): bool
    {
        if (in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH, User::ROLE_COMPTABLE], true)) {
            return true;
        }

        return $user->id === $attendance->user_id;
    }

    /**
     * Détermine si l'utilisateur peut enregistrer un pointage pour un compte donné.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Modification d'un pointage : réservé à la Direction et RH.
     */
    public function update(User $user, Attendance $attendance): bool
    {
        return in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }

    /**
     * Suppression d'un pointage : réservé à la Direction.
     */
    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->role === User::ROLE_DIRECTEUR;
    }
}
