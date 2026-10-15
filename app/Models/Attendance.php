<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    public const MODE_QR_CODE = 'qr_code';
    public const MODE_GEOLOCATION = 'geolocation';
    public const MODE_PIN = 'pin';
    public const MODE_BIOMETRIC = 'biometric';
    public const MODE_CARD = 'card';

    public const MODES = [
        self::MODE_QR_CODE,
        self::MODE_GEOLOCATION,
        self::MODE_PIN,
        self::MODE_BIOMETRIC,
        self::MODE_CARD,
    ];

    protected $fillable = [
        'company_id',
        'branch_id',
        'user_id',
        'schedule_id',
        'work_pattern_id',
        'date',
        'check_in_time',
        'check_out_time',
        'status',
        'clock_in_mode',
        'note',
        'ip_address',
        'source',
        'latitude',
        'longitude',
        'sync_uuid',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in_time' => 'datetime',
            'check_out_time' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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

    // Accessors pour alignement avec le schéma ERD (clock_in, clock_out)
    public function getClockInAttribute()
    {
        return $this->check_in_time;
    }

    public function getClockOutAttribute()
    {
        return $this->check_out_time;
    }
}