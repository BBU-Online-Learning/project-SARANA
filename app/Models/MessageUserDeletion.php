<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageUserDeletion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'user_id',
        'deleted_at',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}