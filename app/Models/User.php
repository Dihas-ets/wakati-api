<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Rôles disponibles dans l'application SaaS Multi-Agences.
     */
    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_COMPANY_ADMIN = 'company_admin';
    public const ROLE_BRANCH_MANAGER = 'branch_manager';
    public const ROLE_HR_MANAGER = 'hr_manager';
    public const ROLE_EMPLOYEE = 'employee';

    // Rôles historiques préservés pour rétrocompatibilité
    public const ROLE_DIRECTEUR = 'directeur'; // Alias branch_manager
    public const ROLE_RH = 'rh';               // Alias hr_manager
    public const ROLE_COMPTABLE = 'comptable';
    public const ROLE_EMPLOYE = 'employe';     // Alias employee

    public const ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_COMPANY_ADMIN,
        self::ROLE_BRANCH_MANAGER,
        self::ROLE_HR_MANAGER,
        self::ROLE_EMPLOYEE,
        self::ROLE_DIRECTEUR,
        self::ROLE_RH,
        self::ROLE_COMPTABLE,
        self::ROLE_EMPLOYE,
    ];

    /**
     * Statuts de présence disponibles.
     */
    public const STATUS_AU_TRAVAIL = 'au_travail';
    public const STATUS_EN_PAUSE = 'en_pause';
    public const STATUS_PAS_POINTE = 'pas_pointe';

    public const STATUSES = [
        self::STATUS_AU_TRAVAIL,
        self::STATUS_EN_PAUSE,
        self::STATUS_PAS_POINTE,
    ];

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'first_name',
        'last_name',
        'phone',
        'email',
        'password',
        'role',
        'job_title',
        'department_id',
        'status',
        'last_login_at',
        'card_uid',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isCompanyAdmin(): bool
    {
        return $this->role === self::ROLE_COMPANY_ADMIN;
    }

    public function isBranchManager(): bool
    {
        return in_array($this->role, [self::ROLE_BRANCH_MANAGER, self::ROLE_DIRECTEUR], true);
    }

    public function isHrManager(): bool
    {
        return in_array($this->role, [self::ROLE_HR_MANAGER, self::ROLE_RH], true);
    }

    public function isEmployee(): bool
    {
        return in_array($this->role, [self::ROLE_EMPLOYEE, self::ROLE_EMPLOYE], true);
    }

    public function canAccessBranch(int|Branch|null $branch): bool
    {
        if ($this->isSuperAdmin() || $this->isCompanyAdmin()) {
            return true;
        }

        $branchId = $branch instanceof Branch ? $branch->id : $branch;

        return $this->branch_id !== null && (int) $this->branch_id === (int) $branchId;
    }

    public function hasRole(string $role): bool
    {
        if ($this->role === self::ROLE_SUPER_ADMIN) {
            return true;
        }

        // Aliases d'équivalence
        $roleMap = [
            self::ROLE_BRANCH_MANAGER => [self::ROLE_BRANCH_MANAGER, self::ROLE_DIRECTEUR],
            self::ROLE_DIRECTEUR => [self::ROLE_BRANCH_MANAGER, self::ROLE_DIRECTEUR],
            self::ROLE_HR_MANAGER => [self::ROLE_HR_MANAGER, self::ROLE_RH],
            self::ROLE_RH => [self::ROLE_HR_MANAGER, self::ROLE_RH],
            self::ROLE_EMPLOYEE => [self::ROLE_EMPLOYEE, self::ROLE_EMPLOYE],
            self::ROLE_EMPLOYE => [self::ROLE_EMPLOYEE, self::ROLE_EMPLOYE],
        ];

        if ($this->role === self::ROLE_COMPANY_ADMIN) {
            return true; // Le company_admin a autorité sur son entreprise et toutes ses agences
        }

        if (isset($roleMap[$role])) {
            return in_array($this->role, $roleMap[$role], true);
        }

        return $this->role === $role;
    }

    /**
     * Identifiant unique partagé entre le QR imprimé et le badge physique (RFID).
     */
    public static function newCardUid(): string
    {
        do {
            $uid = 'WAK-'.strtoupper(bin2hex(random_bytes(6)));
        } while (static::where('card_uid', $uid)->exists());

        return $uid;
    }

    /**
     * Forme canonique d'un identifiant de badge : le même badge peut être lu
     * "04:a1:b2:c3" (Web NFC) ou "04A1B2C3" (lecteur USB / Raspberry Pi).
     * Les séparateurs des numéros de série hexadécimaux sont retirés et tout
     * est mis en majuscules ; les codes "WAK-..." restent inchangés.
     *
     * ⚠ Doit rester identique à normalizeCardUid() côté front (lib/card-uid.ts).
     */
    public static function normalizeCardUid(string $raw): string
    {
        $value = strtoupper(trim($raw));

        if (preg_match('/^[0-9A-F]{2}([:\-\s][0-9A-F]{2}){3,9}$/', $value)) {
            return preg_replace('/[:\-\s]/', '', $value);
        }

        return $value;
    }

    public function ensureCardUid(): string
    {
        if (! $this->card_uid) {
            $this->forceFill(['card_uid' => static::newCardUid()])->save();
        }

        return $this->card_uid;
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (! array_key_exists('card_uid', $user->getAttributes())) {
                $user->card_uid = static::newCardUid();
            }
        });
    }
}