<?php

namespace App\Http\Requests\Employee;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
            'role' => ['sometimes', 'nullable', Rule::in(User::ROLES)],
            'job_title' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'agency_id' => ['nullable', 'integer', 'exists:agencies,id'],
            'primary_position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'secondary_position_ids' => ['nullable', 'array'],
            'secondary_position_ids.*' => ['integer', 'exists:positions,id'],
            'position_ids' => ['nullable', 'array'],
            'position_ids.*' => ['integer', 'exists:positions,id'],
        ];
    }
}
