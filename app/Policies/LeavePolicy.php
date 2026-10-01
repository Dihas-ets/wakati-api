<?php

namespace App\Policies;

use App\Models\Leave;
use App\Models\User;

class LeavePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Leave $leave): bool
    {
        if (in_array($user->role, ['rh', 'directeur'])) {
            return true;
        }

        return $user->id === $leave->user_id;
    }

    /**
     * RH/Directeur créent un congé déjà approuvé pour n'importe qui.
     * Un employé peut créer une DEMANDE (en attente) pour lui-même.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['rh', 'directeur', 'employe', 'comptable']);
    }

    public function update(User $user, Leave $leave): bool
    {
        return in_array($user->role, ['rh', 'directeur']);
    }

    public function delete(User $user, Leave $leave): bool
    {
        if (in_array($user->role, ['rh', 'directeur'])) {
            return true;
        }

        // Un employé peut retirer sa propre demande tant qu'elle est en attente.
        return $user->id === $leave->user_id && $leave->status === Leave::STATUS_PENDING;
    }

    public function decide(User $user, Leave $leave): bool
    {
        return in_array($user->role, ['rh', 'directeur']);
    }
}