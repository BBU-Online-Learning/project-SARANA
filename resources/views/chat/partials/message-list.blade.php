{{-- resources/views/chat/partials/message-list.blade.php --}}
<div class="messages-container teams-message-viewport" aria-live="polite">
    @if($messages->isEmpty())
        <div class="chat-conversation-start" data-empty-conversation>
            <i class="ti ti-messages" aria-hidden="true"></i>
            <h2>{{ $room->type === 'group' ? 'Start the discussion' : 'Say hello' }}</h2>
            <p>{{ $room->type === 'group' ? 'Share a question or an idea with your group.' : 'Send the first message to start your conversation.' }}</p>
        </div>
    @endif
    @include('chat.partials.message-items', [
        'messages' => $messages,
        'room' => $room
    ])
</div>
