<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallParticipant extends Model
{
    protected $fillable = [
        'call_session_id',
        'user_id',
        'joined_at',
        'left_at'
    ];

    /*
    |--------------------------------------------------------------------------
    | CALL SESSION
    |--------------------------------------------------------------------------
    */

    public function callSession()
    {
        return $this->belongsTo(CallSession::class);
    }

    /*
    |--------------------------------------------------------------------------
    | USER
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}