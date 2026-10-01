<?php

namespace App\Policies;

use App\Models\ExplanationRequest;
use App\Models\User;

class ExplanationRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ExplanationRequest $explanationRequest): bool
    {
        if (in_array($user->role, [User::ROLE_DIRECTEUR, User::ROLE_RH], true)) {
            return true;
        }

        return $user->id === $explanationRequest->user_id;
    }

    public function respond(User $user, ExplanationRequest $explanationRequest): bool
    {
        return $user->id === $explanationRequest->user_id;
    }
}
