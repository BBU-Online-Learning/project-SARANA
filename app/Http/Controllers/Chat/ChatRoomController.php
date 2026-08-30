<?php

// app\Http\Controllers\Chat\ChatRoomController.php/

namespace App\Http\Controllers\Chat;

use App\Http\Requests\Chat\CreateDirectRoomRequest;
use App\Http\Requests\Chat\CreateGroupRoomRequest;
use App\Events\Chat\ReadReceiptUpdated;
use App\Events\Chat\UnreadCountUpdated;
use App\Http\Controllers\Controller;
use App\Models\ChatRoom;
use App\Models\User;
use App\Services\Chat\ChatRoomService;
use App\Services\Chat\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatRoomController extends Controller
{
    protected $chatRoomService;

    protected $messageService;

    public function __construct(ChatRoomService $chatRoomService, MessageService $messageService)
    {
        $this->chatRoomService = $chatRoomService;
        $this->messageService = $messageService;
    }

    /*
    |--------------------------------------------------------------------------
    | CHAT HOME
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $rooms = $this->chatRoomService
            ->getUserRooms(Auth::id());

        $users = User::select('id', 'name')->orderBy('name')->get();

        return view(
            'chat.index',
            compact('rooms', 'users')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD ROOM
    |--------------------------------------------------------------------------
    */

    public function show(ChatRoom $room)
    {
        $this->authorize('access', $room);
        $readAt = now();

        $room->members()->updateExistingPivot(
            Auth::id(),
            [
                'last_read_at' => $readAt,
            ]
        );

        broadcast(
            new ReadReceiptUpdated(
                $room->id,
                Auth::id(),
                $readAt->toDateTimeString(),
                Auth::user()->name
            )
        )->toOthers();

        $room->load([
            'members' => function ($query) {
                $query->select(
                    'users.id',
                    'users.name',
                    'users.profile',
                    'users.last_seen_at'
                );
            },
        ]);

        $messages = $this->messageService->paginateMessages($room);

        return response()->json([

            'html' => view(
                'chat.partials.chat-area',
                [
                    'room' => $room,
                    'messages' => collect($messages->items())->reverse()->values(),
                ]
            )->render(),

            'room_id' => $room->id,

            'next_cursor' => optional($messages->nextCursor())->encode(),
        ]);
    }

    public function createDirectMessage(CreateDirectRoomRequest $request)
    {
        $room = $this->chatRoomService->createDirectRoom(
            Auth::id(),
            (int) $request->validated()['user_id']
        );

        return response()->json([
            'room_id' => $room->id,
        ]);
    }

    public function createGroup(CreateGroupRoomRequest $request)
    {
        $validated = $request->validated();

        $room = $this->chatRoomService->createGroupRoom(
            Auth::id(),
            $validated['name'],
            $validated['members'] ?? []
        );

        return response()->json([
            'room_id' => $room->id,
        ]);
    }

    public function markRead(ChatRoom $room)
    {
        $this->authorize('access', $room);

        $readAt = now();

        $room->members()->updateExistingPivot(
            Auth::id(),
            ['last_read_at' => $readAt]
        );

        broadcast(
            new ReadReceiptUpdated(
                $room->id,
                Auth::id(),
                $readAt->toDateTimeString(),
                Auth::user()->name
            )
        )->toOthers();
        // This clears YOUR own badge (needed for multi-tab sync too)
        broadcast(new UnreadCountUpdated(Auth::id(), $room->id, 0));

        return response()->json(['success' => true]);
    }
}
