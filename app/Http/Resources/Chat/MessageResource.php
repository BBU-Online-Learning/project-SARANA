<?php
///app\Http\Resources\Chat\MessageResource.php
// Its job is to transform a database model (usually an Eloquent model) into a clean JSON response for APIs.
// Laravel lets you use Resources to control:
// - what fields are exposed
// - how data is formatted
// - how relationships are structured
// - how security/sanitization is handled
// This is part of a clean backend architecture.
namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;    //JsonResource is Laravel's base API Resource class.Your class inherits all resource functionality from it.

class MessageResource extends JsonResource  //You are creating a custom API transformer for a Message model.access fields using: $this->id, $this->body,...
{
    public function toArray(Request $request): array        //"How should this model look when converted into JSON?"
    {
        return [    //This array becomes the JSON response.

            'id' => $this->id,
            'client_uuid' => $this->client_uuid,
            'room_id' => $this->room_id,
            'body' => $this->body,       

            'message_type' => $this->message_type,
            'is_edited' => $this->is_edited,
            'edited_at' => $this->edited_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),    //ISO Date Formatting gets "2026-05-28 14:22:11" converted into: ""created_at": "2026-05-28T14:22:11.000000Z""
            'format_time' => $this->created_at->format('h:i A'),
            'timestamp' => $this->created_at->timestamp,
            'sender' => [
                'id' => $this->sender->id,      //$this->sender ==> $message->sender what we get is 1 user record
                'name' => $this->sender->name,
                'profile' => $this->sender->profile,
            ],
            'reply_to' => $this->whenLoaded('replyTo', function () {
                return [
                    'id' => $this->replyTo->id,
                    'body' => $this->replyTo->body,
                    'sender_name' => $this->replyTo->sender->name,
                ];
            }),
        ];
    }
}
