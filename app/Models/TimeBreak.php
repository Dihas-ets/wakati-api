<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeBreak extends Model
{
    use HasFactory;

    public const TYPE_LUNCH = 'lunch';
    public const TYPE_COFFEE = 'coffee';
    public const TYPE_PERSONAL = 'personal';

    public const TYPES = [
        self::TYPE_LUNCH,
        self::TYPE_COFFEE,
        self::TYPE_PERSONAL,
    ];

    protected $fillable = [
        'user_id',
        'date',
        'break_start',
        'break_end',
        'break_type',
        'duration_minutes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'break_start' => 'datetime',
            'break_end' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope pour les pauses d'aujourd'hui.
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('date', Carbon::today());
    }

    /**
     * Scope pour la pause actuellement active (non terminée).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('break_end');
    }

    /**
     * Termine la pause en cours et calcule la durée en minutes.
     */
    public function endBreak(?Carbon $endTime = null): int
    {
        $end = $endTime ?? Carbon::now();
        $start = $this->break_start ?? $end;
        $duration = max(1, (int) $start->diffInMinutes($end));

        $this->update([
            'break_end' => $end,
            'duration_minutes' => $duration,
        ]);

        return $duration;
    }
}
