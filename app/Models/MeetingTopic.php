<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingTopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'subject',
        'current_situation',
        'evolution_decision',
        'responsible_id',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function kanbanTasks(): HasMany
    {
        return $this->hasMany(KanbanTask::class, 'meeting_topic_id');
    }
}
