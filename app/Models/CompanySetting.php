<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CompanySetting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'json',
    ];

    public static function get(string $key, $default = null)
    {
        return Cache::remember("company_setting_{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Résout le schéma Symfony Mailer ('smtp', 'smtps') et l'option auto_tls
     * à partir de la valeur de chiffrement utilisateur ('tls', 'ssl', 'null', etc.).
     *
     * @return array{scheme: string, auto_tls: bool}
     */
    public static function resolveSmtpSchemeAndTls(?string $encryption, int $port = 587): array
    {
        $enc = strtolower(trim((string) $encryption));

        if (in_array($enc, ['ssl', 'smtps'], true) || ($port === 465 && !in_array($enc, ['none', 'null', 'false'], true))) {
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

    protected static function booted(): void
    {
        static::saved(function (CompanySetting $setting) {
            Cache::forget("company_setting_{$setting->key}");
        });

        static::deleted(function (CompanySetting $setting) {
            Cache::forget("company_setting_{$setting->key}");
        });
    }
}