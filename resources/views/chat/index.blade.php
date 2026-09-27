{{-- resources/views/chat/index.blade.php --}}
@extends('layouts.chat')

@section('bodyClass')
    chat-page workspace-chat
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/chat-workspace.css') }}" data-workspace-page-style="chat-workspace">
    <link rel="stylesheet" href="{{ asset('css/chat-read-receipts.css') }}" data-workspace-page-style="chat-read-receipts">
    <link rel="stylesheet" href="{{ asset('css/create-chat.css') }}" data-workspace-page-style="chat-create">
    <link rel="stylesheet" href="{{ asset('css/group-invite.css') }}" data-workspace-page-style="chat-group-invite">
    <link rel="stylesheet" href="{{ asset('css/chat-forward.css') }}" data-workspace-page-style="chat-forward">
@endsection

@section('content')
    <dialog id="chat-read-dialog" aria-labelledby="chat-read-title">
        <div class="chat-read-heading">
            <h2 id="chat-read-title">Read by</h2>
            <button type="button" data-close-read-dialog aria-label="Close read details">×</button>
        </div>
        <ul id="chat-read-list" aria-live="polite"></ul>
    </dialog>
    <div id="chat-load-status" class="alert alert-info chat-load-status" role="status" aria-live="polite" hidden></div>
    <div id="chat-connection-status" class="chat-connection-status" role="status" hidden></div>
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
    @include('chat.partials.forward-message-modal')
@endsection

@section('scripts')
    <script data-workspace-page-script="chat-config">
        window.chat = {
            activeRoomId: null,
            currentUserName: @json(auth()->user()->name),
            currentUserInitial: @json(strtoupper(substr(auth()->user()->name, 0, 1))),
            currentUserId: @json(auth()->id()),
            initialRoomId: @json($initialRoomId),
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
            if (!e.target.matches('#message-form [name="body"]')) {
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

    <script src="{{ asset('js/chat/chat.js') }}" data-workspace-page-script="chat-realtime"></script>
    <script src="{{ asset('js/chat/read-receipts.js') }}" data-workspace-page-script="chat-read-receipts"></script>
    <script src="{{ asset('js/chat/attachments.js') }}" data-workspace-page-script="chat-attachments"></script>
    <script src="{{ asset('js/chat/voice.js') }}" data-workspace-page-script="chat-voice"></script>
    <script src="{{ asset('js/chat/image-preview.js') }}" data-workspace-page-script="chat-image-preview"></script>
    <script src="{{ asset('js/chat/messages.js') }}" data-workspace-page-script="chat-messages"></script>
    <script src="{{ asset('js/chat/stickers.js') }}" data-workspace-page-script="chat-stickers"></script>
    <script src="{{ asset('js/chat/search.js') }}" data-workspace-page-script="chat-search"></script>
    <script src="{{ asset('js/chat/create-chat.js') }}" data-workspace-page-script="chat-create"></script>
    <script src="{{ asset('js/chat/workspace-ui.js') }}" data-workspace-page-script="chat-workspace-ui"></script>
    <script src="{{ asset('js/chat/group-invite.js') }}" data-workspace-page-script="chat-group-invite"></script>
    <script src="{{ asset('js/chat/forward.js') }}" data-workspace-page-script="chat-forward"></script>
@endsection
