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
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="{{ asset('css/teamstyle.css') }}" rel="stylesheet">
    <link href="{{ asset('css/workspace.css') }}" rel="stylesheet">
    @yield('styles')
</head>
<body class="application-shell @yield('bodyClass')">
    <a href="#main-content" class="workspace-skip-link">Skip to content</a>
    <div class="wrapper">
        <aside class="sidenav-menu" id="workspace-navigation" aria-label="Main navigation">
            <a href="{{ route('home') }}" class="workspace-brand">{{ config('app.name') }}</a>
            <button type="button" class="btn btn-outline-secondary workspace-mobile-only m-3" data-shell-close>Close menu</button>
            @include('layouts.navigation')
        </aside>
        <button type="button" class="workspace-backdrop" data-shell-close aria-label="Close navigation" hidden></button>
        <header class="app-topbar">
            <div class="page-container topbar-menu">
                <button type="button" class="btn btn-outline-secondary workspace-mobile-only" data-shell-toggle aria-controls="workspace-navigation" aria-expanded="false">Menu</button>
                <span class="fw-semibold">{{ ucwords(str_replace('_', ' ', auth()->user()->role->name)) }} workspace</span>
                <a href="{{ route('profile.edit') }}" class="workspace-account-link">{{ auth()->user()->name }}</a>
            </div>
        </header>
        <main class="page-content" id="main-content" tabindex="-1">
            <div class="workspace-flash">@include('layouts.flash_message')</div>
            @yield('content')
        </main>
    </div>
    <script src="{{ asset('backend/assets/js/vendor.min.js') }}"></script>
    <script src="{{ asset('backend/assets/js/app.js') }}"></script>
    <script src="{{ asset('js/workspace.js') }}" defer></script>
    <script>const csrfToken = document.querySelector('meta[name="csrf-token"]').content;</script>
    @yield('scripts')
</body>
</html>
