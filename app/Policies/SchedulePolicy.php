<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\User;

class SchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Schedule $schedule): bool
    {
        if (in_array($user->role, ['rh', 'directeur'])) {
            return true;
        }

        return $user->id === $schedule->user_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['rh', 'directeur']);
    }

    public function update(User $user, Schedule $schedule): bool
    {
        return in_array($user->role, ['rh', 'directeur']);
    }

    public function delete(User $user, Schedule $schedule): bool
    {
        return in_array($user->role, ['rh', 'directeur']);
    }
}