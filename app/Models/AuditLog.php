<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'company_id',
        'user_id',
        'target_user_id',
        'action',
        'details',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
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
        ?string $ip = null,
        ?int $companyId = null
    ): self {
        $attrs = [
            'user_id' => $userId,
            'target_user_id' => $targetUserId,
            'action' => $action,
            'details' => $details,
            'ip_address' => $ip,
        ];

        if ($companyId !== null) {
            $attrs['company_id'] = $companyId;
        } elseif (auth()->check() && auth()->user()->company_id) {
            $attrs['company_id'] = auth()->user()->company_id;
        }

        return static::create($attrs);
    }
}
