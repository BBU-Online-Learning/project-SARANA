<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CallSession extends Model
{
    public const ACTIVE_STATUSES = ['ringing', 'active'];

    public const TERMINAL_STATUSES = ['declined', 'missed', 'cancelled', 'ended', 'failed'];

    protected $fillable = [
        'room_id',
        'call_type',
        'initiated_by',
        'status',
        'started_at',
        'answered_at',
        'expires_at',
        'ended_at',
        'end_reason',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'answered_at' => 'datetime',
            'expires_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ROOM
    |--------------------------------------------------------------------------
    */

    public function room(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'room_id');
    }

    /*
    |--------------------------------------------------------------------------
    | INITIATOR
    |--------------------------------------------------------------------------
    */

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /*
    |--------------------------------------------------------------------------
    | PARTICIPANTS
    |--------------------------------------------------------------------------
    */

    public function participants(): HasMany
    {
        return $this->hasMany(CallParticipant::class, 'call_session_id');
    }

    public function historyMessage(): HasOne
    {
        return $this->hasOne(Message::class, 'call_session_id');
    }
}
