<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanySetting\UpdateCompanySettingRequest;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CompanySettingController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CompanySetting::class);

        $user = $request->user();
        $companyId = $user->company_id;

        $dedicated = null;
        if ($companyId) {
            $dedicated = CompanySetting::withoutGlobalScopes()->where('company_id', $companyId)->first();
        }

        $kvSettings = CompanySetting::all()->pluck('value', 'key');

        return response()->json($this->formatSettings($kvSettings, $dedicated, $user));
    }

    public function update(UpdateCompanySettingRequest $request): JsonResponse
    {
        $this->authorize('update', CompanySetting::class);

        $user = $request->user();
        $companyId = $user->company_id;
        $data = $request->validated();

        // Règle stricte : Seul le Company Admin et le Super Admin ont le droit de modifier le logo principal, logo secondaire et couleurs
        $isCompanyAdmin = in_array($user?->role, [User::ROLE_SUPER_ADMIN, User::ROLE_COMPANY_ADMIN], true);
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
            $company = Company::find($user->company_id);
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

        // 1. Mise à jour de la ligne dédiée de l'entreprise si des paramètres SMTP sont fournis
        $smtpFields = [
            'mail_host' => 'smtp_host',
            'smtp_host' => 'smtp_host',
            'mail_port' => 'smtp_port',
            'smtp_port' => 'smtp_port',
            'mail_username' => 'smtp_username',
            'smtp_username' => 'smtp_username',
            'mail_password' => 'smtp_password',
            'smtp_password' => 'smtp_password',
            'mail_encryption' => 'smtp_encryption',
            'smtp_encryption' => 'smtp_encryption',
            'mail_from_address' => 'from_address',
            'from_address' => 'from_address',
            'mail_from_name' => 'from_name',
            'from_name' => 'from_name',
            'mail_reply_to_address' => 'reply_to_address',
            'reply_to_address' => 'reply_to_address',
            'mail_reply_to_name' => 'reply_to_name',
            'reply_to_name' => 'reply_to_name',
        ];

        $hasSmtp = false;
        $dedicatedAttrs = [];

        foreach ($smtpFields as $reqKey => $col) {
            if (array_key_exists($reqKey, $data)) {
                $dedicatedAttrs[$col] = $data[$reqKey];
                $hasSmtp = true;
            }
        }

        if ($companyId && $hasSmtp) {
            $dedicated = CompanySetting::withoutGlobalScopes()->where('company_id', $companyId)->first();
            if (! $dedicated) {
                $dedicated = new CompanySetting();
                $dedicated->company_id = $companyId;
            }
            foreach ($dedicatedAttrs as $col => $val) {
                if ($val !== null || ! empty($dedicated->{$col})) {
                    $dedicated->{$col} = $val;
                }
            }
            $dedicated->save();
        }

        // 2. Sauvegarde des clés de configuration (key-value)
        foreach ($data as $key => $value) {
            CompanySetting::set($key, $value);
        }

        $dedicated = $companyId ? CompanySetting::withoutGlobalScopes()->where('company_id', $companyId)->first() : null;
        $kvSettings = CompanySetting::all()->pluck('value', 'key');

        return response()->json($this->formatSettings($kvSettings, $dedicated, $user));
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
            'mail_reply_to_address' => 'nullable|email',
            'mail_reply_to_name' => 'nullable|string',
        ]);

        $recipient = $request->test_email;
        $user = $request->user();
        $companyId = $user->company_id;
        $dedicated = $companyId ? CompanySetting::withoutGlobalScopes()->where('company_id', $companyId)->first() : null;
        $company = $companyId ? Company::find($companyId) : null;
        $settings = CompanySetting::all()->pluck('value', 'key');

        $mailer = $request->input('mail_mailer', $settings->get('mail_mailer', 'smtp'));
        $host = $request->input('mail_host', $dedicated?->smtp_host ?: $settings->get('mail_host', config('mail.mailers.smtp.host')));
        $port = (int) $request->input('mail_port', $dedicated?->smtp_port ?: $settings->get('mail_port', config('mail.mailers.smtp.port', 587)));
        $encryption = $request->input('mail_encryption', $dedicated?->smtp_encryption ?: $settings->get('mail_encryption', 'tls'));
        $username = $request->input('mail_username', $dedicated?->smtp_username ?: $settings->get('mail_username', config('mail.mailers.smtp.username')));
        $password = $request->input('mail_password', $dedicated?->smtp_password ?: $settings->get('mail_password', config('mail.mailers.smtp.password')));
        $fromAddress = $request->input('mail_from_address', $dedicated?->from_address ?: $settings->get('mail_from_address', $company?->email ?: config('mail.from.address', 'direction@wakati.local')));
        $fromName = $request->input('mail_from_name', $dedicated?->from_name ?: $settings->get('mail_from_name', $company?->name ?: 'Wakati'));
        $replyToAddress = $request->input('mail_reply_to_address', $dedicated?->reply_to_address ?: $settings->get('mail_reply_to_address', $fromAddress));
        $replyToName = $request->input('mail_reply_to_name', $dedicated?->reply_to_name ?: $settings->get('mail_reply_to_name', $fromName));

        $resolved = CompanySetting::resolveSmtpSchemeAndTls($encryption, $port);

        // Configuration dynamique du mailer pour ce test
        config([
            'mail.default' => $mailer,
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.scheme' => $resolved['scheme'],
            'mail.mailers.smtp.auto_tls' => $resolved['auto_tls'],
            'mail.mailers.smtp.username' => $username,
            'mail.mailers.smtp.password' => $password,
            'mail.from.address' => $fromAddress,
            'mail.from.name' => $fromName,
            'mail.reply_to.address' => $replyToAddress,
            'mail.reply_to.name' => $replyToName,
        ]);

        Mail::purge('smtp');

        try {
            $encLabel = ($encryption && ! in_array(strtolower($encryption), ['none', 'null', 'false'], true))
                ? strtoupper($encryption)
                : 'Aucun chiffrement';

            $replyInfo = $replyToAddress ? "• Répondre à (Reply-To) : {$replyToName} <{$replyToAddress}>\n" : "";

            Mail::raw(
                "Bonjour,\n\nCe message confirme que les paramètres d'envoi d'e-mails (SMTP) de votre entreprise {$fromName} sur Wakati sont parfaitement configurés et fonctionnels.\n\n• Expéditeur : {$fromName} <{$fromAddress}>\n{$replyInfo}• Serveur SMTP : {$host}:{$port} ({$encLabel})\n• Date : " . now()->format('d/m/Y H:i:s') . "\n\nVos collaborateurs recevront désormais automatiquement leurs invitations d'embauche, convocations aux réunions et fiches de paie !",
                function ($message) use ($recipient, $fromName, $fromAddress, $replyToAddress, $replyToName) {
                    $message->to($recipient)
                        ->from($fromAddress, $fromName);
                    if ($replyToAddress) {
                        $message->replyTo($replyToAddress, $replyToName);
                    }
                    $message->subject("✅ Test d'envoi réussi — {$fromName} (Wakati)");
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

    protected function formatSettings($settings, ?CompanySetting $dedicated = null, ?User $user = null): array
    {
        $company = $user?->company;

        return [
            'company_name' => $company?->name ?? $settings->get('company_name', 'Dihas Group'),
            'primary_logo' => $company?->logo ?? $settings->get('primary_logo', null),
            'secondary_logo' => $company?->secondary_logo ?? $settings->get('secondary_logo', null),
            'primary_color' => $company?->primary_color ?? $settings->get('primary_color', '#795FFC'),
            'secondary_color' => $company?->secondary_color ?? $settings->get('secondary_color', '#0F172A'),
            'allowed_ips' => $settings->get('allowed_ips', []),
            'late_tolerance_minutes' => (int) $settings->get('late_tolerance_minutes', 10),
            'absence_delay_minutes' => (int) $settings->get('absence_delay_minutes', 60),
            'explanation_request_minutes_before' => (int) $settings->get('explanation_request_minutes_before', 5),
            'auto_checkin_mode' => $settings->get('auto_checkin_mode', 'disabled'),
            'timezone' => $settings->get('timezone', config('app.timezone')),
            'mail_mailer' => $settings->get('mail_mailer', config('mail.default', 'log')),
            'mail_host' => $dedicated?->smtp_host ?: $settings->get('mail_host', config('mail.mailers.smtp.host', '')),
            'mail_port' => (int) ($dedicated?->smtp_port ?: $settings->get('mail_port', config('mail.mailers.smtp.port', 587))),
            'mail_encryption' => $dedicated?->smtp_encryption ?: $settings->get('mail_encryption', 'tls'),
            'mail_username' => $dedicated?->smtp_username ?: $settings->get('mail_username', config('mail.mailers.smtp.username', '')),
            'mail_password' => $dedicated?->smtp_password ?: $settings->get('mail_password', config('mail.mailers.smtp.password', '')),
            'mail_from_address' => $dedicated?->from_address ?: $settings->get('mail_from_address', $company?->email ?: config('mail.from.address', '')),
            'mail_from_name' => $dedicated?->from_name ?: $settings->get('mail_from_name', $company?->name ?: config('mail.from.name', 'Wakati')),
            'mail_reply_to_address' => $dedicated?->reply_to_address ?: $settings->get('mail_reply_to_address', $dedicated?->from_address ?: $settings->get('mail_from_address', config('mail.reply_to.address', ''))),
            'mail_reply_to_name' => $dedicated?->reply_to_name ?: $settings->get('mail_reply_to_name', $dedicated?->from_name ?: $settings->get('mail_from_name', config('mail.reply_to.name', 'Wakati'))),
        ];
    }
}