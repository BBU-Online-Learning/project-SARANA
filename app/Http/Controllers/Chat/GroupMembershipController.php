<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\AddGroupMembersRequest;
use App\Http\Requests\Chat\UpdateGroupRequest;
use App\Models\ChatRoom;
use App\Models\Role;
use App\Models\User;
use App\Services\Chat\ChatAccessService;
use App\Services\Chat\GroupMembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class GroupMembershipController extends Controller
{
    public function show(ChatRoom $room): Response
    {
        abort_unless($room->type === 'group', 404);
        Gate::authorize('access', $room);
        $room->load('members');
        $owner = app(ChatAccessService::class)->owner($room);
        $users = Gate::allows('manageGroup', $room) ? User::query()->select('id', 'name')
            ->where('status', 'active')->where('google2fa_enabled', true)->where('must_change_password', false)
            ->whereHas('role', fn ($query) => $query->where('status', true)->whereIn('name', Role::NAMES))
            ->whereNotIn('id', $room->members->modelKeys())->orderBy('name')->get() : collect();

        return response()->view('chat.group', compact('room', 'owner', 'users'))->header('Cache-Control', 'private, no-store');
    }

    public function update(UpdateGroupRequest $request, ChatRoom $room): RedirectResponse
    {
        app(GroupMembershipService::class)->rename($request->user(), $room, $request->validated('name'));

        return redirect()->route('chat.groups.show', $room)->with('success', 'Group renamed.');
    }

    public function add(AddGroupMembersRequest $request, ChatRoom $room): RedirectResponse
    {
        app(GroupMembershipService::class)->add($request->user(), $room, $request->validated('members'));

        return redirect()->route('chat.groups.show', $room)->with('success', 'Members added.');
    }

    public function remove(Request $request, ChatRoom $room, User $user): RedirectResponse
    {
        abort_unless($room->type === 'group', 404);
        app(GroupMembershipService::class)->remove($request->user(), $room, $user->id);

        return redirect()->route('chat.groups.show', $room)->with('success', 'Member removed.');
    }

    public function leave(Request $request, ChatRoom $room): RedirectResponse
    {
        abort_unless($room->type === 'group', 404);
        app(GroupMembershipService::class)->remove($request->user(), $room, $request->user()->id, leaving: true);

        return redirect()->route('chat.index')->with('success', 'You left the group.');
    }

    public function access(Request $request, ChatRoom $room): JsonResponse
    {
        Gate::authorize('access', $room);

        return response()->json([
            'room_id' => $room->id, 'type' => $room->type, 'name' => $room->name,
            'membership_id' => $room->roomMembers()->where('user_id', $request->user()->id)->sole()->id,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function typing(Request $request, ChatRoom $room): JsonResponse
    {
        abort_unless($room->type === 'group', 404);
        Gate::authorize('access', $room);
        rescue(fn () => event(new \App\Events\Chat\GroupTyping($room->id, $request->user()->id)), report: false);

        return response()->json(['success' => true]);
    }
}
