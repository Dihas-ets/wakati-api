<?php

namespace App\Policies;

use App\Models\Position;
use App\Models\User;

class PositionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Position $position): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin()
            || $user->hasPermission('rh')
            || in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN, User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }

    public function update(User $user, Position $position): bool
    {
        if ($position->is_sensitive && ! $this->canManageSensitive($user)) {
            return false;
        }

        return $user->isAdmin()
            || $user->hasPermission('rh')
            || in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN, User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }

    public function delete(User $user, Position $position): bool
    {
        if ($position->is_sensitive && ! $this->canManageSensitive($user)) {
            return false;
        }

        return $user->isAdmin()
            || $user->hasPermission('rh')
            || in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN, User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }

    public function canManageSensitive(User $user): bool
    {
        return in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN, User::ROLE_DIRECTEUR], true);
    }
}
