@php($shellPreferences = \App\Models\AppSetting::forUser(auth()->user()))
<!DOCTYPE html>
<html lang="en" class="{{ $shellPreferences['larger_text'] ? 'settings-larger-text' : '' }} {{ $shellPreferences['reduce_motion'] ? 'settings-reduce-motion' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Workspace') | {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/branding/favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/branding/bbu-mark.png') }}">
    <script src="{{ asset('backend/assets/js/config.js') }}"></script>
    <link href="{{ asset('backend/assets/css/vendor.min.css') }}" rel="stylesheet">
    <link href="{{ asset('backend/assets/css/app.min.css') }}" rel="stylesheet" id="app-style">
    <link href="{{ asset('backend/assets/css/icons.min.css') }}" rel="stylesheet">
    <script>
        window.reverbRuntimeConfig = Object.freeze({
            localHost: @json(config('reverb.browser.local_host')),
            localPort: @json(config('reverb.browser.local_port')),
            publicHost: @json(config('reverb.browser.public_host')),
            publicPort: @json(config('reverb.browser.public_port')),
            publicScheme: @json(config('reverb.browser.public_scheme')),
        });
    </script>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="{{ asset('css/teamstyle.css') }}" rel="stylesheet">
    <link href="{{ asset('css/workspace.css') }}" rel="stylesheet">
    <link href="{{ asset('css/voice-call.css') }}" rel="stylesheet">
    @yield('styles')
    @if(in_array(auth()->user()->role->name, ['teacher', 'student'], true))
        <link href="{{ asset('css/quiz.css') }}" rel="stylesheet">
    @endif
    <link href="{{ asset('css/learning-workspace.css') }}" rel="stylesheet">
    <link href="{{ asset('css/brand.css') }}" rel="stylesheet">
    <link href="{{ asset('css/settings.css') }}" rel="stylesheet">
    <link href="{{ asset('css/mobile-workspace.css') }}" rel="stylesheet" data-workspace-mobile-style>
    <link href="{{ asset('css/touch-zoom.css') }}" rel="stylesheet">
    @yield('inertiaHead')
</head>
<body class="application-shell learning-workspace @yield('bodyClass')" data-workspace-role="{{ auth()->user()->role->name }}">
    <a href="#main-content" class="workspace-skip-link">Skip to content</a>
    <div class="wrapper">
        @include('layouts.partials.sidebar')
        <button type="button" class="workspace-backdrop" data-shell-close aria-label="Close navigation" hidden></button>
        @include('layouts.partials.header')
        <div id="workspace-navigation-status" class="visually-hidden" role="status" aria-live="polite"></div>
        <main class="page-content" id="main-content" tabindex="-1">
            @include('layouts.flash_message')
            @yield('content')
        </main>
        @include('layouts.partials.mobile-navigation')
    </div>
    <x-confirmation-dialog />
    @include('chat.partials.voice-call-overlay')
    <script src="{{ asset('backend/assets/js/vendor.min.js') }}"></script>
    <script src="{{ asset('backend/assets/js/app.js') }}"></script>
    <script src="{{ asset('js/notifications.js') }}"></script>
    <script>window.appPreferences = Object.freeze(@json($shellPreferences));</script>
    <script src="{{ asset('js/notification-center.js') }}" data-feed-url="{{ route('notifications.index') }}"
        data-user-id="{{ auth()->id() }}" data-read-room-url="{{ route('notifications.read-room') }}"
        data-read-url="{{ route('notifications.read', ['notification' => '__ID__']) }}"
        data-read-all-url="{{ route('notifications.read-all') }}"
        data-clear-all-url="{{ route('notifications.clear-all') }}"></script>
    <script src="{{ asset('js/confirmation-dialog.js') }}"></script>
    <script src="{{ asset('js/workspace.js') }}" defer></script>
    <script>const csrfToken = document.querySelector('meta[name="csrf-token"]').content;</script>
    <script>
        window.voiceCallConfig = {
            userId: @json(auth()->id()),
            userName: @json(auth()->user()->name),
            currentUrl: @json(route('chat.calls.current')),
            callBaseUrl: @json(url('/chat/calls')),
            chatUrl: @json(route('chat.index')),
            iceUrl: @json(route('chat.calls.ice')),
            debug: @json(config('app.debug')),
        };
    </script>
    <script src="{{ asset('js/chat/call-media.js') }}"></script>
    <script src="{{ asset('js/chat/call-ui.js') }}"></script>
    <script src="{{ asset('js/chat/call-sounds.js') }}"></script>
    <script src="{{ asset('js/chat/voice-call.js') }}"></script>
    <script src="{{ asset('js/chat/message-sound.js') }}" data-user-id="{{ auth()->id() }}"
        data-sound-url="{{ asset('sound/notification.wav') }}"
        data-chat-url="{{ route('chat.index') }}"></script>
    @yield('scripts')
</body>
</html>
