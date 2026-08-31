<?php

// Send real-time message updates from backend → frontend through WebSockets.
// This is what powers:
// live chat
// instant notifications
// real-time updates
// Laravel Echo
// Reverb / Pusher broadcasting
// without refreshing the page.
// + HIGH-LEVEL FLOW : When a message is created:
// Controller
//    ↓
// Service
//    ↓
// MessageSent Event
//    ↓
// Broadcast Driver (Reverb)
//    ↓
// WebSocket
//    ↓
// Frontend Receives Instantly

namespace App\Events\Chat;

// API Resource This centralizes response formatting.
use App\Models\Message;
// use Illuminate\Broadcasting\PrivateChannel;     //This creates authenticated/private WebSocket channels. Only authorized users can listen.
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;   // This tells Laravel:  Broadcast this event immediately. Without queue delay.
use Illuminate\Foundation\Events\Dispatchable;  // Adds helper methods for dispatching events.
use Illuminate\Queue\SerializesModels;    // /Optimizes Eloquent model serialization.

class MessageSent implements ShouldBroadcastNow // This event: broadcasts instantly ,participates in Laravel event system
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(// This is PHP constructor property promotion.
        public Message $message     // the message model becomes available throughout event.
    ) {}

    public function broadcastOn(): array
    {
        return app(\App\Services\Chat\ChatAccessService::class)->broadcastChannels($this->message->room_id);
    }

    public function broadcastAs(): string   // Defines custom frontend event name.
    {
        return 'message.sent';              // Event Name ==> Frontend listens for: .listen('.message.sent', ...)
    }

    public function broadcastWith(): array  // What data gets sent to frontend.
    {

        return [
            'message_id' => $this->message->id,
            'client_uuid' => $this->message->client_uuid,
            'room_id' => $this->message->room_id,
        ];
    }
}
