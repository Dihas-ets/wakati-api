<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkPattern;

class WorkPatternPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WorkPattern $workPattern): bool
    {
        if (in_array($user->role, ['rh', 'directeur'])) {
            return true;
        }

        return $user->id === $workPattern->user_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['rh', 'directeur']);
    }

    public function update(User $user, WorkPattern $workPattern): bool
    {
        return in_array($user->role, ['rh', 'directeur']);
    }

    public function delete(User $user, WorkPattern $workPattern): bool
    {
        return in_array($user->role, ['rh', 'directeur']);
    }
}