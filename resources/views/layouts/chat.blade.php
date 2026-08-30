<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Chat</title>

    {{-- Keep the same assets your chat page needs --}}
    <link href="{{ asset('backend/assets/css/vendor.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('backend/assets/css/app.min.css') }}" rel="stylesheet" type="text/css" id="app-style" />
    <link href="{{ asset('backend/assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Chat styling --}}
    <link href="{{ asset('css/teamstyle.css') }}" rel="stylesheet">

    @yield('styles')
</head>
<body class="@yield('bodyClass')">
    {{-- 
        This layout is for teacher/student chat pages.
        It does NOT show the admin left sidebar or top bar.
    --}}
    @yield('content')

    <script src="{{ asset('backend/assets/js/vendor.min.js') }}"></script>
    <script src="{{ asset('backend/assets/js/app.js') }}"></script>

    {{-- Global CSRF token for fetch/axios --}}
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    </script>

    @yield('scripts')
</body>
</html>