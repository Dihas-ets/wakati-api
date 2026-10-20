<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELED = 'canceled';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_EXPIRED,
        self::STATUS_CANCELED,
    ];

    protected $fillable = [
        'company_id',
        'plan_id',
        'start_date',
        'end_date',
        'status',
        'is_trial',
        'notes',
        'price_paid',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'is_trial' => 'boolean',
            'price_paid' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
