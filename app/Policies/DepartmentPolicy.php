<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
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

    public function view(User $user, Department $department): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }

    public function update(User $user, Department $department): bool
    {
        return in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }

    public function delete(User $user, Department $department): bool
    {
        return in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }
}
