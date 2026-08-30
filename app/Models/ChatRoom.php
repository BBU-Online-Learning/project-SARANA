<?php
//app\Models\ChatRoom.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatRoom extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type',
        'name',
        'created_by',
        'avatar',
        'description',
        'is_private',
        'last_message_at',
    ];

    /*
    |--------------------------------------------------------------------------
    | CREATOR
    |--------------------------------------------------------------------------
    */

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /*
    |--------------------------------------------------------------------------
    | MEMBERS
    |--------------------------------------------------------------------------
    */

    public function members()
    {
        return $this->belongsToMany(User::class, 'chat_room_members', 'room_id', 'user_id')
            ->withPivot([
                'role',
                'joined_at',
                'last_read_at',
            ])
            ->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | ROOM MEMBER RECORDS
    |--------------------------------------------------------------------------
    */

    public function roomMembers()
    {
        return $this->hasMany(ChatRoomMember::class, 'room_id');
    }

    /*
    |--------------------------------------------------------------------------
    | MESSAGES
    |--------------------------------------------------------------------------
    */

    public function messages()
    {
        return $this->hasMany(Message::class, 'room_id');
    }

    /*
    |--------------------------------------------------------------------------
    | CALLS
    |--------------------------------------------------------------------------
    */

    public function callSessions()
    {
        return $this->hasMany(CallSession::class, 'room_id');
    }

    /*
    |--------------------------------------------------------------------------
    | ATTACHMENTS
    |--------------------------------------------------------------------------
    */

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'room_id');
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class, 'room_id')->latestOfMany();
    }
}
