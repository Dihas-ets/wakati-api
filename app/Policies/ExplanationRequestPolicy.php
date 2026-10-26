<?php

namespace App\Policies;

use App\Models\ExplanationRequest;
use App\Models\User;

class ExplanationRequestPolicy
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

    public function view(User $user, ExplanationRequest $explanationRequest): bool
    {
        if (in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH, User::ROLE_COMPANY_ADMIN, User::ROLE_SUPER_ADMIN], true)) {
            return true;
        }

        return $user->id === $explanationRequest->user_id;
    }

    public function respond(User $user, ExplanationRequest $explanationRequest): bool
    {
        return $user->id === $explanationRequest->user_id;
    }
}
