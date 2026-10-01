<?php

namespace App\Http\Requests\CompanySetting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanySettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['rh', 'directeur'], true);
    }

    public function rules(): array
    {
        return [
            'allowed_ips' => ['sometimes', 'array'],
            'allowed_ips.*' => ['string', 'max:45'],
            'late_tolerance_minutes' => ['sometimes', 'integer', 'min:0', 'max:120'],
            'absence_delay_minutes' => ['sometimes', 'integer', 'min:1', 'max:480'],
            'explanation_request_minutes_before' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'auto_checkin_mode' => ['sometimes', Rule::in(['disabled', 'confirm_required', 'full_auto'])],
            'timezone' => ['sometimes', 'timezone'],
        ];
    }
}
