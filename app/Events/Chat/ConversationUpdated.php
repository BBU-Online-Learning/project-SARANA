<?php

namespace App\Events\Chat;

use App\Models\Message;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Message $message
    ) {}

    public function broadcastOn(): array
    {
        return app(\App\Services\Chat\ChatAccessService::class)->broadcastChannels($this->message->room_id);
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'room_id' => $this->message->room_id,
            'body' => $this->message->previewText(),
            'sender' => $this->message->sender->name,
            'created_at' => $this->message->created_at->diffForHumans(),
        ];
    }
}
