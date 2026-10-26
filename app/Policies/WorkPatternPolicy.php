<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkPattern;

class WorkPatternPolicy
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

    public function view(User $user, WorkPattern $workPattern): bool
    {
        if (in_array($user->role, ['rh', 'directeur', 'company_admin', 'super_admin'])) {
            return true;
        }

        return $user->id === $workPattern->user_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['rh', 'directeur', 'company_admin', 'super_admin']);
    }

    public function update(User $user, WorkPattern $workPattern): bool
    {
        return in_array($user->role, ['rh', 'directeur', 'company_admin', 'super_admin']);
    }

    public function delete(User $user, WorkPattern $workPattern): bool
    {
        return in_array($user->role, ['rh', 'directeur', 'company_admin', 'super_admin']);
    }
}