{{-- resources/views/chat/partials/message-item.blade.php --}}
@php
    $viewerId = $currentUserId ?? auth()->id();
    $isOwn = $message->sender_id === $viewerId;
    $isDeleted = $message->isDeletedForEveryone();
    $isGroup = $room->type !== 'direct';

    $otherMembers = $isOwn && !$isDeleted ? $room->members->where('id', '!=', $viewerId) : collect();

    // Members (other than the sender) who have read this specific message,
    // derived from the existing per-room last_read_at pivot — no new table needed.
    $seenByMembers = $otherMembers
        ->filter(function ($member) use ($message) {
            return $member->pivot->last_read_at && $member->pivot->last_read_at >= $message->created_at;
        })
        ->values();

    $isRead =
        $isOwn && !$isDeleted && $otherMembers->isNotEmpty()
            ? $otherMembers->count() === $seenByMembers->count()
            : false;

    $senderInitial = strtoupper(substr($message->sender->name, 0, 1));
    // How many avatars to show before collapsing into "+N"
    $maxAvatars = 3;
    $visibleSeen = $seenByMembers->take($maxAvatars);
    $overflowSeenCount = max($seenByMembers->count() - $maxAvatars, 0);

    // Reaction Data
    $reactionCounts = $message->relationLoaded('reactions') ? $message->reactionCounts() : [];
    $currentUserReactions = $message->relationLoaded('reactions')
        ? $message->reactions->where('user_id', $viewerId)->pluck('emoji')->toArray()
        : [];
@endphp

