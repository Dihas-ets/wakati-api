<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'company_id',
        'name',
        'description',
    ];

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
