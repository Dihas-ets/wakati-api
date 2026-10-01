<?php

namespace App\Http\Requests\Kiosk;

use Illuminate\Foundation\Http\FormRequest;

class KioskHeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user()) {
            return in_array($this->user()->role, ['rh', 'directeur'], true);
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'kiosk_id' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9\-_.]+$/'],
            'ip_address' => ['required', 'ip'],
            'status' => ['sometimes', 'string', 'in:online,offline,warning,syncing'],
            'battery_level' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:50'],
            'meta' => ['sometimes', 'nullable', 'array'],
            'timestamp' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'kiosk_id.required' => 'L\'identifiant de borne est obligatoire.',
            'ip_address.required' => 'L\'adresse IP de la borne est obligatoire.',
            'ip_address.ip' => 'L\'adresse IP transmise doit être une adresse IPv4 ou IPv6 valide.',
            'status.in' => 'Statut de borne invalide.',
            'battery_level.between' => 'Le niveau de batterie doit être compris entre 0 et 100.',
        ];
    }
}
