<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class CompanySetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'from_address',
        'from_name',
        'reply_to_address',
        'reply_to_name',
        'key',
        'value',
        'description',
    ];

    protected $casts = [
        'value' => 'json',
        'smtp_password' => 'encrypted',
        'smtp_port' => 'integer',
    ];

    /**
     * Résout le schéma Symfony Mailer ('smtp', 'smtps') et l'option auto_tls
     * à partir de la valeur de chiffrement utilisateur ('tls', 'ssl', 'null', etc.).
     *
     * @return array{scheme: string, auto_tls: bool}
     */
    public static function resolveSmtpSchemeAndTls(?string $encryption, int $port = 587): array
    {
        $enc = strtolower(trim((string) $encryption));

        if (in_array($enc, ['ssl', 'smtps'], true) || ($port === 465 && ! in_array($enc, ['none', 'null', 'false'], true))) {
            return [
                'scheme' => 'smtps',
                'auto_tls' => true,
            ];
        }

        if (in_array($enc, ['none', 'null', 'false'], true)) {
            return [
                'scheme' => 'smtp',
                'auto_tls' => false,
            ];
        }

        // 'tls', 'starttls', 'smtp' ou standard port 587 / 25
        return [
            'scheme' => 'smtp',
            'auto_tls' => true,
        ];
    }

    /**
     * Configuration dynamique du Mailer Laravel à la volée pour une entreprise donnée.
     */
    public static function configureMailerForCompany(?int $companyId): void
    {
        if (! $companyId) {
            return;
        }

        $setting = static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNotNull('smtp_host')
            ->first();

        if (! $setting || empty($setting->smtp_host)) {
            return;
        }

        $resolved = static::resolveSmtpSchemeAndTls($setting->smtp_encryption, (int) ($setting->smtp_port ?: 587));

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => $setting->smtp_host,
            'mail.mailers.smtp.port' => (int) ($setting->smtp_port ?: 587),
            'mail.mailers.smtp.scheme' => $resolved['scheme'],
            'mail.mailers.smtp.auto_tls' => $resolved['auto_tls'],
            'mail.mailers.smtp.username' => $setting->smtp_username,
            'mail.mailers.smtp.password' => $setting->smtp_password,
        ]);

        if (! empty($setting->from_address)) {
            config([
                'mail.from.address' => $setting->from_address,
                'mail.from.name' => $setting->from_name ?: config('mail.from.name'),
            ]);
        }

        if (! empty($setting->reply_to_address)) {
            config([
                'mail.reply_to.address' => $setting->reply_to_address,
                'mail.reply_to.name' => $setting->reply_to_name ?: $setting->from_name,
            ]);
        }

        Mail::purge('smtp');
    }

    public static function get(string $key, $default = null)
    {
        $companyId = auth()->check() ? auth()->user()->company_id : null;
        $cacheKey = $companyId ? "company_setting_{$companyId}_{$key}" : "company_setting_global_{$key}";

        return Cache::remember($cacheKey, 3600, function () use ($key, $default, $companyId) {
            // Si la clé correspond à un champ SMTP d'un row dédié à la company
            if ($companyId) {
                $dedicated = static::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->whereNotNull('smtp_host')
                    ->first();

                if ($dedicated) {
                    $columnMap = [
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

                    if (isset($columnMap[$key]) && ! empty($dedicated->{$columnMap[$key]})) {
                        return $dedicated->{$columnMap[$key]};
                    }
                }
            }

            // Recherche par champ 'key'
            $query = static::where('key', $key);
            $setting = $query->first();

            if (! $setting && $companyId) {
                // Fallback sans scope
                $setting = static::withoutGlobalScopes()
                    ->where(function ($q) use ($companyId) {
                        $q->where('company_id', $companyId)->orWhereNull('company_id');
                    })
                    ->where('key', $key)
                    ->orderByRaw('company_id IS NULL ASC')
                    ->first();
            }

            return $setting ? $setting->value : $default;
        });
    }

    public static function set(string $key, $value): self
    {
        $companyId = auth()->check() ? auth()->user()->company_id : null;
        $setting = static::updateOrCreate(
            ['key' => $key, 'company_id' => $companyId],
            ['value' => $value]
        );

        if ($companyId) {
            Cache::forget("company_setting_{$companyId}_{$key}");
        }
        Cache::forget("company_setting_global_{$key}");

        return $setting;
    }

    protected static function booted(): void
    {
        static::saved(function (CompanySetting $setting) {
            if ($setting->key) {
                if ($setting->company_id) {
                    Cache::forget("company_setting_{$setting->company_id}_{$setting->key}");
                }
                Cache::forget("company_setting_global_{$setting->key}");
            }
        });

        static::deleted(function (CompanySetting $setting) {
            if ($setting->key) {
                if ($setting->company_id) {
                    Cache::forget("company_setting_{$setting->company_id}_{$setting->key}");
                }
                Cache::forget("company_setting_global_{$setting->key}");
            }
        });
    }
}