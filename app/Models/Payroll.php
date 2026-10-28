<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    use HasFactory;

    public const RH_STATUS_DRAFT = 'draft';
    public const RH_STATUS_VALIDATED = 'validated_by_rh';

    public const PAYMENT_STATUS_UNPAID = 'unpaid';
    public const PAYMENT_STATUS_PAID = 'paid';

    protected $fillable = [
        'user_id',
        'month',
        'base_salary',
        'bonus',
        'deductions',
        'net_salary',
        'rh_status',
        'rh_validated_at',
        'rh_validator_id',
        'payment_status',
        'paid_at',
        'payment_reference',
        'paid_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'bonus' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'rh_validated_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rhValidator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rh_validator_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function calculateNet(): float
    {
        $net = ((float) $this->base_salary + (float) $this->bonus) - (float) $this->deductions;
        return max(0, $net);
    }
}
