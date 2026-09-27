{{-- resources/views/chat/partials/chat-area.blade.php --}}
@php
    $isDirect = $room->type === 'direct';
    $otherUser = $isDirect ? $room->members->where('id', '!=', auth()->id())->first() : null;
    $displayName = $isDirect ? $otherUser?->name ?? 'Direct chat' : $room->name ?? 'Group chat'; //$otherUser?->name Uses PHP 8 null-safe operator: so it won't crash if $otherUser is null.
    $initial = strtoupper(substr($displayName, 0, 1));
    $avatar = $room->avatarUrl();
    $memberCount = $room->members->count();
@endphp

<div class="teams-chat-area" data-conversation-type="{{ $room->type }}">
    <div class="attachment-drop-overlay" data-attachment-drop-overlay hidden aria-hidden="true">
        <div><i class="ti ti-cloud-upload" aria-hidden="true"></i><strong>Drop files to attach</strong></div>
    </div>
    <header class="teams-chat-header">
        <button type="button" class="teams-icon-button chat-mobile-back" data-chat-list
            aria-label="Back to conversations" aria-controls="chat-conversations" title="Back to conversations">
            <i class="ti ti-arrow-left" aria-hidden="true"></i>
        </button>
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
                <button type="button" class="teams-icon-button chat-desktop-call-action" data-start-voice-call
                    data-call-type="audio" title="Start an audio call"
                    data-start-url="{{ route('chat.calls.store', $room) }}"
                    data-peer-name="{{ $otherUser->name }}"
                    data-peer-avatar="{{ $otherUser->profileUrl() }}"
                    aria-label="Start a voice call with {{ $otherUser->name }}">
                    <i class="ti ti-phone" aria-hidden="true"></i>
                </button>
                <button type="button" class="teams-icon-button chat-desktop-call-action" data-start-voice-call data-call-type="video"
                    data-start-url="{{ route('chat.calls.store', $room) }}"
                    data-peer-name="{{ $otherUser->name }}"
                    data-peer-avatar="{{ $otherUser->profileUrl() }}"
                    title="Start a video call" aria-label="Start a video call with {{ $otherUser->name }}">
                    <i class="ti ti-video" aria-hidden="true"></i>
                </button>
                <details class="chat-mobile-call-menu">
                    <summary class="teams-icon-button" aria-label="Call {{ $otherUser->name }}" title="Call {{ $otherUser->name }}">
                        <i class="ti ti-phone" aria-hidden="true"></i>
                    </summary>
                    <div class="chat-mobile-call-options">
                        <button type="button" data-start-voice-call data-call-type="audio"
                            data-start-url="{{ route('chat.calls.store', $room) }}"
                            data-peer-name="{{ $otherUser->name }}" data-peer-avatar="{{ $otherUser->profileUrl() }}">
                            <i class="ti ti-phone" aria-hidden="true"></i> Voice call
                        </button>
                        <button type="button" data-start-voice-call data-call-type="video"
                            data-start-url="{{ route('chat.calls.store', $room) }}"
                            data-peer-name="{{ $otherUser->name }}" data-peer-avatar="{{ $otherUser->profileUrl() }}">
                            <i class="ti ti-video" aria-hidden="true"></i> Video call
                        </button>
                    </div>
                </details>
            @endif
            @unless($isDirect)
                <button type="button" class="teams-icon-button" data-group-info aria-label="Group information" aria-haspopup="dialog" aria-controls="group-info-drawer" title="Group information">
                    <i class="ti ti-info-circle" aria-hidden="true"></i>
                </button>
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
            <span id="room-search-count" class="room-search-count" role="status" aria-live="polite"></span>
            <button type="button" id="room-search-prev" class="teams-icon-button" aria-label="Previous match">
                <i class="ti ti-chevron-up"></i>
            </button>
            <button type="button" id="room-search-next" class="teams-icon-button" aria-label="Next match">
                <i class="ti ti-chevron-down"></i>
            </button>
            <button type="button" id="room-search-close" class="teams-icon-button" aria-label="Close search">
                <i class="ti ti-x"></i>
            </button>
            <div id="room-search-results" class="room-search-results" aria-label="Matching messages" hidden></div>
        </div>
    </header>
    @include('chat.partials.message-list')

    <button type="button" id="jump-to-latest-btn" class="jump-to-latest-btn" aria-label="Jump to latest messages" hidden>
        <i class="ti ti-arrow-down" aria-hidden="true"></i>
        <span data-new-message-label>New messages</span>
    </button>


    <div class="message-selection-bar" data-message-selection-bar hidden>
        <button type="button" class="teams-icon-button" data-selection-cancel aria-label="Cancel message selection">
            <i class="ti ti-x" aria-hidden="true"></i>
        </button>
        <strong data-selection-count>1 selected</strong>
        <button type="button" class="message-selection-forward" data-selection-forward>
            <i class="ti ti-arrow-forward-up" aria-hidden="true"></i> Forward
        </button>
    </div>

    <div class="teams-composer-wrap">

        @php
            $stickerPacks = collect(\App\Services\Chat\StickerCatalog::all())->groupBy('pack', preserveKeys: true);
        @endphp

        <div id="sticker-picker" class="sticker-picker" role="dialog" aria-modal="false"
            aria-labelledby="sticker-picker-title" hidden>
            <div class="sticker-picker-header">
                <strong id="sticker-picker-title">Stickers</strong>
                <button type="button" id="sticker-picker-close" class="teams-icon-button"
                    aria-label="Close sticker picker">
                    <i class="ti ti-x" aria-hidden="true"></i>
                </button>
            </div>
            <div class="sticker-pack-tabs" role="tablist" aria-label="Sticker packs">
                @foreach ($stickerPacks as $packName => $stickers)
                    @php $packId = \Illuminate\Support\Str::slug($packName); @endphp
                    <button type="button" class="sticker-pack-tab" role="tab"
                        id="sticker-pack-tab-{{ $packId }}" data-sticker-pack-tab="{{ $packId }}"
                        aria-controls="sticker-pack-{{ $packId }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                        tabindex="{{ $loop->first ? '0' : '-1' }}">{{ $packName }}</button>
                @endforeach
            </div>
            @foreach ($stickerPacks as $packName => $stickers)
                @php $packId = \Illuminate\Support\Str::slug($packName); @endphp
                <div id="sticker-pack-{{ $packId }}" class="sticker-picker-grid" role="tabpanel"
                    aria-labelledby="sticker-pack-tab-{{ $packId }}" data-sticker-pack-panel="{{ $packId }}"
                    @if (! $loop->first) hidden @endif>
                    @foreach ($stickers as $stickerId => $sticker)
                        <button type="button" class="sticker-option" data-sticker-id="{{ $stickerId }}"
                            data-sticker-name="{{ $sticker['name'] }}" data-sticker-pack="{{ $sticker['pack'] }}"
                            data-sticker-url="{{ asset($sticker['asset']) }}"
                            aria-label="Send {{ $sticker['name'] }} sticker">
                            <img src="{{ asset($sticker['asset']) }}" alt="" loading="lazy">
                            <span>{{ $sticker['name'] }}</span>
                        </button>
                    @endforeach
                </div>
            @endforeach
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
                    <button type="button" class="teams-icon-button" data-emoji-toggle aria-label="Choose emoji" aria-expanded="false" aria-controls="chat-emoji-picker" title="Emoji">
                        <i class="ti ti-mood-smile" aria-hidden="true"></i>
                    </button>
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
                <textarea name="body" rows="1" placeholder="Type a message…" autocomplete="off"
                    aria-label="Message input" aria-describedby="message-composer-help"></textarea>

                <button type="submit" class="teams-send-button" aria-label="Send">
                    <i class="ti ti-send" aria-hidden="true"></i>
                </button>
            </div>
        </form>
        <div id="chat-emoji-picker" class="chat-emoji-picker" role="group" aria-label="Choose emoji" hidden>
            @foreach(['😀', '😊', '👏', '👍', '❤️', '🎉', '🙏', '🤔', '✅', '📚', '💡', '👋'] as $emoji)
                <button type="button" data-chat-emoji="{{ $emoji }}" aria-label="Insert {{ $emoji }}">{{ $emoji }}</button>
            @endforeach
        </div>
        <p id="message-composer-help" class="composer-help">Enter to send · Shift + Enter for a new line. On mobile, use the send button.</p>
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
    @unless($isDirect)
        @include('chat.partials.group-info-drawer')
    @endunless
</div>
