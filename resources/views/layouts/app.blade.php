<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Workspace') | {{ config('app.name') }}</title>
    <link rel="shortcut icon" href="{{ asset('backend/assets/images/favicon.ico') }}">
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
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="{{ asset('css/teamstyle.css') }}" rel="stylesheet">
    <link href="{{ asset('css/workspace.css') }}" rel="stylesheet">
    <link href="{{ asset('css/voice-call.css') }}" rel="stylesheet">
    @yield('styles')
    @if(in_array(auth()->user()->role->name, ['teacher', 'student'], true))
        <link href="{{ asset('css/learning-workspace.css') }}" rel="stylesheet">
    @endif
</head>
<body class="application-shell {{ in_array(auth()->user()->role->name, ['teacher', 'student'], true) ? 'learning-workspace' : '' }} @yield('bodyClass')" data-workspace-role="{{ auth()->user()->role->name }}">
    <a href="#main-content" class="workspace-skip-link">Skip to content</a>
    <div class="wrapper">
        @include('layouts.partials.sidebar')
        <button type="button" class="workspace-backdrop" data-shell-close aria-label="Close navigation" hidden></button>
        @include('layouts.partials.header')
        <main class="page-content" id="main-content" tabindex="-1">
            @include('layouts.flash_message')
            @yield('content')
        </main>
    </div>
    <x-confirmation-dialog />
    @include('chat.partials.voice-call-overlay')
    <script src="{{ asset('backend/assets/js/vendor.min.js') }}"></script>
    <script src="{{ asset('backend/assets/js/app.js') }}"></script>
    <script src="{{ asset('js/notifications.js') }}"></script>
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
    <script src="{{ asset('js/chat/voice-call.js') }}"></script>
    @yield('scripts')
</body>
</html>
