<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallSession extends Model
{
    protected $fillable = [
        'room_id',
        'initiated_by',
        'status',
        'started_at',
        'ended_at'
    ];

    /*
    |--------------------------------------------------------------------------
    | ROOM
    |--------------------------------------------------------------------------
    */

    public function room()
    {
        return $this->belongsTo(ChatRoom::class, 'room_id');
    }

    /*
    |--------------------------------------------------------------------------
    | INITIATOR
    |--------------------------------------------------------------------------
    */

    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /*
    |--------------------------------------------------------------------------
    | PARTICIPANTS
    |--------------------------------------------------------------------------
    */

    public function participants()
    {
        return $this->hasMany(CallParticipant::class, 'call_session_id');
    }
}