{{-- resources/views/chat/partials/chat-area.blade.php --}}
@php
    $isDirect = $room->type === 'direct';
    $otherUser = $isDirect ? $room->members->where('id', '!=', auth()->id())->first() : null;
    $displayName = $isDirect ? $otherUser?->name ?? 'Direct chat' : $room->name ?? 'Group chat'; //$otherUser?->name Uses PHP 8 null-safe operator: so it won't crash if $otherUser is null.
    $initial = strtoupper(substr($displayName, 0, 1));
    $profile = $otherUser?->profile;
    $avatar = $room->avatar;
    $memberCount = $room->members->count();
@endphp

<div class="teams-chat-area">
    <header class="teams-chat-header">
        <div class="teams-chat-title-group">
            <div class="teams-room-avatar teams-room-avatar-lg">

                @if ($isDirect)
                    @if ($profile)
                        <img src="{{ $profile }}" width="40" class="rounded-circle me-lg-2 d-flex"
                            alt="user-image">
                    @else
                        {{ $initial }}
                    @endif
                    <span class="presence-dot" data-user-id="{{ $otherUser?->id }}"></span>
                @else
                    @if ($avatar)
                        <img src="{{ $avatar }}" width="40" class="rounded-circle me-lg-2 d-flex"
                            alt="group-image">
                    @else
                        {{ $initial }}
                    @endif

                @endif
            </div>

            <div>
                <h1>{{ $displayName }}</h1>
                <p>
                    @if ($isDirect)
                        <span class="user-status" data-user-id="{{ $otherUser?->id }}"
                            data-last-seen="{{ $otherUser?->last_seen_at?->toIso8601String() }}">

                            {{ $otherUser?->last_seen_at ? 'Last seen ' . $otherUser->last_seen_at->diffForHumans() : 'Offline' }}

                        </span>
                    @else
                        {{ $memberCount }}
                        {{ $memberCount === 1 ? 'member' : 'members' }}
                        <span aria-hidden="true">.</span>
                        <span>Group chat</span>
                    @endif
                </p>
            </div>
        </div>

        <div class="teams-chat-actions">
            @unless($isDirect)
                <a href="{{ route('chat.groups.show', $room) }}" class="btn btn-sm btn-outline-secondary">Members</a>
            @endunless
            <button type="button" id="open-room-search-btn" class="teams-icon-button" aria-label="Search in chat">
                <i class="ti ti-search"></i>
            </button>

        </div>
        {{-- IN-CHAT MESSAGE SEARCH BAR (Telegram-style) --}}
        <div id="room-search-bar" class="teams-room-search-bar" style="display:none;">
            <i class="ti ti-search" aria-hidden="true"></i>
            <input type="text" id="room-search-input" placeholder="Search in this chat" autocomplete="off">
            <span id="room-search-count" class="room-search-count"></span>
            <button type="button" id="room-search-prev" class="teams-icon-button" aria-label="Previous match">
                <i class="ti ti-chevron-up"></i>
            </button>
            <button type="button" id="room-search-next" class="teams-icon-button" aria-label="Next match">
                <i class="ti ti-chevron-down"></i>
            </button>
            <button type="button" id="room-search-close" class="teams-icon-button" aria-label="Close search">
                <i class="ti ti-x"></i>
            </button>
        </div>
    </header>
    @include('chat.partials.message-list')


    <div class="teams-composer-wrap">

        {{-- TYPING INDICATOR --}}
        <div id="typing-indicator" class="teams-typing-indicator"></div>

        {{-- EDIT PREVIEW BAR (hidden by default) --}}
        <div id="edit-preview" class="composer-context-bar" style="display: none;">
            <div class="composer-context-icon">
                <i class="ti ti-edit" aria-hidden="true"></i>
            </div>
            <div class="composer-context-body">
                <span class="composer-context-label">Edit Message</span>
                <span class="composer-context-text" id="edit-preview-text"></span>
            </div>
            <button id="cancel-edit-btn" class="composer-context-cancel" type="button" aria-label="Cancel editing">
                <i class="ti ti-x" aria-hidden="true"></i>
            </button>
        </div>

        {{-- REPLY PREVIEW BAR (hidden by default) --}}
        <div id="reply-preview" class="composer-context-bar" style="display: none;">
            <div class="composer-context-icon">
                <i class="ti ti-arrow-back-up" aria-hidden="true"></i>
            </div>
            <div class="composer-context-body">
                <span class="composer-context-label">Reply</span>
                <span class="composer-context-text" id="reply-preview-text"></span>
            </div>
            <button id="cancel-reply-btn" class="composer-context-cancel" type="button" aria-label="Cancel reply">
                <i class="ti ti-x" aria-hidden="true"></i>
            </button>
        </div>
        {{-- ATTACHMENT PREVIEW BAR (hidden by default) --}}
        <div id="attachment-preview" class="composer-context-bar attachment-preview-bar" style="display:none;">

            <div class="composer-context-icon">
                <i class="ti ti-paperclip" aria-hidden="true"></i>
            </div>

            <div class="composer-context-body">

                <span class="composer-context-label">
                    Attachments
                </span>

                <div id="attachment-preview-list" class="attachment-preview-list">
                </div>

            </div>

            <button id="cancel-attachments-btn" class="composer-context-cancel" type="button"
                aria-label="Remove attachments">

                <i class="ti ti-x"></i>

            </button>

        </div>
        {{-- COMPOSER INPUT --}}
        <form id="message-form" autocomplete="off" enctype="multipart/form-data">
            <div class="teams-composer">
                <div class="teams-composer-tools">
                    {{-- Attach files --}}
                    <button type="button" id="attach-file-btn" class="teams-icon-button" aria-label="Attach file">
                        <i class="ti ti-paperclip"></i>
                    </button>

                    <input type="file" id="attachment-input" hidden multiple
                        accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.zip,.ogg,.oga,.webm,.mp3,.wav,.m4a,.aac,.mpeg,.mpga,.mp4,audio/*">

                    {{-- Voice record --}}
                    <button type="button" id="voice-record-btn" class="teams-icon-button"
                        aria-label="Record voice message">
                        <i class="ti ti-microphone"></i>
                    </button>

                </div>

                {{-- Text composer --}}
                <input type="text" name="body" placeholder="Type a message…" autocomplete="off"
                    aria-label="Message input" />

                <button type="submit" class="teams-send-button" aria-label="Send">
                    <i class="ti ti-send" aria-hidden="true"></i>
                </button>
            </div>
        </form>
        {{-- Telegram-style voice overlay --}}
        <div id="voice-overlay" class="voice-compose-overlay" hidden>
            <button type="button" id="voice-cancel-btn" class="voice-action-btn voice-cancel-btn">
                Cancel
            </button>

            <div class="voice-compose-center">
                <div id="voice-record-status" class="voice-record-status">Recording voice message...</div>
                <audio id="voice-preview-audio" controls preload="metadata" hidden></audio>
            </div>

            <button type="button" id="voice-send-btn" class="voice-action-btn voice-send-btn"
                aria-label="Send voice message">
                <i class="ti ti-send"></i>
            </button>
        </div>
    </div>
</div>
