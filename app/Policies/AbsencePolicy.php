<?php

namespace App\Policies;

use App\Models\Absence;
use App\Models\User;

class AbsencePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Absence $absence): bool
    {
        if (in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH, User::ROLE_COMPTABLE], true)) {
            return true;
        }

        return $user->id === $absence->user_id;
    }

    public function justify(User $user, Absence $absence): bool
    {
        return in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }
}
