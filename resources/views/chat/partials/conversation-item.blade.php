{{-- resources/views/chat/partials/conversation-item.blade.php --}}
@php
    $latestMessage = $room->messages->first();
    $isDirect = $room->type === 'direct';

    $otherUser = $isDirect ? $room->members->where('id', '!=', auth()->id())->first() : null;
    $displayName = $isDirect ? $otherUser?->name ?? 'Direct chat' : $room->name ?? 'Group chat';
    $initial = strtoupper(substr($displayName, 0, 1));
@endphp

<div class="room-item teams-room-card" data-room-id="{{ $room->id }}" tabindex="0">
    <div class="teams-room-avatar">
        {{ $initial }}

        @if ($isDirect)
            <span class="presence-dot" data-user-id="{{ $otherUser?->id }}"></span>
        @endif
    </div>

    <div class="teams-room-content">
        <div class="teams-room-title-row">
            <h2 class="room-name">{{ $displayName }}</h2>
            @if ($isDirect)
                <span class="room-last-time user-status" data-user-id="{{ $otherUser?->id }}"
                    data-last-seen="{{ $otherUser?->last_seen_at?->toIso8601String() }}">
                    {{ $otherUser?->last_seen_at ? 'Last seen ' . $otherUser->last_seen_at->diffForHumans() : 'Offline' }}
                </span>
            @else
                <span class="room-last-time">
                    {{ optional($room->last_message_at)?->diffForHumans() }}
                </span>
            @endif
        </div>

        <div class="teams-room-preview-row">
            <p class="room-last-message">
                @if (!$latestMessage)
                    No messages yet
                @elseif ($latestMessage->isDeletedForEveryone())
                    <em>This message was deleted</em>
                @else
                    <span>{{ $latestMessage->sender->name }}:</span>
                    {{ Str::limit($latestMessage->previewText(), 48) }}
                @endif
            </p>

            @if ($room->unread_count > 0)
                <span class="unread-badge">
                    {{ $room->unread_count }}
                </span>
            @endif
        </div>
    </div>
</div>
