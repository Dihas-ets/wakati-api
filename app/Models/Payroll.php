<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    use HasFactory, BelongsToTenant;

    public const RH_STATUS_DRAFT = 'draft';
    public const RH_STATUS_VALIDATED = 'validated_by_rh';

    public const PAYMENT_STATUS_UNPAID = 'unpaid';
    public const PAYMENT_STATUS_PAID = 'paid';

    protected $fillable = [
        'company_id',
        'agency_id',
        'user_id',
        'month_year',
        'month',
        'base_salary',
        'bonus',
        'deductions',
        'net_salary',
        'status_rh',
        'rh_status',
        'rh_validated_at',
        'rh_validator_id',
        'payment_status',
        'paid_at',
        'payment_ref',
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

    public function getMonthYearAttribute(): ?string
    {
        return $this->attributes['month_year'] ?? $this->attributes['month'] ?? null;
    }

    public function setMonthYearAttribute($value): void
    {
        $this->attributes['month_year'] = $value;
        $this->attributes['month'] = $value;
    }

    public function getMonthAttribute(): ?string
    {
        return $this->attributes['month'] ?? $this->attributes['month_year'] ?? null;
    }

    public function setMonthAttribute($value): void
    {
        $this->attributes['month'] = $value;
        $this->attributes['month_year'] = $value;
    }

    public function getStatusRhAttribute(): ?string
    {
        return $this->attributes['status_rh'] ?? $this->attributes['rh_status'] ?? self::RH_STATUS_DRAFT;
    }

    public function setStatusRhAttribute($value): void
    {
        $this->attributes['status_rh'] = $value;
        $this->attributes['rh_status'] = $value;
    }

    public function getRhStatusAttribute(): ?string
    {
        return $this->attributes['rh_status'] ?? $this->attributes['status_rh'] ?? self::RH_STATUS_DRAFT;
    }

    public function setRhStatusAttribute($value): void
    {
        $this->attributes['rh_status'] = $value;
        $this->attributes['status_rh'] = $value;
    }

    public function getPaymentRefAttribute(): ?string
    {
        return $this->attributes['payment_ref'] ?? $this->attributes['payment_reference'] ?? null;
    }

    public function setPaymentRefAttribute($value): void
    {
        $this->attributes['payment_ref'] = $value;
        $this->attributes['payment_reference'] = $value;
    }

    public function getPaymentReferenceAttribute(): ?string
    {
        return $this->attributes['payment_reference'] ?? $this->attributes['payment_ref'] ?? null;
    }

    public function setPaymentReferenceAttribute($value): void
    {
        $this->attributes['payment_reference'] = $value;
        $this->attributes['payment_ref'] = $value;
    }

    public function calculateNet(): float
    {
        $net = ((float) $this->base_salary + (float) $this->bonus) - (float) $this->deductions;
        return max(0, $net);
    }
}
