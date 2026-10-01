<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $isManager = in_array($this->user()?->role, ['rh', 'directeur'], true);

        $rules = [
            'leave_type' => ['required', 'string', 'in:deplacement,maladie,annuel,maternite'],
            'location' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'work_mode' => ['required', 'string', 'in:teletravail,presentiel,hybride,deplacement'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($isManager) {
            $rules['user_id'] = ['required', 'integer', 'exists:users,id'];
        }

        return $rules;
    }
}
