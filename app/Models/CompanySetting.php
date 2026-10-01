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