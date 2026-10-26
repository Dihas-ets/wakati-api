<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    use HasFactory;

    public const ACCESS_TYPE_SUBSCRIPTION = 'subscription';
    public const ACCESS_TYPE_FREE_GRANT = 'free_grant';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_TRIAL = 'trial';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'secondary_logo',
        'primary_color',
        'secondary_color',
        'phone',
        'email',
        'address',
        'website',
        'rccm_ifu',
        'access_type',
        'status',
        'has_used_trial',
    ];

    protected $appends = [
        'primary_logo',
    ];

    public function getPrimaryLogoAttribute(): ?string
    {
        return $this->logo;
    }

    public function setPrimaryLogoAttribute(?string $value): void
    {
        $this->attributes['logo'] = $value;
    }

    protected function casts(): array
    {
        return [
            'has_used_trial' => 'boolean',
        ];
    }

    public function agencies(): HasMany
    {
        return $this->hasMany(Agency::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function getActivePlan(): ?Plan
    {
        $sub = $this->currentSubscription()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->with('plan')
            ->first();

        return $sub?->plan;
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(CompanyHistory::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function kanbanTasks(): HasMany
    {
        return $this->hasMany(KanbanTask::class);
    }

    /**
     * Vérifie si l'entreprise dispose d'un accès actif :
     * Soit via gratuité accordée par le super-admin (free_grant),
     * Soit via un abonnement actif en cours de validité.
     */
    public function hasValidAccess(): bool
    {
        if ($this->access_type === self::ACCESS_TYPE_FREE_GRANT) {
            return $this->status === self::STATUS_ACTIVE;
        }

        return $this->subscriptions()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('end_date', '>=', now())
            ->exists();
    }
}
