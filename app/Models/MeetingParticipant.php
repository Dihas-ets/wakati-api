<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingParticipant extends Model
{
    use HasFactory;

    public const STATUS_INVITED = 'invited';
    public const STATUS_PRESENT = 'present';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_EXCUSED = 'excused';

    public const METHOD_QR = 'qr_code';
    public const METHOD_RFID = 'rfid';
    public const METHOD_MANUAL = 'manual';
    public const METHOD_MOBILE_APP = 'mobile_app';
    public const METHOD_WEB = 'web';

    protected $fillable = [
        'meeting_id',
        'user_id',
        'attendance_status',
        'checked_in_at',
        'checkin_method',
        'convocation_sent',
        'report_sent',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'convocation_sent' => 'boolean',
            'report_sent' => 'boolean',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
