<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\ChatGroupInvite;
use App\Models\ChatRoom;
use App\Services\Chat\GroupInviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GroupInviteController extends Controller
{
    public function store(Request $request, ChatRoom $room, GroupInviteService $invites): JsonResponse|RedirectResponse
    {
        $invite = $invites->create($request->user(), $room);
        $presentation = $invites->presentation($invite);

        if (! $request->expectsJson()) {
            return redirect()->route('chat.groups.show', $room)->with('success', 'A new group invitation link was created.');
        }

        return response()->json([
            'message' => 'Invitation link created. It will expire in 7 days.',
            'html' => view('chat.partials.group-invite-panel', ['room' => $room, 'groupInviteData' => $presentation])->render(),
        ]);
    }

    public function destroy(Request $request, ChatRoom $room, ChatGroupInvite $invite, GroupInviteService $invites): JsonResponse|RedirectResponse
    {
        $invites->revoke($request->user(), $room, $invite);

        if (! $request->expectsJson()) {
            return redirect()->route('chat.groups.show', $room)->with('success', 'The group invitation link was disabled.');
        }

        return response()->json([
            'message' => 'Invitation link disabled.',
            'html' => view('chat.partials.group-invite-panel', ['room' => $room, 'groupInviteData' => null])->render(),
        ]);
    }

    public function show(Request $request, ChatGroupInvite $invite, GroupInviteService $invites): Response
    {
        $invite->load(['room.members', 'creator']);
        abort_unless($invite->room?->type === 'group', 404);
        $isMember = $invite->room->roomMembers()->where('user_id', $request->user()->id)->exists();

        return response()->view('chat.invites.show', [
            'invite' => $invite,
            'room' => $invite->room,
            'isMember' => $isMember,
            'available' => $invite->isUsable(),
            'joinUrl' => $invite->isUsable()
                ? $invites->joinUrl($invite)
                : null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function join(Request $request, ChatGroupInvite $invite, GroupInviteService $invites): RedirectResponse
    {
        $room = $invites->join($request->user(), $invite);

        return redirect()->route('chat.index', ['room' => $room->id])
            ->with('success', 'You joined '.$room->name.'.');
    }
}
