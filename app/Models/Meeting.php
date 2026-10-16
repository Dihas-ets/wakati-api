<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meeting extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONVOKED = 'convoked';
    public const STATUS_ONGOING = 'ongoing';
    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_CONVOKED,
        self::STATUS_ONGOING,
        self::STATUS_COMPLETED,
    ];

    protected $fillable = [
        'title',
        'date',
        'time',
        'location',
        'redactor_id',
        'agenda',
        'summary_decisions',
        'drh_notes',
        'status',
        'docx_file_path',
    ];

    protected $appends = [
        'attendance_stats',
        'is_today',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }

    public function redactor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redactor_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_participants')
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

    public function meetingParticipants(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(MeetingTopic::class)->orderBy('id');
    }

    public function kanbanTasks(): HasMany
    {
        return $this->hasMany(KanbanTask::class)->orderBy('id');
    }

    public function validations(): HasMany
    {
        return $this->hasMany(MeetingValidation::class);
    }

    /**
     * Scope pour filtrer les réunions du jour.
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('date', Carbon::today());
    }

    /**
     * Scope pour les réunions à venir ou en cours.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('date', '>=', Carbon::today())
            ->whereIn('status', [self::STATUS_CONVOKED, self::STATUS_ONGOING]);
    }

    /**
     * Indique si la réunion a lieu aujourd'hui.
     */
    public function getIsTodayAttribute(): bool
    {
        if (! $this->date) {
            return false;
        }

        $meetingDate = $this->date instanceof Carbon
            ? $this->date->toDateString()
            : Carbon::parse($this->date)->toDateString();

        return $meetingDate === Carbon::today()->toDateString();
    }

    /**
     * Vérifie si le self-check-in est permis le jour J.
     */
    public function isSelfCheckInAllowed(): bool
    {
        return $this->is_today && in_array($this->status, [self::STATUS_CONVOKED, self::STATUS_ONGOING]);
    }

    /**
     * Calcule en temps réel le ratio des effectifs et statistiques d'émargement.
     */
    public function getAttendanceStatsAttribute(): array
    {
        // Si les participants ne sont pas chargés, on compte via relation
        $participants = $this->relationLoaded('meetingParticipants')
            ? $this->meetingParticipants
            : $this->meetingParticipants()->get();

        $totalInvited = $participants->count();
        $totalPresent = $participants->where('attendance_status', MeetingParticipant::STATUS_PRESENT)->count();
        $totalAbsent = $participants->where('attendance_status', MeetingParticipant::STATUS_ABSENT)->count();
        $totalExcused = $participants->where('attendance_status', MeetingParticipant::STATUS_EXCUSED)->count();

        $attendanceRate = $totalInvited > 0
            ? round(($totalPresent / $totalInvited) * 100, 1)
            : 0.0;

        return [
            'total_invited' => $totalInvited,
            'total_present' => $totalPresent,
            'total_absent' => $totalAbsent,
            'total_excused' => $totalExcused,
            'attendance_rate_percent' => $attendanceRate,
            'ratio_label' => "{$totalPresent} / {$totalInvited} ({$attendanceRate}%)",
        ];
    }
}
