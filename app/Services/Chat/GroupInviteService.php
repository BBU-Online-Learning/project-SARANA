<?php

namespace App\Services\Chat;

use App\Models\ChatGroupInvite;
use App\Models\ChatRoom;
use App\Models\User;
use App\Notifications\ActivityNotification;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class GroupInviteService
{
    public function __construct(private ChatAccessService $access) {}

    public function create(User $actor, ChatRoom $room): ChatGroupInvite
    {
        return $this->access->synchronized(function () use ($actor, $room): ChatGroupInvite {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $room = ChatRoom::query()->lockForUpdate()->findOrFail($room->id);
            abort_unless($room->type === 'group' && $this->access->ready($actor), 403);
            Gate::forUser($actor)->authorize('manageGroup', $room);

            $room->groupInvites()->usable()->lockForUpdate()->update(['revoked_at' => now()]);

            return $room->groupInvites()->create([
                'created_by' => $actor->id,
                'expires_at' => now()->addDays((int) config('chat.group_invite_lifetime_days', 7)),
            ]);
        });
    }

    public function revoke(User $actor, ChatRoom $room, ChatGroupInvite $invite): void
    {
        $this->access->synchronized(function () use ($actor, $room, $invite): void {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $room = ChatRoom::query()->lockForUpdate()->findOrFail($room->id);
            $invite = ChatGroupInvite::query()->lockForUpdate()->findOrFail($invite->id);
            abort_unless($room->type === 'group' && $invite->room_id === $room->id, 404);
            Gate::forUser($actor)->authorize('manageGroup', $room);
            $invite->update(['revoked_at' => now()]);
        });
    }

    public function join(User $user, ChatGroupInvite $invite): ChatRoom
    {
        return $this->access->synchronized(function () use ($user, $invite): ChatRoom {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $invite = ChatGroupInvite::query()->lockForUpdate()->findOrFail($invite->id);
            $room = ChatRoom::query()->lockForUpdate()->findOrFail($invite->room_id);
            abort_unless($room->type === 'group' && $this->access->ready($user), 403);

            if (! $invite->isUsable()) {
                throw ValidationException::withMessages(['invite' => 'This group invitation has expired or was disabled.']);
            }

            if ($room->roomMembers()->where('user_id', $user->id)->exists()) {
                return $room;
            }

            if ($room->roomMembers()->lockForUpdate()->count() >= 31) {
                throw ValidationException::withMessages(['invite' => 'This group is full. Ask the owner for help.']);
            }

            $room->members()->attach($user->id, ['role' => 'member', 'joined_at' => now()]);
            $invite->increment('joined_count');

            $ownerId = $room->roomMembers()->where('role', 'owner')->value('user_id');
            if ($ownerId && (int) $ownerId !== $user->id) {
                User::query()->find($ownerId)?->notify(new ActivityNotification(
                    'group',
                    'New group member',
                    $user->name.' joined '.$room->name.'.',
                    route('chat.index', ['room' => $room->id], false),
                ));
            }

            return $room;
        });
    }

    /** @return array{invite: ChatGroupInvite, url: string, qr_code: string, expires_at: string} */
    public function presentation(ChatGroupInvite $invite): array
    {
        $expiresAt = $invite->expires_at;
        $url = $this->signedUrl('chat.group-invites.show', $invite);
        $writer = new Writer(new ImageRenderer(new RendererStyle(220, 2), new SvgImageBackEnd));

        return [
            'invite' => $invite,
            'url' => $url,
            'qr_code' => 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($url)),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function joinUrl(ChatGroupInvite $invite): string
    {
        return $this->signedUrl('chat.group-invites.join', $invite);
    }

    private function signedUrl(string $routeName, ChatGroupInvite $invite): string
    {
        $relativeUrl = URL::temporarySignedRoute(
            $routeName,
            $invite->expires_at,
            ['invite' => $invite],
            absolute: false,
        );

        $publicUrl = (string) config('chat.group_invite_public_url');

        if ($this->isValidPublicUrl($publicUrl)) {
            return rtrim($publicUrl, '/').'/'.ltrim($relativeUrl, '/');
        }

        return url($relativeUrl);
    }

    private function isValidPublicUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        return in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            && filled($parts['host'] ?? null)
            && blank($parts['query'] ?? null)
            && blank($parts['fragment'] ?? null)
            && blank($parts['user'] ?? null)
            && blank($parts['pass'] ?? null);
    }
}
