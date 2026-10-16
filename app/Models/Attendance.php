<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{

    protected $fillable = [
        'user_id', 'schedule_id', 'work_pattern_id', 'date', 'check_in_time', 'check_out_time',
        'is_on_break', 'current_break_start', 'total_break_minutes', 'break_history',
        'status', 'ip_address', 'source', 'latitude', 'longitude', 'sync_uuid',
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
}