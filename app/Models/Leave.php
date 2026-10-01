<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leave extends Model
{
    public const TYPE_DEPLACEMENT = 'deplacement';
    public const TYPE_MALADIE = 'maladie';
    public const TYPE_ANNUEL = 'annuel';
    public const TYPE_MATERNITE = 'maternite';

    public const TYPES = [
        self::TYPE_DEPLACEMENT,
        self::TYPE_MALADIE,
        self::TYPE_ANNUEL,
        self::TYPE_MATERNITE,
    ];

    public const MODE_TELETRAVAIL = 'teletravail';
    public const MODE_PRESENTIEL = 'presentiel';
    public const MODE_HYBRIDE = 'hybride';
    public const MODE_DEPLACEMENT = 'deplacement';

    public const MODES = [
        self::MODE_TELETRAVAIL,
        self::MODE_PRESENTIEL,
        self::MODE_HYBRIDE,
        self::MODE_DEPLACEMENT,
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'user_id',
        'created_by',
        'leave_type',
        'location',
        'date',
        'start_time',
        'end_time',
        'hours',
        'work_mode',
        'notes',
        'status',
        'decided_by',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'hours' => 'decimal:2',
            'decided_at' => 'datetime',
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

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}