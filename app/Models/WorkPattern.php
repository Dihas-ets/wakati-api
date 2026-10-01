<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkPattern extends Model
{
    protected $fillable = [
        'user_id',
        'created_by',
        'day_of_week',
        'start_time',
        'end_time',
        'effective_from',
        'effective_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Retourne le pattern actif d'un utilisateur pour une date donnée
     * (jour de semaine correspondant + période effective), s'il existe.
     */
    public static function activeFor(int $userId, Carbon $date): ?self
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('day_of_week', $date->dayOfWeek)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_until')
                  ->orWhereDate('effective_until', '>=', $date->toDateString());
            })
            ->first();
    }
}