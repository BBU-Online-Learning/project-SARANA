{{-- resources/views/chat/partials/message-list.blade.php --}}
<div class="messages-container teams-message-viewport" aria-live="polite">
    @include('chat.partials.message-items', [
        'messages' => $messages,
        'room' => $room
    ])
</div>