<?php

// app\Models\ChatRoom.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

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

    public function avatarUrl(): ?string
    {
        $avatar = $this->avatar;
        if (! is_string($avatar) || $avatar === '') {
            return null;
        }
        if (filter_var($avatar, FILTER_VALIDATE_URL) && in_array(parse_url($avatar, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return $avatar;
        }
        if (preg_match('~^/?storage/(images/groups/[A-Za-z0-9_-]+\.(?:jpe?g|png|webp))$~iD', $avatar, $matches)) {
            return Storage::disk('public')->url($matches[1]);
        }
        if (preg_match('~^(images/groups/[A-Za-z0-9_-]+\.(?:jpe?g|png|webp))$~iD', $avatar)) {
            return Storage::disk('public')->url($avatar);
        }

        return null;
    }

    public function managedAvatarPath(): ?string
    {
        $avatar = $this->avatar;
        if (! is_string($avatar)) {
            return null;
        }
        $path = parse_url($avatar, PHP_URL_PATH) ?: $avatar;

        return preg_match('~^/?(?:storage/)?(images/groups/[A-Za-z0-9_-]+\.(?:jpe?g|png|webp))$~iD', $path, $matches)
            ? $matches[1] : null;
    }

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
