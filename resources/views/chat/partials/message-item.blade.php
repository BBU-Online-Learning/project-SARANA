{{-- resources/views/chat/partials/message-item.blade.php --}}
@php
    $viewerId = $currentUserId ?? auth()->id();
    $isOwn = $message->sender_id === $viewerId;
    $isDeleted = $message->isDeletedForEveryone();
    $isGroup = $room->type !== 'direct';
    $sticker = $message->message_type === 'sticker'
        ? \App\Services\Chat\StickerCatalog::find($message->sticker_id)
        : null;
    $canForward = !$isDeleted && !in_array($message->message_type, ['call', 'system_notification'], true);

    $isRead = $isOwn && !$isDeleted
        && app(\App\Services\Chat\ReadReceiptService::class)->details($message, $room)['read_count'] > 0;


    // Reaction Data
    $reactionCounts = $message->relationLoaded('reactions') ? $message->reactionCounts() : [];
    $currentUserReactions = $message->relationLoaded('reactions')
        ? $message->reactions->where('user_id', $viewerId)->pluck('emoji')->toArray()
        : [];
@endphp

<div class="message-item teams-message {{ $isOwn ? 'is-own' : 'is-other' }} {{ $isDeleted ? 'is-deleted' : '' }}"
    data-message-id="{{ $message->id }}" data-sender-id="{{ $message->sender_id }}"
    data-created-at="{{ $message->created_at->timestamp }}" data-client-id="{{ $message->client_uuid }}"
    data-message-type="{{ $message->message_type }}" data-forwardable="{{ $canForward ? '1' : '0' }}"
    data-message-preview="{{ e(Str::limit($message->previewText(), 120)) }}">

    <a href="{{ $isOwn ? route('profile.edit') : route('users.profile', $message->sender) }}"
        class="message-profile-link" data-user-profile aria-label="View {{ $message->sender->name }}'s profile">
        <x-user-avatar :user="$message->sender" :size="32" class="teams-message-avatar" />
    </a>

    <div class="teams-message-stack">

        <div class="teams-message-meta">
            <strong><a href="{{ $isOwn ? route('profile.edit') : route('users.profile', $message->sender) }}"
                data-user-profile>{{ $message->sender->name }}</a></strong>
            @if($isGroup && $message->sender?->role)
                <small class="chat-sender-role">{{ ucwords(str_replace('_', ' ', $message->sender->role->name)) }}</small>
            @endif
            <span>
                {{ $message->created_at->format('h:i A') }}
                @if ($isOwn && !$isDeleted)
                    <button type="button" class="read-status" data-message-id="{{ $message->id }}"
                        data-is-group="{{ $isGroup ? '1' : '0' }}" data-read="{{ $isRead ? '1' : '0' }}"
                        aria-label="{{ $isRead ? ($isGroup ? 'Read by members. Show details' : 'Read') : 'Sent' }}"
                        @disabled(!$isGroup || !$isRead)>{{ $isRead ? '✓✓' : '✓' }}</button>
                @endif
                @if ($message->edited_at && !$isDeleted)
                    <span class="teams-edited-label">edited</span>
                @endif
            </span>
            @if (! $isDeleted)
                <button type="button" class="chat-mobile-message-actions" data-mobile-message-actions
                    aria-label="Message actions" aria-controls="message-actions-{{ $message->id }}"
                    aria-expanded="false" title="Message actions">
                    <i class="ti ti-dots" aria-hidden="true"></i>
                </button>
            @endif
        </div>

        @if (!$isDeleted)
            <div class="teams-message-toolbar" id="message-actions-{{ $message->id }}">
                <button class="reply-btn" aria-label="Reply to message" data-message-id="{{ $message->id }}"
                    data-message-body="{{ e($message->previewText()) }}">
                    <i class="ti ti-arrow-back-up"></i>
                </button>

                @if ($canForward)
                    <button type="button" class="forward-message-btn" data-message-id="{{ $message->id }}"
                        aria-label="Forward message" title="Forward">
                        <i class="ti ti-arrow-forward-up" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="select-message-btn" data-message-id="{{ $message->id }}"
                        aria-label="Select message" title="Select">
                        <i class="ti ti-circle-check" aria-hidden="true"></i>
                    </button>
                @endif

                @if ($isOwn)
                    <button class="delete-message-btn" data-message-id="{{ $message->id }}"
                        aria-label="Delete message">
                        <i class="ti ti-trash"></i>
                    </button>
                @else
                    <button class="hide-message-btn" data-message-id="{{ $message->id }}" aria-label="Delete for me">
                        <i class="ti ti-trash"></i>
                    </button>
                @endif
                @if ($isOwn && !$message->isForwarded() && !in_array($message->message_type, ['sticker', 'call'], true) && $message->created_at->gt(now()->subMinutes(15)))
                    <button class="edit-message-btn" aria-label="Edit message" data-message-id="{{ $message->id }}"
                        data-message-body="{{ e($message->body) }}">
                        <i class="ti ti-edit"></i>
                    </button>
                @endif
                <button class="reaction-trigger-btn" data-message-id="{{ $message->id }}"
                    aria-label="Add reaction">
                    <i class="ti ti-mood-smile"></i>
                </button>
            </div>
        @endif

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
                        @elseif ($message->replyTo->message_type === 'sticker')
                            <em>Sticker</em>
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
        @if (!$isDeleted && $message->isForwarded())
            <div class="forwarded-message-label">
                <i class="ti ti-arrow-forward-up" aria-hidden="true"></i>
                <span>Forwarded from <strong>{{ $message->forwarded_from_sender_name ?: ($message->forwardedFromSender?->name ?? 'Unknown user') }}</strong></span>
            </div>
        @endif

        {{-- Bubble --}}
        @if ($isDeleted)
            <div class="teams-message-bubble teams-message-deleted">
                <i class="ti ti-ban" aria-hidden="true"></i>
                This message was deleted
            </div>
        @elseif ($message->message_type === 'call')
            <div class="voice-call-history-card">
                <i class="ti {{ str_contains(strtolower($message->body ?? ''), 'video call') ? 'ti-video' : 'ti-phone' }}" aria-hidden="true"></i>
                <span>{{ $message->body ?: 'Voice call' }}</span>
            </div>
        @elseif ($message->message_type === 'sticker')
            @if ($sticker)
                <div class="sticker-message" aria-label="{{ $sticker['name'] }} sticker">
                    <img src="{{ asset($sticker['asset']) }}" alt="{{ $sticker['name'] }} sticker" loading="lazy">
                </div>
            @else
                <div class="teams-message-bubble sticker-unavailable">
                    Sticker unavailable
                </div>
            @endif
        @elseif (filled($message->body))
            <div class="teams-message-bubble" data-message-body="{{ e($message->body) }}">{!! \App\Support\ChatMessageFormatter::linkify($message->body) !!}</div>
        @endif

        {{-- Attachment Rendering --}}
        @if (!$isDeleted && $message->attachments->isNotEmpty())
            <div class="message-attachments">
                @foreach ($message->attachments as $attachment)
                    {{-- Voice note --}}
                    @if ($message->message_type === 'voice')
                        <div class="voice-message-card">
                            <div class="voice-message-icon">
                                <i class="ti ti-microphone"></i>
                            </div>

                            <div class="voice-message-body">
                                <strong>Voice message</strong>
                                <audio controls preload="metadata" src="{{ $attachment->url() }}"></audio>
                            </div>
                        </div>

                    @elseif ($attachment->isAudio())
                        <div class="voice-message-card audio-attachment-card">
                            <div class="voice-message-icon"><i class="ti ti-music"></i></div>
                            <div class="voice-message-body">
                                <strong>{{ $attachment->original_name }}</strong>
                                <span class="attachment-file-size">{{ $attachment->humanSize() }}</span>
                                <audio controls preload="metadata" src="{{ $attachment->url() }}"></audio>
                            </div>
                            <a href="{{ route('chat.attachments.download', $attachment) }}" class="attachment-download-button"
                                aria-label="Download {{ $attachment->original_name }}"><i class="ti ti-download"></i></a>
                        </div>

                    @elseif ($attachment->isVideo())
                        <div class="video-attachment-card">
                            <video controls preload="metadata" playsinline src="{{ $attachment->url() }}"
                                aria-label="{{ $attachment->original_name }}"></video>
                            <div class="video-attachment-meta">
                                <span><strong>{{ $attachment->original_name }}</strong><small>{{ $attachment->humanSize() }}</small></span>
                                <a href="{{ route('chat.attachments.download', $attachment) }}" class="attachment-download-button"
                                    aria-label="Download {{ $attachment->original_name }}"><i class="ti ti-download"></i></a>
                            </div>
                        </div>

                        {{-- Image --}}
                    @elseif ($attachment->isImage())
                        <img src="{{ $attachment->thumbUrl() }}" class="message-attachment-thumb"
                            data-attachment-id="{{ $attachment->id }}" data-full-src="{{ $attachment->url() }}"
                            data-download-src="{{ route('chat.attachments.download', $attachment) }}"
                            data-filename="{{ $attachment->original_name }}" alt="{{ $attachment->original_name }}"
                            loading="lazy" role="button" tabindex="0">

                        {{-- Other files --}}
                    @else
                        <a href="{{ route('chat.attachments.download', $attachment) }}" class="attachment-file-card">
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


    </div>
</div>
