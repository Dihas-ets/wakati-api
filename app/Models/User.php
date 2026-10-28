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
     * Rôles disponibles dans l'application.
     */
    public const ROLE_RH = 'rh';
    public const ROLE_COMPTABLE = 'comptable';
    public const ROLE_DIRECTEUR = 'directeur';
    public const ROLE_EMPLOYE = 'employe';

    public const ROLES = [
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
        'name',
        'email',
        'phone',
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

    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function positions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Position::class, 'user_positions')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function primaryPosition(): ?Position
    {
        if ($this->relationLoaded('positions')) {
            return $this->positions->firstWhere('pivot.is_primary', true) ?: $this->positions->first();
        }

        return $this->positions()->wherePivot('is_primary', true)->first() ?: $this->positions()->first();
    }

    /**
     * Vérifie le cumul des permissions JSON de tous ses postes rattachés.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->role === self::ROLE_DIRECTEUR) {
            return true;
        }

        $positions = $this->relationLoaded('positions') ? $this->positions : $this->positions()->get();

        foreach ($positions as $pos) {
            $perms = (array) ($pos->permissions ?? []);
            if (in_array('*', $perms, true) || in_array('admin', $perms, true) || in_array($permission, $perms, true)) {
                return true;
            }
        }

        // Rétrocompatibilité avec les rôles existants
        if ($permission === 'rh' && $this->role === self::ROLE_RH) {
            return true;
        }
        if ($permission === 'comptable' && $this->role === self::ROLE_COMPTABLE) {
            return true;
        }
        if ($permission === 'directeur' && $this->role === self::ROLE_DIRECTEUR) {
            return true;
        }

        return false;
    }

    /**
     * Vérifie s'il possède au moins un poste sensible (ex: Comptable, RH).
     */
    public function hasSensitiveRole(): bool
    {
        $positions = $this->relationLoaded('positions') ? $this->positions : $this->positions()->get();

        return $positions->contains(fn (Position $p) => (bool) $p->is_sensitive);
    }

    /**
     * Vérifie si l'utilisateur est considéré comme Administrateur (Directeur ou permission admin).
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_DIRECTEUR || $this->hasPermission('admin');
    }

    public function hasRole(string $role): bool
    {
        if ($this->role === $role) {
            return true;
        }

        // Si le rôle correspond à une permission de poste
        return $this->hasPermission($role);
    }

    public function payrolls(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function auditLogsAuthored(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }

    public function auditLogsReceived(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AuditLog::class, 'target_user_id');
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
     * Forme canonique d'un identifiant de badge.
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
            if (empty($user->role)) {
                $user->role = self::ROLE_EMPLOYE;
            }
        });
    }
}