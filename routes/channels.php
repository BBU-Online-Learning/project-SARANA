<?php

// Laravel automatically loads this file for broadcasting authentication.
use Illuminate\Support\Facades\Broadcast;   // Used to define channel authorization rules

//

Broadcast::channel('chat.room.{roomId}', function (\App\Models\User $user, string $roomId): array|false {
    $room = \App\Models\ChatRoom::find($roomId);
    if ($room?->type === 'direct' && app(\App\Services\Chat\ChatAccessService::class)->access($user, $room)) {
        return ['id' => $user->id, 'name' => $user->name];
    }

    return false;
});

Broadcast::channel('chat.membership.{membershipId}', function (\App\Models\User $user, string $membershipId): bool {
    return ctype_digit($membershipId) && app(\App\Services\Chat\ChatAccessService::class)->subscription($user, (int) $membershipId);
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
