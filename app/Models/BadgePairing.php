<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BadgePairing extends Model
{
    protected $fillable = ['user_id', 'initiated_by', 'expires_at', 'used_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function activePairing(): ?self
    {
        return static::whereNull('used_at')
            ->where('expires_at', '>=', now())
            ->latest()
            ->first();
    }
}