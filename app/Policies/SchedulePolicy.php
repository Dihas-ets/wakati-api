<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\User;

class SchedulePolicy
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

    public function view(User $user, Schedule $schedule): bool
    {
        if (in_array($user->role, ['rh', 'directeur', 'company_admin', 'super_admin'])) {
            return true;
        }

        return $user->id === $schedule->user_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['rh', 'directeur', 'company_admin', 'super_admin']);
    }

    public function update(User $user, Schedule $schedule): bool
    {
        return in_array($user->role, ['rh', 'directeur', 'company_admin', 'super_admin']);
    }

    public function delete(User $user, Schedule $schedule): bool
    {
        return in_array($user->role, ['rh', 'directeur', 'company_admin', 'super_admin']);
    }
}