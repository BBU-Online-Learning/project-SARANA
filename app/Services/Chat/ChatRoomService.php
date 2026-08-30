<?php

namespace App\Services\Chat;

use App\Models\ChatRoom;
use App\Repositories\Chat\ChatRoomRepository;
use Illuminate\Support\Facades\DB;

class ChatRoomService   // The manager between controllers and repositories.
{
    protected $chatRoomRepository;

    public function __construct(ChatRoomRepository $chatRoomRepository) // called Dependency Injection (DI).
    {
        $this->chatRoomRepository = $chatRoomRepository;    // Stores repository inside service.
    }

    /*
    |--------------------------------------------------------------------------
    | USER ROOMS
    |--------------------------------------------------------------------------
    */

    public function getUserRooms(int $userId)
    {
        return $this->chatRoomRepository
            ->getUserRooms($userId);
    }

    public function createDirectRoom(int $currentUser, int $otherUser)
    {
        return DB::transaction(function () use ($currentUser, $otherUser) {

            $existing = ChatRoom::where('type', 'direct')
                ->whereHas(
                    'members',
                    fn($q) => $q->where(
                        'users.id',
                        $currentUser
                    )
                )
                ->whereHas(
                    'members',
                    fn($q) => $q->where(
                        'users.id',
                        $otherUser
                    )
                )
                ->first();

            if ($existing) {
                return $existing;
            }

            $room = ChatRoom::create([
                'type' => 'direct',
                'created_by' => $currentUser,
            ]);

            $room->members()->attach([ //This inserts records into the chat_room_members pivot table.
                $currentUser,
                $otherUser,
            ]);

            return $room;
        });
    }

    public function createGroupRoom(int $creator, string $name, array $members)
    {
        return DB::transaction(function () use ($creator, $name, $members) {
            $memberIds = collect($members)
                ->map(fn($id) => (int) $id)
                ->filter(fn($id) => $id > 0)
                ->unique()
                ->push($creator)
                ->unique()
                ->values()
                ->all();

            $room = ChatRoom::create([
                'type' => 'group',
                'name' => $name,
                'created_by' => $creator,
            ]);

            $room->members()->attach($memberIds);

            return $room;
        });
    }
}
