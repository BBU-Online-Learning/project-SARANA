{{-- resources/views/chat/partials/chat-area.blade.php --}}
@php
    $isDirect = $room->type === 'direct';
    $otherUser = $isDirect ? $room->members->where('id', '!=', auth()->id())->first() : null;
    $displayName = $isDirect ? $otherUser?->name ?? 'Direct chat' : $room->name ?? 'Group chat'; //$otherUser?->name Uses PHP 8 null-safe operator: so it won't crash if $otherUser is null.
    $initial = strtoupper(substr($displayName, 0, 1));
    $avatar = $room->avatarUrl();
    $memberCount = $room->members->count();
@endphp

<div class="teams-chat-area">
    <div class="attachment-drop-overlay" data-attachment-drop-overlay hidden aria-hidden="true">
        <div><i class="ti ti-cloud-upload" aria-hidden="true"></i><strong>Drop files to attach</strong></div>
    </div>
    <header class="teams-chat-header">
        <div class="teams-chat-title-group">
            @if ($isDirect && $otherUser)
                <a href="{{ route('users.profile', $otherUser) }}" class="chat-profile-avatar-link" data-user-profile
                    aria-label="View {{ $otherUser->name }}'s profile">
                    <x-user-avatar :user="$otherUser" :size="44" class="teams-room-avatar teams-room-avatar-lg">
                    <span class="presence-dot" data-user-id="{{ $otherUser?->id }}"></span>
                    </x-user-avatar>
                </a>
            @else
                <div class="teams-room-avatar teams-room-avatar-lg">
                    @if ($avatar)
                        <img src="{{ $avatar }}" alt="" loading="lazy">
                    @else
                        {{ $initial }}
                    @endif

                </div>
            @endif

            <div>
                <h1>@if ($isDirect && $otherUser)<a href="{{ route('users.profile', $otherUser) }}"
                    data-user-profile>{{ $displayName }}</a>@else{{ $displayName }}@endif</h1>
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
            @if($isDirect && $otherUser)
                <button type="button" class="teams-icon-button" data-start-voice-call
                    data-call-type="audio" title="Start an audio call"
                    data-start-url="{{ route('chat.calls.store', $room) }}"
                    data-peer-name="{{ $otherUser->name }}"
                    data-peer-avatar="{{ $otherUser->profileUrl() }}"
                    aria-label="Start a voice call with {{ $otherUser->name }}">
                    <i class="ti ti-phone" aria-hidden="true"></i>
                </button>
                <button type="button" class="teams-icon-button" data-start-voice-call data-call-type="video"
                    data-start-url="{{ route('chat.calls.store', $room) }}"
                    data-peer-name="{{ $otherUser->name }}"
                    data-peer-avatar="{{ $otherUser->profileUrl() }}"
                    title="Start a video call" aria-label="Start a video call with {{ $otherUser->name }}">
                    <i class="ti ti-video" aria-hidden="true"></i>
                </button>
            @endif
            @unless($isDirect)
                <a href="{{ route('chat.groups.show', $room) }}" class="btn btn-sm btn-outline-secondary">Members</a>
            @endunless
            <button type="button" id="open-room-search-btn" class="teams-icon-button" aria-label="Search in chat"
                aria-controls="room-search-bar" aria-expanded="false">
                <i class="ti ti-search"></i>
            </button>

        </div>
        {{-- IN-CHAT MESSAGE SEARCH BAR (Telegram-style) --}}
        <div id="room-search-bar" class="teams-room-search-bar" role="search" aria-label="Search messages" hidden>
            <i class="ti ti-search" aria-hidden="true"></i>
            <input type="search" id="room-search-input" placeholder="Search in this chat" aria-label="Search messages"
                autocomplete="off">
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

    <button type="button" id="jump-to-latest-btn" class="jump-to-latest-btn" aria-label="Jump to latest messages" hidden>
        <i class="ti ti-arrow-down" aria-hidden="true"></i>
        <span data-new-message-label>New messages</span>
    </button>


    <div class="teams-composer-wrap">

        <div id="sticker-picker" class="sticker-picker" role="dialog" aria-modal="false"
            aria-labelledby="sticker-picker-title" hidden>
            <div class="sticker-picker-header">
                <div>
                    <strong id="sticker-picker-title">Stickers</strong>
                    <span>Study Buddies</span>
                </div>
                <button type="button" id="sticker-picker-close" class="teams-icon-button"
                    aria-label="Close sticker picker">
                    <i class="ti ti-x" aria-hidden="true"></i>
                </button>
            </div>
            <div class="sticker-picker-grid" role="group" aria-label="Study Buddies stickers">
                @foreach (\App\Services\Chat\StickerCatalog::all() as $stickerId => $sticker)
                    <button type="button" class="sticker-option" data-sticker-id="{{ $stickerId }}"
                        aria-label="Send {{ $sticker['name'] }} sticker">
                        <img src="{{ asset($sticker['asset']) }}" alt="" loading="lazy">
                        <span>{{ $sticker['name'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>

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

                <span id="attachment-limit-summary" class="attachment-limit-summary" aria-live="polite"></span>

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
                        accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.zip,.ogg,.oga,.webm,.mp3,.wav,.m4a,.aac,.mpeg,.mpga,.mp4,audio/*,video/mp4,video/webm">

                    {{-- Voice record --}}
                    <button type="button" id="voice-record-btn" class="teams-icon-button"
                        aria-label="Record voice message">
                        <i class="ti ti-microphone"></i>
                    </button>

                    {{-- Stickers --}}
                    <button type="button" id="sticker-picker-btn" class="teams-icon-button"
                        aria-label="Choose a sticker" aria-controls="sticker-picker" aria-expanded="false">
                        <i class="ti ti-sticker" aria-hidden="true"></i>
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
