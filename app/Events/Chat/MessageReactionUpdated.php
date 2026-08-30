<?php
// app/Events/Chat/MessageReactionUpdated.php

namespace App\Events\Chat;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReactionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int   $messageId,
        public int   $roomId,
        public array $reactions  // ['👍' => 3, '❤️' => 2, ...]
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('chat.room.' . $this->roomId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'reaction.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'reactions'  => $this->reactions,
        ];
    }
}