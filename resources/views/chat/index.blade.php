{{-- resources/views/chat/index.blade.php --}}
@extends('layouts.chat')

@section('bodyClass')
    chat-page workspace-chat
@endsection

@section('content')
    <link rel="stylesheet" href="{{ asset('css/chat-read-receipts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/create-chat.css') }}">
    <dialog id="chat-read-dialog" aria-labelledby="chat-read-title">
        <div class="chat-read-heading">
            <h2 id="chat-read-title">Read by</h2>
            <button type="button" data-close-read-dialog aria-label="Close read details">×</button>
        </div>
        <ul id="chat-read-list" aria-live="polite"></ul>
    </dialog>
    <div class="chat-mobile-toolbar">
        <button type="button" class="btn btn-sm btn-outline-primary" data-chat-list aria-controls="chat-conversations">Conversations</button>
    </div>
    <div id="chat-load-status" class="alert alert-info chat-load-status" role="status" aria-live="polite" hidden></div>
    <div class="teams-chat-page">
        <div class="teams-chat-shell">
            <aside class="conversation-list" id="chat-conversations" aria-label="Conversation list">
                @include('chat.partials.conversation-list')
            </aside>

            <section class="chat-room-area" aria-label="Active chat">
                <div id="chat-room-container" class="h-100">
                    @include('chat.partials.empty-chat')
                </div>
            </section>
        </div>
    </div>

    @include('chat.partials.create-chat-modal')
    @include('chat.partials.image-preview-modal')
@endsection

@section('scripts')
    <script>
        window.chat = {
            activeRoomId: null,
            currentUserName: @json(auth()->user()->name),
            currentUserInitial: @json(strtoupper(substr(auth()->user()->name, 0, 1))),
            currentUserId: @json(auth()->id()),
            allowedReactions: @json(config('chat.allowed_reactions')),
            stickers: @json(\App\Services\Chat\StickerCatalog::forClient()),
            nextCursor: null,
            loadingOlderMessages: false,
            replyingToMessageId: null,
            replyingToMessageText: null,
            voiceDraftFile: null,
        };
        window.chatAttachmentConfig = Object.freeze({
            maxFiles: @json(config('chat.max_attachments_per_message')),
            maxFileSizeBytes: @json(config('chat.max_attachment_size_kb') * 1024),
            maxTotalSizeBytes: @json(config('chat.max_attachment_total_size_kb') * 1024),
            allowedExtensions: @json(config('chat.allowed_attachment_extensions')),
        });

        document.addEventListener('input', (e) => {
            if (!e.target.matches('#message-form input[name="body"]')) {
                return;
            }

            sendTypingSignal();
        });

        let typingTimer;

        function sendTypingSignal() {
            clearTimeout(typingTimer);

            typingTimer = setTimeout(() => {
                if (!window.chat.activeRoomId || !window.chat.channel) {
                    return;
                }

                if (window.chat.roomType === 'group') {
                    axios.post(`/chat/groups/${window.chat.activeRoomId}/typing`).catch(() => {});
                    return;
                }
                window.chat.channel.whisper('typing', {
                    userId: window.chat.currentUserId,
                    userName: window.chat.currentUserName
                });
            }, 700);
        }
    </script>

    <script src="{{ asset('js/chat/chat.js') }}"></script>
    <script src="{{ asset('js/chat/read-receipts.js') }}"></script>
    <script src="{{ asset('js/chat/attachments.js') }}"></script>
    <script src="{{ asset('js/chat/voice.js') }}"></script>
    <script src="{{ asset('js/chat/image-preview.js') }}"></script>
    <script src="{{ asset('js/chat/messages.js') }}"></script>
    <script src="{{ asset('js/chat/stickers.js') }}"></script>
    <script src="{{ asset('js/chat/search.js') }}"></script>
    <script src="{{ asset('js/chat/create-chat.js') }}"></script>
@endsection
