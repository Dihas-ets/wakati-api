<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Position extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'title',
        'permissions',
        'is_sensitive',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_sensitive' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_positions')
            ->withPivot('is_primary')
            ->withTimestamps();
    }
}
