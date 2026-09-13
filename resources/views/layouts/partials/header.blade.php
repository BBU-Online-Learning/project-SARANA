        <header class="app-topbar">
            <div class="page-container topbar-menu">
                <button type="button" class="btn btn-outline-secondary workspace-mobile-only" data-shell-toggle aria-controls="workspace-navigation" aria-expanded="false">Menu</button>
                <div class="workspace-header-context">
                    <span class="fw-semibold">@yield('title', 'Workspace')</span>
                    @include('layouts.partials.identity')
                </div>
                <a href="{{ route('profile.edit') }}" class="workspace-account-link d-flex align-items-center gap-2">
                    <x-user-avatar :user="auth()->user()" :size="34" /> <span>{{ auth()->user()->name }}</span>
                </a>
            </div>
        </header>
