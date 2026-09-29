<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ClassMeetingJoinRequest extends Model
{
    /** @use HasFactory<\Database\Factories\ClassMeetingJoinRequestFactory> */
    use HasFactory;

    public const PENDING = 'pending';

    public const ADMITTED = 'admitted';

    public const DENIED = 'denied';

    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'public_uuid', 'class_meeting_id', 'requester_user_id', 'status',
        'requested_at', 'decided_at', 'decided_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $joinRequest): void {
            $joinRequest->public_uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_uuid';
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(ClassMeeting::class, 'class_meeting_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function admitsCurrentEntry(): bool
    {
        if ($this->status !== self::ADMITTED || $this->decided_at === null) {
            return false;
        }

        $meeting = $this->meeting;

        return $meeting !== null && ($meeting->rescheduled_at === null || $this->decided_at->greaterThanOrEqualTo($meeting->rescheduled_at));
    }
}
