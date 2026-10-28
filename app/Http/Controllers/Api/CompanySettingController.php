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
            'mail_reply_to_address' => 'nullable|email',
            'mail_reply_to_name' => 'nullable|string',
        ]);

        $recipient = $request->test_email;
        $settings = CompanySetting::all()->pluck('value', 'key');

        $mailer = $request->input('mail_mailer', $settings->get('mail_mailer', 'smtp'));
        $host = $request->input('mail_host', $settings->get('mail_host', config('mail.mailers.smtp.host')));
        $port = (int) $request->input('mail_port', $settings->get('mail_port', config('mail.mailers.smtp.port', 587)));
        $encryption = $request->input('mail_encryption', $settings->get('mail_encryption', 'tls'));
        $username = $request->input('mail_username', $settings->get('mail_username', config('mail.mailers.smtp.username')));
        $password = $request->input('mail_password', $settings->get('mail_password', config('mail.mailers.smtp.password')));
        $fromAddress = $request->input('mail_from_address', $settings->get('mail_from_address', config('mail.from.address', 'direction@wakati.local')));
        $fromName = $request->input('mail_from_name', $settings->get('mail_from_name', $settings->get('company_name', 'Wakati')));
        $replyToAddress = $request->input('mail_reply_to_address', $settings->get('mail_reply_to_address', $fromAddress));
        $replyToName = $request->input('mail_reply_to_name', $settings->get('mail_reply_to_name', $fromName));

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
            $encLabel = ($encryption && !in_array(strtolower($encryption), ['none', 'null', 'false'], true))
                ? strtoupper($encryption)
                : 'Aucun chiffrement';

            $replyInfo = $replyToAddress ? "• Répondre à (Reply-To) : {$replyToName} <{$replyToAddress}>\n" : "";

            Mail::raw(
                "Bonjour,\n\nCe message confirme que les paramètres d'envoi d'e-mails (SMTP) de votre application Wakati sont parfaitement configurés et fonctionnels.\n\n• Expéditeur : {$fromName} <{$fromAddress}>\n{$replyInfo}• Serveur SMTP : {$host}:{$port} ({$encLabel})\n• Date : " . now()->format('d/m/Y H:i:s') . "\n\nVos collaborateurs recevront désormais automatiquement leurs convocations aux réunions et leurs comptes-rendus personnalisés !",
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

    protected function formatSettings($settings): array
    {
        return [
            'company_name' => $settings->get('company_name', 'Dihas Group'),
            'allowed_ips' => $settings->get('allowed_ips', []),
            'late_tolerance_minutes' => $settings->get('late_tolerance_minutes', 10),
            'absence_delay_minutes' => $settings->get('absence_delay_minutes', 60),
            'explanation_request_minutes_before' => $settings->get('explanation_request_minutes_before', 5),
            'auto_checkin_mode' => $settings->get('auto_checkin_mode', 'disabled'),
            'timezone' => $settings->get('timezone', config('app.timezone')),
            'mail_mailer' => $settings->get('mail_mailer', config('mail.default', 'log')),
            'mail_host' => $settings->get('mail_host', config('mail.mailers.smtp.host', '')),
            'mail_port' => (int) $settings->get('mail_port', config('mail.mailers.smtp.port', 587)),
            'mail_encryption' => $settings->get('mail_encryption', 'tls'),
            'mail_username' => $settings->get('mail_username', config('mail.mailers.smtp.username', '')),
            'mail_password' => $settings->get('mail_password', config('mail.mailers.smtp.password', '')),
            'mail_from_address' => $settings->get('mail_from_address', config('mail.from.address', '')),
            'mail_from_name' => $settings->get('mail_from_name', config('mail.from.name', 'Wakati')),
            'mail_reply_to_address' => $settings->get('mail_reply_to_address', $settings->get('mail_from_address', config('mail.reply_to.address', ''))),
            'mail_reply_to_name' => $settings->get('mail_reply_to_name', $settings->get('mail_from_name', config('mail.reply_to.name', 'Wakati'))),
        ];
    }
}