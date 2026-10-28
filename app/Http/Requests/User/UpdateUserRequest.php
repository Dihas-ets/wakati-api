<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return $u?->isAdmin()
            || $u?->hasPermission('rh')
            || in_array($u?->role, [
                User::ROLE_SUPER_ADMIN,
                User::ROLE_COMPANY_ADMIN,
                User::ROLE_MANAGER,
                User::ROLE_DIRECTEUR,
                User::ROLE_RH,
            ], true);
    }

    public function rules(): array
    {
        $targetUser = $this->route('user');
        $targetId = $targetUser instanceof User ? $targetUser->id : $targetUser;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($targetId)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:8', 'max:128'],
            'role' => ['sometimes', 'nullable', Rule::in(User::ROLES)],
            'job_title' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'agency_id' => ['nullable', 'integer', 'exists:agencies,id'],
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
