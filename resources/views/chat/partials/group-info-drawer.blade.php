<dialog id="group-info-drawer" class="chat-info-drawer" aria-labelledby="group-info-title">
    <header class="chat-drawer-heading">
        <h2 id="group-info-title">Group information</h2>
        <button type="button" class="teams-icon-button" data-close-group-info aria-label="Close group information">×</button>
    </header>
    <div class="chat-drawer-body">
        <div class="chat-group-identity">
            <div class="teams-room-avatar teams-room-avatar-lg" aria-hidden="true">
                @if($avatar)<img src="{{ $avatar }}" alt="">@else{{ $initial }}@endif
            </div>
            <h3>{{ $room->name }}</h3>
            <p>{{ $memberCount }} members</p>
            @if($room->description)<p>{{ $room->description }}</p>@endif
        </div>
        <p class="small text-muted" data-group-refresh-status role="status"></p>
        @can('manageGroup', $room)
            <a href="{{ route('chat.groups.show', $room) }}" class="btn btn-outline-primary w-100 mb-3">Edit group & manage members</a>
            @include('chat.partials.group-invite-panel', ['groupInviteData' => $groupInviteData ?? null])
        @endcan
        <h3 class="chat-drawer-label">Members</h3>
        <div class="chat-drawer-members">
            @foreach($room->members->sortBy(fn ($member) => $member->pivot->role === 'owner' ? 0 : 1) as $member)
                <a class="chat-drawer-member" href="{{ $member->id === auth()->id() ? route('profile.edit') : route('users.profile', $member) }}">
                    <x-user-avatar :user="$member" :size="38" />
                    <span><strong>{{ $member->name }}{{ $member->id === auth()->id() ? ' (you)' : '' }}</strong>
                        <small>{{ ucwords(str_replace('_', ' ', $member->role?->name ?? 'Member')) }}{{ $member->pivot->role === 'owner' ? ' · Group owner' : '' }}</small>
                    </span>
                </a>
            @endforeach
        </div>
        @if($room->members->firstWhere('id', auth()->id())?->pivot->role !== 'owner')
            <form method="POST" action="{{ route('chat.groups.leave', $room) }}" class="mt-4"
                data-confirm-title="Leave group?" data-confirm-message="You will lose access to this conversation after leaving." data-confirm-label="Leave group">
                @csrf
                <button type="submit" class="btn btn-outline-danger w-100">Leave group</button>
            </form>
        @else
            <p class="text-muted small mt-4">As the group owner, you must remain in this group.</p>
        @endif
    </div>
</dialog>