<div class="message-item teams-message {{ $isOwn ? 'is-own' : 'is-other' }} {{ $isDeleted ? 'is-deleted' : '' }}"
    data-message-id="{{ $message->id }}" data-sender-id="{{ $message->sender_id }}"
    data-created-at="{{ $message->created_at->timestamp }}" data-client-id="{{ $message->client_uuid }}"
    data-message-type="{{ $message->message_type }}">

    <div class="teams-message-avatar">
        {{ $senderInitial }}
    </div>

    <div class="teams-message-stack">

        {{-- Toolbar: hidden when message is deleted --}}
        @if (!$isDeleted)
            <div class="teams-message-toolbar">
                {{-- Reply Button --}}
                <button class="reply-btn" data-message-id="{{ $message->id }}"
                    data-message-body="{{ e($message->body) }}">
                    <i class="ti ti-arrow-back-up"></i>
                </button>

                {{-- Delete button — available to own messages only --}}
                @if ($isOwn)
                    <button class="delete-message-btn" data-message-id="{{ $message->id }}"
                        aria-label="Delete message">
                        <i class="ti ti-trash"></i>
                    </button>
                @endif

                {{-- Delete For Me — available to everyone --}}
                @if (!$isOwn)
                    <button class="hide-message-btn" data-message-id="{{ $message->id }}" aria-label="Delete for me">
                        <i class="ti ti-trash"></i>
                    </button>
                @endif
                {{-- Edit Message --}}
                @if ($isOwn && $message->created_at->gt(now()->subMinutes(15)) && !$isDeleted)
                    <button class="edit-message-btn" data-message-id="{{ $message->id }}"
                        data-message-body="{{ e($message->body) }}">
                        <i class="ti ti-edit"></i>
                    </button>
                @endif
                {{-- Reaction picker trigger --}}
                @if (!$isDeleted)
                    <button class="reaction-trigger-btn" data-message-id="{{ $message->id }}"
                        aria-label="Add reaction">
                        <i class="ti ti-mood-smile"></i>
                    </button>
                @endif
            </div>
        @endif

        <div class="teams-message-meta">
            <strong>{{ $message->sender->name }}</strong>
            <span>
                {{ $message->created_at->format('h:i A') }}
                @if ($message->edited_at && !$isDeleted)
                    <span class="teams-edited-label">edited</span>
                @endif
            </span>
        </div>

        {{-- Reply preview — show "Deleted message" if parent was deleted --}}
        @if (!$isDeleted && $message->replyTo)
            <div class="teams-reply-preview jump-to-message" data-target-message-id="{{ $message->replyTo->id }}">
                <div class="reply-line"></div>
                <div class="reply-content">
                    <div class="reply-author">
                        {{ $message->replyTo->sender->name ?? 'Unknown' }}
                    </div>
                    <div class="reply-text">
                        {{-- Deleted parent --}}
                        @if ($message->replyTo->isDeletedForEveryone())
                            <em>Deleted message</em>
                            {{-- Voice parent --}}
                        @elseif ($message->replyTo->message_type === 'voice')
                            <em>Voice message</em>
                            {{-- Text parent --}}
                        @elseif (filled($message->replyTo->body))
                            {{ Str::limit($message->replyTo->body, 80) }}
                        @else
                            <em>Attachment</em>
                        @endif
                    </div>
                </div>
            </div>
        @endif


        {{-- Bubble --}}
        @if ($isDeleted)
            <div class="teams-message-bubble teams-message-deleted">
                <i class="ti ti-ban" aria-hidden="true"></i>
                This message was deleted
            </div>
        @elseif (filled($message->body))
            <div class="teams-message-bubble" data-message-body="{{ e($message->body) }}">
                {{ $message->body }}
            </div>
        @endif
        {{-- Attachment Rendering --}}
        @if (!$isDeleted && $message->attachments->isNotEmpty())
            <div class="message-attachments">
                @foreach ($message->attachments as $attachment)
                    {{-- Voice note --}}
                    @if ($attachment->isAudio())
                        <div class="voice-message-card">
                            <div class="voice-message-icon">
                                <i class="ti ti-microphone"></i>
                            </div>

                            <div class="voice-message-body">
                                <strong>Voice message</strong>
                                <audio controls preload="metadata" src="{{ $attachment->url() }}"></audio>
                            </div>
                        </div>

                        {{-- Image --}}
                    @elseif ($attachment->isImage())
                        <img src="{{ $attachment->thumbUrl() }}" class="message-attachment-thumb"
                            data-attachment-id="{{ $attachment->id }}" data-full-src="{{ $attachment->url() }}"
                            data-filename="{{ $attachment->original_name }}" alt="{{ $attachment->original_name }}"
                            loading="lazy">

                        {{-- Other files --}}
                    @else
                        <a href="{{ $attachment->url() }}" target="_blank" rel="noopener" class="attachment-file-card">
                            <span class="attachment-file-icon">
                                {{ strtoupper($attachment->extension ?? 'FILE') }}
                            </span>
                            <span class="attachment-file-info">
                                <span class="attachment-file-name">{{ $attachment->original_name }}</span>
                                <span class="attachment-file-size">{{ $attachment->humanSize() }}</span>
                            </span>
                            <i class="ti ti-download"></i>
                        </a>
                    @endif
                @endforeach
            </div>
        @endif

        {{-- REACTION PICKER POPUP (hidden by default) --}}
        <div class="reaction-picker" data-message-id="{{ $message->id }}" style="display:none;">
            @foreach (config('chat.allowed_reactions') as $emoji)
                <button class="reaction-picker-emoji" data-message-id="{{ $message->id }}"
                    data-emoji="{{ $emoji }}">
                    {{ $emoji }}
                </button>
            @endforeach
        </div>
        {{-- REACTION PILLS --}}
        @if (!$isDeleted)
            <div class="message-reactions" data-message-id="{{ $message->id }}">
                @foreach ($message->reactionCounts() as $emoji => $count)
                    @if ($count > 0)
                        <button
                            class="reaction-pill {{ $message->reactions->where('user_id', auth()->id())->where('emoji', $emoji)->isNotEmpty()? 'is-mine': '' }}"
                            data-message-id="{{ $message->id }}" data-emoji="{{ $emoji }}">
                            {{ $emoji }} {{ $count }}
                        </button>
                    @endif
                @endforeach
            </div>
        @endif

        @if ($isOwn && !$isDeleted)
            <div class="teams-read-row" data-message-id="{{ $message->id }}"
                data-is-group="{{ $isGroup ? '1' : '0' }}">

                @if ($isGroup)
                    {{-- GROUP: avatar stack of who has seen it --}}
                    <div class="seen-by-stack {{ $seenByMembers->isEmpty() ? 'is-empty' : '' }}">
                        @foreach ($visibleSeen as $seenUser)
                            <span class="seen-by-avatar" data-user-id="{{ $seenUser->id }}"
                                title="Seen by {{ $seenUser->name }}">
                                {{ strtoupper(substr($seenUser->name, 0, 1)) }}
                            </span>
                        @endforeach

                        @if ($overflowSeenCount > 0)
                            <span class="seen-by-overflow" title="and {{ $overflowSeenCount }} more">
                                +{{ $overflowSeenCount }}
                            </span>
                        @endif
                    </div>
                @else
                    {{-- DIRECT: keep the simple checkmark --}}
                    <span class="read-status" data-message-id="{{ $message->id }}">
                        {!! $isRead ? '&check;&check;' : '&check;' !!}
                    </span>
                @endif

            </div>
        @endif

    </div>
</div>
