<?php

namespace App\Policies;

use App\Models\Absence;
use App\Models\User;

class AbsencePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if (in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN], true)) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Absence $absence): bool
    {
        if (in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH, User::ROLE_COMPTABLE, User::ROLE_COMPANY_ADMIN, User::ROLE_SUPER_ADMIN], true)) {
            return true;
        }

        return $user->id === $absence->user_id;
    }

    public function justify(User $user, Absence $absence): bool
    {
        return in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH, User::ROLE_COMPANY_ADMIN, User::ROLE_SUPER_ADMIN], true);
    }
}
