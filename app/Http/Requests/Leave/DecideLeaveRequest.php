<?php

namespace App\Http\Requests\Leave;

use App\Models\Leave;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['rh', 'directeur'], true);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([Leave::STATUS_APPROVED, Leave::STATUS_REJECTED])],
        ];
    }
}
