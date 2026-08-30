<?php

namespace App\Repositories\Chat;

use App\Models\ChatRoom;
use Illuminate\Support\Facades\DB;

class ChatRoomRepository
{
    /*
    |--------------------------------------------------------------------------
    | USER CHAT ROOMS
    |--------------------------------------------------------------------------
    */

    public function getUserRooms(int $userId)
    {
        return ChatRoom::query()
            /*
            |--------------------------------------------------------------------------
            | USER MEMBERSHIP
            |--------------------------------------------------------------------------
            */
            ->whereHas('members', function ($query) use ($userId) { //"Only return chat rooms that have members matching this condition."
                $query->where('users.id', $userId); //"Does this room have a member whose user ID is $userId?"
            })
            /*
            |--------------------------------------------------------------------------
            | MEMBERS
            |--------------------------------------------------------------------------
            */
            ->with([
                'members:id,name,email,profile', //Laravel loads all members in one additional query.
            ])
            /*
            |--------------------------------------------------------------------------
            | LATEST MESSAGE
            |--------------------------------------------------------------------------
            */
            ->with([
                'messages' => function ($query) {       //Load Latest Message

                    $query->latest()
                        ->limit(1)
                        ->withCount('attachments')
                        ->with([
                            'sender:id,name,profile'
                        ]);
                }
            ])
            /*
            |--------------------------------------------------------------------------
            | UNREAD COUNT
            |--------------------------------------------------------------------------
            */
            ->withCount([
                'messages as unread_count' => function ($query) use ($userId) {
                    $query->whereNull('deleted_for_everyone_at') //Only count active messages.
                        ->where('sender_id', '!=', $userId)   //You don't count messages you sent.
                        ->whereExists(function ($subQuery) use ($userId) {
                            $subQuery->select(DB::raw(1))
                                ->from('chat_room_members')   //Search in: chat_room_members
                                ->whereColumn(          //chat_room_members.room_id = messages.room_id
                                    'chat_room_members.room_id',
                                    'messages.room_id'
                                )
                                ->where('chat_room_members.user_id', $userId)

                                ->where(function ($condition) { //Unread Logic

                                    $condition
                                        ->whereNull('chat_room_members.last_read_at')   //User never opened room

                                        ->orWhereColumn(
                                            'messages.created_at',
                                            '>',
                                            'chat_room_members.last_read_at'
                                        );
                                });
                        });
                }

            ])

            /*
            |--------------------------------------------------------------------------
            | SORTING
            |--------------------------------------------------------------------------
            */
            ->orderByDesc('last_message_at')    //Newest conversations first.
            ->get();
    }

    
}
