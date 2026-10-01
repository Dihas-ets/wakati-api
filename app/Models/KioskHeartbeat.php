<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KioskHeartbeat extends Model
{
    protected $fillable = [
        'kiosk_id',
        'ip_address',
        'status',
        'battery_level',
        'app_version',
        'meta',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'meta' => 'array',
            'battery_level' => 'integer',
        ];
    }
}
