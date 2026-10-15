<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
{
    /**
     * Bypasser pour le SUPER_ADMIN
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Détermine si l'utilisateur peut voir la liste des pointages.
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
        if ($attendance->company_id && $user->company_id !== $attendance->company_id) {
            return false;
        }

        if ($user->isCompanyAdmin()) {
            return true;
        }

        if (($user->isBranchManager() || $user->isHrManager()) && $user->branch_id === $attendance->branch_id) {
            return true;
        }

        return $user->id === $attendance->user_id;
    }

    /**
     * Détermine si l'utilisateur peut enregistrer un pointage.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Modification / régularisation d'un pointage :
     * - COMPANY_ADMIN : sur toute l'entreprise.
     * - BRANCH_MANAGER / HR_MANAGER : uniquement sur son agence.
     */
    public function update(User $user, Attendance $attendance): bool
    {
        if ($attendance->company_id && $user->company_id !== $attendance->company_id) {
            return false;
        }

        if ($user->isCompanyAdmin()) {
            return true;
        }

        return ($user->isBranchManager() || $user->isHrManager()) && $user->branch_id === $attendance->branch_id;
    }

    /**
     * Suppression d'un pointage : réservé au COMPANY_ADMIN ou au BRANCH_MANAGER de l'agence.
     */
    public function delete(User $user, Attendance $attendance): bool
    {
        if ($attendance->company_id && $user->company_id !== $attendance->company_id) {
            return false;
        }

        if ($user->isCompanyAdmin()) {
            return true;
        }

        return $user->isBranchManager() && $user->branch_id === $attendance->branch_id;
    }
}
