<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAttendanceSession extends Model
{
    protected $fillable = ['class_meeting_id', 'user_id', 'room_sid', 'participant_sid', 'joined_at', 'left_at', 'leave_reason'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime', 'left_at' => 'datetime'];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(ClassMeeting::class, 'class_meeting_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
