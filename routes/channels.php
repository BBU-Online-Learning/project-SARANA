<?php

// Laravel automatically loads this file for broadcasting authentication.
use Illuminate\Support\Facades\Broadcast;   // Used to define channel authorization rules
use Illuminate\Support\Facades\DB;   //

Broadcast::channel('chat.room.{roomId}', function ($user, $roomId) {    // Broadcast::channel(  "This registers a broadcasting authorization callback. Meaning: “When someone tries to subscribe to this channel, run this logic.”
    // CHANNEL NAME 'chat.room.{roomId}', Defines a dynamic private channel pattern.
    if (DB::table('chat_room_members')
        ->where('room_id', $roomId)
        ->where('user_id', $user->id)
        ->exists()
    ) {

        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }

    return false;
});

// Create Presence Channel
Broadcast::channel('online', function ($user) {
    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
}
);
// Private channel for per-user notifications (unread counts, etc.)
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('school-class.channel.{channelId}', function ($user, $channelId) {
    // Retired: shared topics cannot revoke a single already-connected member.
    return false;
});

Broadcast::channel('school-class.membership.{membershipId}', function (\App\Models\User $user, string $membershipId): bool {
    return ctype_digit($membershipId)
        && app(\App\Services\ClassAccessService::class)->subscription($user, (int) $membershipId);
});
