<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{

    protected $fillable = [
        'user_id', 'schedule_id', 'work_pattern_id', 'date', 'check_in_time', 'check_out_time',
        'is_on_break', 'current_break_start', 'total_break_minutes', 'break_history',
        'status', 'ip_address', 'source', 'latitude', 'longitude', 'pause_start', 'pause_end', 'sync_uuid',
    ];

    protected $appends = [
        'effective_work_minutes',
        'effective_work_duration',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in_time' => 'datetime',
            'check_out_time' => 'datetime',
            'is_on_break' => 'boolean',
            'current_break_start' => 'datetime',
            'total_break_minutes' => 'integer',
            'break_history' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function workPattern(): BelongsTo
    {
        return $this->belongsTo(WorkPattern::class);
    }

    public function timeBreaks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TimeBreak::class, 'user_id', 'user_id')
            ->whereDate('date', $this->date);
    }

    /**
     * Calcule le temps de travail effectif en déduisant les pauses cumulées.
     */
    public function getEffectiveWorkMinutesAttribute(): int
    {
        if (! $this->check_in_time) {
            return 0;
        }

        $end = $this->check_out_time ?? \Carbon\Carbon::now();
        $grossMinutes = max(0, (int) $this->check_in_time->diffInMinutes($end));
        $breakMinutes = (int) ($this->total_break_minutes ?? 0);

        if ($this->is_on_break && $this->current_break_start) {
            $currentBreakElapsed = max(0, (int) $this->current_break_start->diffInMinutes($end));
            $breakMinutes += $currentBreakElapsed;
        }

        return max(0, $grossMinutes - $breakMinutes);
    }

    /**
     * Formatage lisible du temps effectif (ex: "7h 35m").
     */
    public function getEffectiveWorkDurationAttribute(): string
    {
        $minutes = $this->effective_work_minutes;
        $hours = intdiv($minutes, 60);
        $rem = $minutes % 60;

        return sprintf('%dh %02dm', $hours, $rem);
    }
}