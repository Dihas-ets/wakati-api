<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Plan extends Model
{
    use HasFactory;

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
        'max_employees',
        'features',
        'is_popular',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration' => 'integer',
            'is_trial' => 'boolean',
            'trial_period_days' => 'integer',
            'max_employees' => 'integer',
            'features' => 'array',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Plan $plan) {
            if (empty($plan->slug) && ! empty($plan->name)) {
                $plan->slug = Str::slug($plan->name);
            }
        });
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
