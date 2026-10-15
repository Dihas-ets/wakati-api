<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Plan extends Model
{
    use HasFactory;

    public const DURATION_UNIT_DAY = 'day';
    public const DURATION_UNIT_MONTH = 'month';
    public const DURATION_UNIT_YEAR = 'year';

    public const DURATION_TYPE_RECURRING = 'recurring';
    public const DURATION_TYPE_FIXED = 'fixed';
    public const DURATION_TYPE_TRIAL = 'trial';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'duration',
        'duration_unit',
        'duration_type',
        'is_trial',
        'trial_period_days',
        'features',
        'max_employees',
        'max_branches',
        'is_popular',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration' => 'integer',
            'trial_period_days' => 'integer',
            'is_trial' => 'boolean',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
            'features' => 'array',
            'sort_order' => 'integer',
            'max_employees' => 'integer',
            'max_branches' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Plan $plan) {
            if (empty($plan->slug)) {
                $plan->slug = Str::slug($plan->name) . '-' . Str::random(4);
            }
        });
    }
}
