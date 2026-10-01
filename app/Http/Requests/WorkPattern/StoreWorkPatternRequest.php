<?php

namespace App\Http\Requests\WorkPattern;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkPatternRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['rh', 'directeur'], true);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'days' => ['required_without:day_of_week', 'array', 'min:1'],
            'days.*.day_of_week' => ['required_with:days', 'integer', 'between:0,6'],
            'days.*.start_time' => ['required_with:days', 'date_format:H:i'],
            'days.*.end_time' => ['required_with:days', 'date_format:H:i'],
            'day_of_week' => ['required_without:days', 'integer', 'between:0,6'],
            'start_time' => ['required_without:days', 'date_format:H:i'],
            'end_time' => ['required_without:days', 'date_format:H:i'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
