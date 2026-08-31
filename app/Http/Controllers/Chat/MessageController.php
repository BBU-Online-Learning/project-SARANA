<?php

// app\Http\Controllers\Chat\MessageController.php

namespace App\Http\Controllers\Chat;

use App\Events\Chat\ConversationUpdated;
use App\Events\Chat\MessageDeleted;
use App\Events\Chat\MessageUpdated;        // This gives access to Laravel controller features.
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\StoreMessageRequest;
use App\Http\Requests\Chat\UpdateMessageRequest;
use App\Models\ChatRoom;
use App\Models\Message;
use App\Models\MessageUserDeletion;
use App\Models\User;
use App\Services\Chat\ChatAccessService;
use App\Services\Chat\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    protected $messageService;

    public function __construct(
        MessageService $messageService
    ) {
        $this->messageService = $messageService;
    }

    /*
    |--------------------------------------------------------------------------
    | ROOM MESSAGES
    |--------------------------------------------------------------------------
    */

    public function olderMessages(Request $request, ChatRoom $room)
    {
        $this->authorize('access', $room);

        $messages = $this->messageService
            ->paginateMessages($room, 50, $request->cursor);

        return response()->json([
            'html' => view(
                'chat.partials.message-items',
                [
                    'messages' => collect($messages->items())->reverse()->values(),
                    'room' => $room,
                ]
            )->render(),

            'next_cursor' => optional(
                $messages->nextCursor()
            )->encode(),
        ]);
    }
    /*
    |--------------------------------------------------------------------------
    | STORE MESSAGE
    |--------------------------------------------------------------------------
    */

    public function store(StoreMessageRequest $request, ChatRoom $room)
    {

        $this->authorize('access', $room);
        $message = app(ChatAccessService::class)->withRoom($request->user(), $room,
            fn (User $actor, ChatRoom $room) => $this->messageService->sendMessage($room, $request->validated()));

        return response()->json([
            'success' => true,
        ]);
    }

    public function update(UpdateMessageRequest $request, Message $message)
    {
        $this->authorize('update', $message);

        $message = app(ChatAccessService::class)->withRoom($request->user(), $message->room,
            function (User $actor, ChatRoom $room) use ($message, $request): Message {
                $message = $room->messages()->lockForUpdate()->findOrFail($message->id);
                Gate::forUser($actor)->authorize('update', $message);
                $message->update(['body' => $request->validated('body'), 'edited_at' => now(), 'is_edited' => true]);

                return $message;
            });

        $message->load([
            'sender',
            'replyTo.sender',
            'room.members',
        ]);

        broadcast(
            new MessageUpdated($message)
        )->toOthers();

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'body' => $message->body,
            ],
        ]);
    }

    /*
|--------------------------------------------------------------------------
| DELETE FOR EVERYONE
|--------------------------------------------------------------------------
*/
    public function destroy(Message $message)
    {
        $this->authorize('delete', $message);

        $message = app(ChatAccessService::class)->withRoom(Auth::user(), $message->room,
            function (User $actor, ChatRoom $room) use ($message): Message {
                $message = $room->messages()->lockForUpdate()->findOrFail($message->id);
                Gate::forUser($actor)->authorize('delete', $message);
                $message->update(['deleted_for_everyone_at' => now()]);

                return $message;
            });
        // Load relations so the sidebar can update immediately after delete.
        $message->load(['sender', 'attachments']);

        broadcast(new MessageDeleted($message->id, $message->room_id));
        broadcast(new ConversationUpdated($message));

        return response()->json(['success' => true]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE FOR ME (hide from current user only)
    |--------------------------------------------------------------------------
    */
    public function hideForMe(Message $message)
    {
        $this->authorize('access', $message->room);

        MessageUserDeletion::firstOrCreate([
            'message_id' => $message->id,
            'user_id' => Auth::id(),
        ]);

        return response()->json(['success' => true]);
    }

    public function renderHtml(Message $message)
    {
        $this->authorize('access', $message->room);

        $message->load([
            'sender',
            'replyTo.sender',
            'room.members',
            'reactions',
            'attachments.media',
        ]);

        return response()->json([
            'message_id' => $message->id,
            'html' => view(
                'chat.partials.message-item',
                [
                    'message' => $message,
                    'room' => $message->room,
                ]
            )->render(),
        ]);
    }
}
