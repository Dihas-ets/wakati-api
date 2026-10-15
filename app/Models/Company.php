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
        'domain_slug',
        'logo_url',
        'registration_number',
        'email',
        'phone',
        'address',
        'website',
        'access_type',
        'status',
        'has_used_trial',
        'trial_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'has_used_trial' => 'boolean',
            'trial_ends_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function activeBranches(): HasMany
    {
        return $this->hasMany(Branch::class)->where('is_active', true);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)->orderBy('id', 'desc');
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', ['active', 'trial'])
            ->where('end_date', '>=', now())
            ->latestOfMany();
    }

    public function getDomainSlugAttribute(): ?string
    {
        return $this->attributes['domain_slug'] ?? $this->attributes['slug'] ?? null;
    }

    public function activePlan(): ?Plan
    {
        return $this->currentSubscription?->plan;
    }

    public function maxBranchesAllowed(): ?int
    {
        if ($this->access_type === self::ACCESS_TYPE_FREE_GRANT) {
            return null; // Illimité si accordé par Super Admin
        }

        return $this->activePlan()?->max_branches;
    }

    public function maxEmployeesAllowed(): ?int
    {
        if ($this->access_type === self::ACCESS_TYPE_FREE_GRANT) {
            return null; // Illimité si accordé par Super Admin
        }

        return $this->activePlan()?->max_employees;
    }

    public function canCreateBranch(): bool
    {
        $max = $this->maxBranchesAllowed();
        if ($max === null) {
            return true;
        }

        return $this->branches()->count() < $max;
    }

    public function canCreateEmployee(): bool
    {
        $max = $this->maxEmployeesAllowed();
        if ($max === null) {
            return true;
        }

        return $this->users()->whereIn('role', [
            User::ROLE_EMPLOYEE,
            User::ROLE_EMPLOYE,
        ])->count() < $max;
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('payment_date', 'desc');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(CompanyHistory::class)->orderBy('id', 'desc');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * Vérifie si l'entreprise a un accès valide (accès gratuit illimité accordé par super_admin
     * ou abonnement/essai actif non expiré).
     */
    public function hasValidAccess(): bool
    {
        if ($this->status === self::STATUS_SUSPENDED) {
            return false;
        }

        if ($this->access_type === self::ACCESS_TYPE_FREE_GRANT) {
            return true;
        }

        // Si conditionné à un abonnement, vérifier l'abonnement en cours
        $sub = $this->currentSubscription;
        return $sub !== null && $sub->end_date >= now();
    }

    /**
     * Enregistrer une action dans l'historique de l'entreprise.
     */
    public function recordHistory(string $action, string $description, ?int $userId = null, ?array $metadata = null): CompanyHistory
    {
        return $this->histories()->create([
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
