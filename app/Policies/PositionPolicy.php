<?php

namespace App\Policies;

use App\Models\Position;
use App\Models\User;

class PositionPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Tous les utilisateurs authentifiés peuvent voir la liste des postes
    }

    public function view(User $user, Position $position): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('rh') || in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }

    public function update(User $user, Position $position): bool
    {
        if ($position->is_sensitive && ! $user->isAdmin()) {
            return false;
        }

        return $user->isAdmin() || $user->hasPermission('rh') || in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }

    public function delete(User $user, Position $position): bool
    {
        if ($position->is_sensitive && ! $user->isAdmin()) {
            return false;
        }

        return $user->isAdmin() || $user->hasPermission('rh') || in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }
}
