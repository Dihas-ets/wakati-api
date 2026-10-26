<?php

namespace App\Http\Requests\CompanySetting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanySettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['super_admin', 'company_admin', 'rh', 'directeur'], true);
    }

    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'string', 'max:255'],
            'logo' => ['sometimes', 'nullable', 'string'],
            'primary_logo' => ['sometimes', 'nullable', 'string'],
            'secondary_logo' => ['sometimes', 'nullable', 'string'],
            'primary_color' => ['sometimes', 'nullable', 'string', 'max:30'],
            'secondary_color' => ['sometimes', 'nullable', 'string', 'max:30'],
            'mail_mailer' => ['sometimes', Rule::in(['smtp', 'log'])],
            'mail_host' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mail_port' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'mail_encryption' => ['sometimes', 'nullable', 'string', 'max:20'],
            'mail_username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mail_password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mail_from_address' => ['sometimes', 'nullable', 'email', 'max:255'],
            'mail_from_name' => ['sometimes', 'nullable', 'string', 'max:255'],
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
