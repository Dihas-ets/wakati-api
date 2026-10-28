<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || in_array($this->user()?->role, [User::ROLE_DIRECTEUR, User::ROLE_RH], true) || $this->user()?->hasPermission('rh');
    }

    public function rules(): array
    {
        $targetUser = $this->route('user');
        $targetId = $targetUser instanceof User ? $targetUser->id : $targetUser;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($targetId)],
            'password' => ['nullable', 'string', 'min:8', 'max:128'],
            'role' => ['sometimes', 'nullable', Rule::in(User::ROLES)],
            'job_title' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'is_blocked' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'max:50'],
            'primary_position_id' => ['sometimes', 'nullable', 'integer', 'exists:positions,id'],
            'secondary_position_ids' => ['sometimes', 'nullable', 'array'],
            'secondary_position_ids.*' => ['integer', 'exists:positions,id'],
            'position_ids' => ['sometimes', 'nullable', 'array'],
            'position_ids.*' => ['integer', 'exists:positions,id'],
        ];
    }
}
