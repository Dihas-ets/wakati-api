<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanySetting\UpdateCompanySettingRequest;
use App\Models\CompanySetting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CompanySettingController extends Controller
{
    use AuthorizesRequests;

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', CompanySetting::class);

        $settings = CompanySetting::all()->pluck('value', 'key');

        return response()->json($this->formatSettings($settings));
    }

    public function update(UpdateCompanySettingRequest $request): JsonResponse
    {
        $this->authorize('update', CompanySetting::class);

        $data = $request->validated();
        $user = $request->user();

        // Règle stricte : Seul le Company Admin et le Super Admin ont le droit de modifier le logo principal, logo secondaire et couleurs
        $isCompanyAdmin = in_array($user?->role, [\App\Models\User::ROLE_SUPER_ADMIN, \App\Models\User::ROLE_COMPANY_ADMIN], true);
        if (! $isCompanyAdmin) {
            unset(
                $data['primary_logo'],
                $data['logo'],
                $data['secondary_logo'],
                $data['primary_color'],
                $data['secondary_color']
            );
        }

        // Si l'utilisateur appartient à une entreprise, synchroniser le modèle Company
        if ($user && $user->company_id) {
            $company = \App\Models\Company::find($user->company_id);
            if ($company) {
                $companyUpdates = [];
                if (isset($data['company_name'])) {
                    $companyUpdates['name'] = $data['company_name'];
                }
                if ($isCompanyAdmin) {
                    if (isset($data['primary_logo'])) {
                        $companyUpdates['logo'] = $data['primary_logo'];
                    } elseif (isset($data['logo'])) {
                        $companyUpdates['logo'] = $data['logo'];
                    }
                    if (isset($data['secondary_logo'])) {
                        $companyUpdates['secondary_logo'] = $data['secondary_logo'];
                    }
                    if (isset($data['primary_color'])) {
                        $companyUpdates['primary_color'] = $data['primary_color'];
                    }
                    if (isset($data['secondary_color'])) {
                        $companyUpdates['secondary_color'] = $data['secondary_color'];
                    }
                }
                if (! empty($companyUpdates)) {
                    $company->update($companyUpdates);
                }
            }
        }

        foreach ($data as $key => $value) {
            CompanySetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $settings = CompanySetting::all()->pluck('value', 'key');

        return response()->json($this->formatSettings($settings));
    }

    /**
     * Test immédiat d'envoi d'e-mail depuis la page de paramètres.
     */
    public function testEmail(Request $request): JsonResponse
    {
        $this->authorize('update', CompanySetting::class);

        $request->validate([
            'test_email' => 'required|email',
            'mail_mailer' => 'nullable|string|in:smtp,log',
            'mail_host' => 'nullable|string',
            'mail_port' => 'nullable|integer',
            'mail_encryption' => 'nullable|string',
            'mail_username' => 'nullable|string',
            'mail_password' => 'nullable|string',
            'mail_from_address' => 'nullable|email',
            'mail_from_name' => 'nullable|string',
        ]);

        $recipient = $request->test_email;
        $settings = CompanySetting::all()->pluck('value', 'key');

        $mailer = $request->input('mail_mailer', $settings->get('mail_mailer', 'smtp'));
        $host = $request->input('mail_host', $settings->get('mail_host', config('mail.mailers.smtp.host')));
        $port = $request->input('mail_port', $settings->get('mail_port', config('mail.mailers.smtp.port', 587)));
        $scheme = $request->input('mail_encryption', $settings->get('mail_encryption', config('mail.mailers.smtp.scheme', 'tls')));
        $username = $request->input('mail_username', $settings->get('mail_username', config('mail.mailers.smtp.username')));
        $password = $request->input('mail_password', $settings->get('mail_password', config('mail.mailers.smtp.password')));
        $fromAddress = $request->input('mail_from_address', $settings->get('mail_from_address', config('mail.from.address', 'direction@wakati.local')));
        $fromName = $request->input('mail_from_name', $settings->get('mail_from_name', $settings->get('company_name', 'Wakati')));

        if ($scheme === 'none' || $scheme === 'null' || ! $scheme) {
            $scheme = null;
        }

        // Configuration dynamique du mailer pour ce test
        config([
            'mail.default' => $mailer,
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => (int) $port,
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.mailers.smtp.username' => $username,
            'mail.mailers.smtp.password' => $password,
            'mail.from.address' => $fromAddress,
            'mail.from.name' => $fromName,
        ]);

        Mail::purge('smtp');

        try {
            Mail::raw(
                "Bonjour,\n\nCe message confirme que les paramètres d'envoi d'e-mails (SMTP) de votre application Wakati sont parfaitement configurés et fonctionnels.\n\n• Expéditeur : {$fromName} <{$fromAddress}>\n• Serveur SMTP : {$host}:{$port} (" . ($scheme ? strtoupper($scheme) : 'Aucun chiffrement') . ")\n• Date : " . now()->format('d/m/Y H:i:s') . "\n\nVos collaborateurs recevront désormais automatiquement leurs convocations aux réunions et leurs comptes-rendus personnalisés !",
                function ($message) use ($recipient, $fromName, $fromAddress) {
                    $message->to($recipient)
                        ->from($fromAddress, $fromName)
                        ->subject("✅ Test d'envoi réussi — {$fromName} (Wakati)");
                }
            );

            return response()->json([
                'success' => true,
                'message' => "E-mail de test envoyé avec succès à {$recipient} ! Vérifiez votre boîte de réception.",
            ]);
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            $hint = '';

            if (str_contains(strtolower($errorMsg), 'authentication') || str_contains(strtolower($errorMsg), '535')) {
                $hint = " Indice : Vos identifiants (adresse email ou mot de passe) ont été refusés. Si vous utilisez Gmail, veillez à utiliser un Mot de passe d'Application (16 caractères sans espaces) généré sur votre compte Google.";
            } elseif (str_contains(strtolower($errorMsg), 'connection refused') || str_contains(strtolower($errorMsg), 'timed out')) {
                $hint = " Indice : Impossible de joindre le serveur {$host} sur le port {$port}. Vérifiez l'adresse du serveur et le port (587 avec TLS ou 465 avec SSL).";
            }

            return response()->json([
                'success' => false,
                'message' => "Échec de l'envoi : " . $errorMsg . $hint,
            ], 422);
        }
    }

    protected function formatSettings($settings): array
    {
        $user = auth()->user();
        $company = ($user && $user->company_id) ? \App\Models\Company::find($user->company_id) : null;

        return [
            'company_name' => $company?->name ?? $settings->get('company_name', 'Dihas Group'),
            'primary_logo' => $company?->primary_logo ?? $settings->get('primary_logo', null),
            'secondary_logo' => $company?->secondary_logo ?? $settings->get('secondary_logo', null),
            'primary_color' => $company?->primary_color ?? $settings->get('primary_color', '#795FFC'),
            'secondary_color' => $company?->secondary_color ?? $settings->get('secondary_color', '#0F172A'),
            'allowed_ips' => $settings->get('allowed_ips', []),
            'late_tolerance_minutes' => $settings->get('late_tolerance_minutes', 10),
            'absence_delay_minutes' => $settings->get('absence_delay_minutes', 60),
            'explanation_request_minutes_before' => $settings->get('explanation_request_minutes_before', 5),
            'auto_checkin_mode' => $settings->get('auto_checkin_mode', 'disabled'),
            'timezone' => $settings->get('timezone', config('app.timezone')),
            'mail_mailer' => $settings->get('mail_mailer', config('mail.default', 'log')),
            'mail_host' => $settings->get('mail_host', config('mail.mailers.smtp.host', '')),
            'mail_port' => (int) $settings->get('mail_port', config('mail.mailers.smtp.port', 587)),
            'mail_encryption' => $settings->get('mail_encryption', config('mail.mailers.smtp.scheme', 'tls')),
            'mail_username' => $settings->get('mail_username', config('mail.mailers.smtp.username', '')),
            'mail_password' => $settings->get('mail_password', config('mail.mailers.smtp.password', '')),
            'mail_from_address' => $settings->get('mail_from_address', config('mail.from.address', '')),
            'mail_from_name' => $settings->get('mail_from_name', config('mail.from.name', 'Wakati')),
        ];
    }
}