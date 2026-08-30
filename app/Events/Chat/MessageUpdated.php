<?php
// app\Events\Chat\MessageUpdated.php
namespace App\Events\Chat;

use App\Models\Message;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Message $message
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel(
                'chat.room.' . $this->message->room_id
            ),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->message->id,
            'room_id' => $this->message->room_id,
            'body' => $this->message->body,
            'edited_at' => optional($this->message->edited_at)?->toDateTimeString(),
        ];
    }
}