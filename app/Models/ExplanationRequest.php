<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExplanationRequest extends Model
{
    protected $fillable = [
        'user_id', 'schedule_id', 'work_pattern_id', 'date', 'sent_at', 'response', 'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}