<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'metadata', 'ip_address'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Enregistre une action. Utilisation :
     * ActivityLog::record($request, 'leave.approved', $leave, ['previous_status' => 'pending']);
     */
    public static function record(?\Illuminate\Http\Request $request, string $action, ?Model $subject = null, array $metadata = []): self
    {
        return static::create([
            'user_id' => $request?->user()?->id,
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
        ]);
    }
}
