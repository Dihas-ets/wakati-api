<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, BelongsToTenant;

    /**
     * Rôles disponibles dans l'application.
     */
    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_COMPANY_ADMIN = 'company_admin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_EMPLOYEE = 'employee';

    public const ROLE_RH = 'rh';
    public const ROLE_COMPTABLE = 'comptable';
    public const ROLE_DIRECTEUR = 'directeur';
    public const ROLE_EMPLOYE = 'employe';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_COMPANY_ADMIN,
        self::ROLE_MANAGER,
        self::ROLE_EMPLOYEE,
        self::ROLE_RH,
        self::ROLE_COMPTABLE,
        self::ROLE_DIRECTEUR,
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
        'agency_id',
        'name',
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

    public function agency(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function attendances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isCompanyAdmin(): bool
    {
        return $this->role === self::ROLE_COMPANY_ADMIN;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_COMPANY_ADMIN, self::ROLE_DIRECTEUR], true);
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isEmployee(): bool
    {
        return in_array($this->role, [self::ROLE_EMPLOYEE, self::ROLE_EMPLOYE], true);
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

    public function meetingsCreated(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Meeting::class, 'redactor_id');
    }

    public function meetings(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Meeting::class, 'meeting_participants')
            ->withPivot([
                'id',
                'attendance_status',
                'checked_in_at',
                'checkin_method',
                'convocation_sent',
                'report_sent',
            ])
            ->withTimestamps();
    }

    public function meetingAttendances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    public function assignedKanbanTasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(KanbanTask::class, 'assigned_to');
    }

    public function createdKanbanTasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(KanbanTask::class, 'created_by');
    }

    public function timeBreaks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TimeBreak::class);
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