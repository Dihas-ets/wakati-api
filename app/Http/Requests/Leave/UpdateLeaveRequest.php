<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['rh', 'directeur'], true);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'leave_type' => ['sometimes', 'string', 'in:deplacement,maladie,annuel,maternite'],
            'location' => ['nullable', 'string', 'max:255'],
            'date' => ['sometimes', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'work_mode' => ['sometimes', 'string', 'in:teletravail,presentiel,hybride,deplacement'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
