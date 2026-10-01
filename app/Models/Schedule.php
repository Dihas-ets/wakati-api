<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Schedule extends Model
{
    protected $fillable = ['user_id', 'created_by', 'title', 'date', 'start_time', 'end_time', 'is_remote'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_remote' => 'boolean',
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

    public function attendance(): HasOne
    {
        return $this->hasOne(Attendance::class);
    }

    public function absence(): HasOne
    {
        return $this->hasOne(Absence::class);
    }
}