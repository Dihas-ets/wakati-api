<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class SyncOfflineRequest extends FormRequest
{
    public function authorize(): bool
    {
        // L'autorisation est vérifiée soit via le middleware offline-sync (secret partagé),
        // soit via un token Sanctum d'un utilisateur avec le rôle RH ou Directeur.
        if ($this->user()) {
            return in_array($this->user()->role, ['rh', 'directeur'], true);
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'scans' => ['required', 'array', 'min:1', 'max:500'],
            'scans.*.card_uid' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9\-_:]+$/'],
            'scans.*.scanned_at' => ['required', 'date'],
            'scans.*.client_scan_id' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'scans.required' => 'Le tableau de pointages est requis.',
            'scans.max' => 'Le lot ne peut pas dépasser 500 pointages par requête.',
            'scans.*.card_uid.required' => 'L\'identifiant de carte est obligatoire.',
            'scans.*.card_uid.regex' => 'Format de badge invalide.',
            'scans.*.scanned_at.required' => 'La date et heure de pointage est requise.',
            'scans.*.scanned_at.date' => 'La date de pointage doit être une date ISO8601 valide.',
            'scans.*.client_scan_id.required' => 'Le client_scan_id est obligatoire.',
            'scans.*.client_scan_id.uuid' => 'Le client_scan_id doit être un UUID valide.',
        ];
    }
}
