<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'room_id',
        'call_session_id',
        'sender_id',
        'message_type',
        'body',
        'sticker_id',
        'reply_to_message_id',
        'client_uuid',
        'is_edited',
        'edited_at',
        'deleted_for_everyone_at',
    ];

    protected $casts = [
        'is_edited' => 'boolean',
        'edited_at' => 'datetime',
        'deleted_for_everyone_at' => 'datetime',
    ];

    public function isDeletedForEveryone(): bool   // so when $message->isDeletedForEveryoen return true or false //Checks whether the message was deleted for all participants in the chat.
    {
        return $this->deleted_for_everyone_at !== null;
    }

    public function isHiddenFor(int $userId): bool // Checks whether a specific user has hidden/deleted this message for themselves
    {
        return $this->hiddenByUsers()->where('user_id', $userId)->exists();
    }
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
    | SENDER
    |--------------------------------------------------------------------------
    */

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id')->withTrashed();
    }

    /*
    |--------------------------------------------------------------------------
    | REPLY
    |--------------------------------------------------------------------------
    */

    public function replyTo()
    {
        return $this->belongsTo(Message::class, 'reply_to_message_id');
    }

    /*
    |--------------------------------------------------------------------------
    | REPLIES
    |--------------------------------------------------------------------------
    */
    public function replies()
    {
        return $this->hasMany(Message::class, 'reply_to_message_id');
    }
    /*
    |--------------------------------------------------------------------------
    | ATTACHMENTS
    |--------------------------------------------------------------------------
    */

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'message_id');
    }

    /*
    |--------------------------------------------------------------------------
    | READS
    |--------------------------------------------------------------------------
    */

    public function reads()
    {
        return $this->hasMany(MessageRead::class);
    }

    public function hiddenByUsers()     // his defines a relationship between:messages_TB and massage_user_deletions_TB
    {
        return $this->hasMany(MessageUserDeletion::class, 'message_id');
    }
    /*
    |--------------------------------------------------------------------------
    | REACTIONS
    |--------------------------------------------------------------------------
    */

    public function reactions()
    {
        return $this->hasMany(MessageReaction::class, 'message_id');
    }

    /**
     * Returns emoji => count map, e.g. ['👍' => 3, '❤️' => 2]
     * Skips emojis with 0 count automatically.
     */
    public function reactionCounts(): array
    {
        return $this->reactions()
            ->selectRaw('emoji, COUNT(*) as count')
            ->groupBy('emoji')
            ->pluck('count', 'emoji')
            ->map(fn ($count) => (int) $count)
            ->toArray();
    }

    public function previewText(): string
    {
        if ($this->isDeletedForEveryone()) {
            return 'This message was deleted';
        }

        if ($this->message_type === 'voice') {
            return '🎤 Voice message';
        }

        if ($this->message_type === 'image') {
            return '📷 Photo';
        }

        if ($this->message_type === 'video') {
            return '🎬 Video';
        }

        if ($this->message_type === 'call') {
            return $this->body ?: 'Voice call';
        }

        if ($this->message_type === 'sticker') {
            return 'Sticker';
        }

        if (filled($this->body)) {
            return $this->body;
        }

        $attachments = $this->relationLoaded('attachments')
            ? $this->attachments
            : $this->attachments()->get();

        if ($attachments->isEmpty()) {
            return '';
        }

        if ($attachments->every(fn ($attachment) => method_exists($attachment, 'isAudio') && $attachment->isAudio())) {
            return 'Voice message';
        }

        $count = $attachments->count();

        return match (true) {
            $count === 1 => '📎 Attachment',
            default => "📎 {$count} Attachments",
        };
    }
}
