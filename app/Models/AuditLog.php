<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'target_user_id',
        'action',
        'details',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public static function record(
        ?int $userId,
        ?int $targetUserId,
        string $action,
        ?array $details = null,
        ?string $ip = null
    ): self {
        return static::create([
            'user_id' => $userId,
            'target_user_id' => $targetUserId,
            'action' => $action,
            'details' => $details,
            'ip_address' => $ip,
            'created_at' => now(),
        ]);
    }
}
