<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeInvitation extends Model
{
    use HasFactory, BelongsToTenant;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'company_id',
        'agency_id',
        'department_id',
        'position_id',
        'email',
        'first_name',
        'last_name',
        'token',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Scope local pour filtrer les invitations en attente et non expirées (expires_at > now()).
     */
    public function scopePendingAndValid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('expires_at', '>', now());
    }

    /**
     * Vérifie si l'invitation est encore valide.
     */
    public function isValid(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->expires_at && $this->expires_at->isFuture();
    }
}
