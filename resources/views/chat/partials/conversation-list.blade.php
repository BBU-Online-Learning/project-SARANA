{{-- resources/views/chat/partials/conversation-list.blade.php --}}
<div class="teams-conversation-panel">
    <div class="teams-panel-header">
        <div>
            <h1>Chat</h1>
            <p>Recent conversations</p>
        </div>

        <button type="button" class="teams-icon-button teams-new-chat-button" data-bs-toggle="modal"
            data-bs-target="#createChatModal" aria-label="New chat">
            <i class="ti ti-edit"></i>
        </button>
    </div>
    {{-- inpute search  --}}
    <div class="teams-search-box" role="search">
        <i class="ti ti-search" aria-hidden="true"></i>
        <input type="search" id="chat-search-input" placeholder="Search chats" aria-label="Search chats"
            autocomplete="off">
    </div>
    {{-- result search --}}
    <div id="conversation-search-empty" class="teams-empty-panel" style="display:none;">
        <i class="ti ti-search-off"></i>
        <p>No conversations found</p>
    </div>

    <div class="teams-room-scroll">
        @forelse($rooms as $room)
            @include('chat.partials.conversation-item')
        @empty
            <div class="teams-empty-panel">
                <i class="ti ti-message-circle"></i>
                <p>No conversations</p>
            </div>
        @endforelse
    </div>
</div>
