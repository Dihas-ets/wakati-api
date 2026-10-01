<?php

namespace App\Policies;

use App\Models\User;

class CompanySettingPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }

    public function update(User $user): bool
    {
        return in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }
}
