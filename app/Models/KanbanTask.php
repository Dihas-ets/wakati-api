<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KanbanTask extends Model
{
    use HasFactory;

    public const STATUS_TODO = 'todo';
    public const STATUS_INITIAL = 'initial';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_REVIEW = 'review';
    public const STATUS_DONE = 'done';

    public const STATUSES = [
        self::STATUS_TODO,
        self::STATUS_INITIAL,
        self::STATUS_IN_PROGRESS,
        self::STATUS_REVIEW,
        self::STATUS_DONE,
    ];

    protected $fillable = [
        'meeting_id',
        'meeting_topic_id',
        'title',
        'description',
        'assigned_to',
        'created_by',
        'start_date',
        'due_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(MeetingTopic::class, 'meeting_topic_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
