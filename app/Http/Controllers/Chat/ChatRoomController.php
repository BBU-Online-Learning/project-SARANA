<?php

// app\Http\Controllers\Chat\ChatRoomController.php/

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\CreateDirectRoomRequest;
use App\Http\Requests\Chat\CreateGroupRoomRequest;
use App\Models\ChatRoom;
use App\Models\User;
use App\Services\Chat\ChatRoomService;
use App\Services\Chat\GroupInviteService;
use App\Services\Chat\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

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

    public function index(Request $request)
    {
        $rooms = $this->chatRoomService
            ->getUserRooms(Auth::id());

        $users = User::query()->with('role:id,name')->select('id', 'name', 'profile', 'role_id', 'last_seen_at')
            ->where('id', '!=', Auth::id())
            ->where('status', 'active')->where('google2fa_enabled', true)->where('must_change_password', false)
            ->whereHas('role', fn ($query) => $query->where('status', true)->whereIn('name', \App\Models\Role::NAMES))
            ->orderBy('name')->get();
        $requestedRoomId = $request->integer('room');
        $initialRoomId = $requestedRoomId > 0 && $rooms->contains('id', $requestedRoomId) ? $requestedRoomId : null;

        return view(
            'chat.index',
            compact('rooms', 'users', 'initialRoomId')
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
        $room->load([
            'members' => function ($query) {
                $query->select(
                    'users.id',
                    'users.name',
                    'users.profile',
                    'users.last_seen_at',
                    'users.role_id',
                    'users.status',
                    'users.google2fa_enabled',
                    'users.must_change_password'
                );
            },
        ]);

        $messages = $this->messageService->paginateMessages($room);
        $room->loadMissing('members.role:id,name');
        $groupInviteData = null;
        if ($room->type === 'group' && Gate::allows('manageGroup', $room)) {
            $invite = $room->groupInvites()->usable()->latest()->first();
            $groupInviteData = $invite ? app(GroupInviteService::class)->presentation($invite) : null;
        }

        return response()->json([

            'html' => view(
                'chat.partials.chat-area',
                [
                    'room' => $room,
                    'messages' => collect($messages->items())->reverse()->values(),
                    'groupInviteData' => $groupInviteData,
                ]
            )->render(),

            'room_id' => $room->id,

            'room_type' => $room->type,
            'membership_id' => $room->roomMembers()->where('user_id', Auth::id())->sole()->id,

            'next_cursor' => optional($messages->nextCursor())->encode(),
        ])->header('Cache-Control', 'private, no-store');
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
}
