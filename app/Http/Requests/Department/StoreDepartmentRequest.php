<?php

namespace App\Http\Requests\Department;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return $u?->isAdmin()
            || $u?->hasPermission('rh')
            || in_array($u?->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN, User::ROLE_DIRECTEUR, User::ROLE_RH], true);
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->where(function ($query) use ($companyId) {
                    return $companyId ? $query->where('company_id', $companyId) : $query;
                }),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
