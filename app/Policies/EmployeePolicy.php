<?php

namespace App\Policies;

use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, User $model): bool
    {
        if ($user->isAdmin() || in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN, User::ROLE_MANAGER, User::ROLE_DIRECTEUR, User::ROLE_RH], true)) {
            return true;
        }

        return $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin()
            || $user->hasPermission('rh')
            || in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN, User::ROLE_MANAGER, User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }

    public function update(User $user, User $model): bool
    {
        if ($user->isAdmin() || in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN], true)) {
            return true;
        }

        if ($model->isAdmin()) {
            return false;
        }

        return $user->hasPermission('rh') || in_array($user->role, [User::ROLE_MANAGER, User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if ($user->isAdmin() || in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN], true)) {
            return true;
        }

        if ($model->isAdmin() || $model->hasSensitiveRole()) {
            return false;
        }

        return $user->hasPermission('rh') || in_array($user->role, [User::ROLE_MANAGER, User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }

    /**
     * Autoriser l'attribution de postes sensibles (is_sensitive = true) UNIQUEMENT à company_admin ou super_admin.
     */
    public function assignSensitivePosition(User $user): bool
    {
        return in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN, User::ROLE_DIRECTEUR], true);
    }
}
