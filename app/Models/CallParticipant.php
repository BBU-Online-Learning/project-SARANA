<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallParticipant extends Model
{
    protected $fillable = [
        'call_session_id',
        'user_id',
        'joined_at',
        'left_at',
        'last_seen_at',
        'client_id',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | CALL SESSION
    |--------------------------------------------------------------------------
    */

    public function callSession(): BelongsTo
    {
        return $this->belongsTo(CallSession::class);
    }

    /*
    |--------------------------------------------------------------------------
    | USER
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
