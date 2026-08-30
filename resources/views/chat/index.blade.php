{{-- resources/views/chat/index.blade.php --}}
@php
    // Pick the layout once:
    // admin -> normal dashboard shell
    // teacher/student -> clean chat shell
    $roleName = strtolower(auth()->user()->role->name ?? '');
    $layout = $roleName === 'admin' ? 'layouts.app' : 'layouts.chat';
@endphp

@extends($layout)

@section('bodyClass')
    chat-page {{ $roleName === 'admin' ? 'admin-chat' : 'student-chat' }}
@endsection

@section('hideFooter')
@endsection
@section('styles')
    <link rel="stylesheet" href="{{ asset('css/teamstyle.css') }}">
@endsection

@section('content')
    <div class="teams-chat-page">
        <div class="teams-chat-shell">
            <aside class="chat-sidebar" aria-label="Main navigation">
                @include('chat.partials.sidebar')
            </aside>

            <aside class="conversation-list" aria-label="Conversation list">
                @include('chat.partials.conversation-list')
            </aside>

            <main class="chat-room-area" aria-label="Active chat">
                <div id="chat-room-container" class="h-100">
                    @include('chat.partials.empty-chat')
                </div>
            </main>
        </div>
    </div>

    @include('chat.partials.create-chat-modal')
    @include('chat.partials.image-preview-modal')
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <script>
        window.chat = {
            activeRoomId: null,
            currentUserName: @json(auth()->user()->name),
            currentUserInitial: @json(strtoupper(substr(auth()->user()->name, 0, 1))),
            currentUserId: @json(auth()->id()),
            allowedReactions: @json(config('chat.allowed_reactions')),
            nextCursor: null,
            loadingOlderMessages: false,
            replyingToMessageId: null,
            replyingToMessageText: null,
            voiceDraftFile: null,
        };

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

                window.chat.channel.whisper('typing', {
                    userId: window.chat.currentUserId,
                    userName: window.chat.currentUserName
                });
            }, 700);
        }
    </script>

    <script src="{{ asset('js/chat/chat.js') }}"></script>
    <script src="{{ asset('js/chat/attachments.js') }}"></script>
    <script src="{{ asset('js/chat/voice.js') }}"></script>
    <script src="{{ asset('js/chat/image-preview.js') }}"></script>
    <script src="{{ asset('js/chat/messages.js') }}"></script>
    <script src="{{ asset('js/chat/search.js') }}"></script>
    <script src="{{ asset('js/chat/create-chat.js') }}"></script>
@endsection
