        @php
            $workspaceHeaderIcon = match (true) {
                request()->routeIs('home') => 'ti-layout-dashboard',
                request()->routeIs('users.*') => 'ti-users',
                request()->routeIs('classes.*') => 'ti-school',
                request()->routeIs('roles.*') => 'ti-shield-check',
                request()->routeIs('chat.*') => 'ti-messages',
                request()->routeIs('profile.*') => 'ti-user-circle',
                request()->routeIs('settings.*') => 'ti-settings',
                default => 'ti-apps',
            };
        @endphp
        <header class="app-topbar">
            <div class="page-container topbar-menu">
                <button type="button" class="workspace-mobile-only workspace-topbar-button" data-shell-toggle aria-controls="workspace-navigation" aria-expanded="false" aria-label="Open navigation" title="Open navigation">
                    <i class="ti ti-menu-2" aria-hidden="true"></i><span class="visually-hidden">Menu</span>
                </button>
                <div class="workspace-header-context">
                    <span class="workspace-header-icon" aria-hidden="true"><i class="ti {{ $workspaceHeaderIcon }}"></i></span>
                    <span class="fw-semibold">@yield('title', 'Workspace')</span>
                    @include('layouts.partials.identity')
                </div>
                <a href="{{ route('profile.edit') }}" class="workspace-account-link d-flex align-items-center gap-2">
                    <x-user-avatar :user="auth()->user()" :size="34" /> <span>{{ auth()->user()->name }}</span>
                </a>
                <div class="notification-center">
                    <button type="button" id="notification-center-toggle" class="notification-center-toggle"
                        aria-label="Notifications" aria-controls="notification-center-panel" aria-expanded="false">
                        <i class="ti ti-bell" aria-hidden="true"></i>
                        <span id="notification-center-count" class="notification-center-count" hidden></span>
                    </button>
                    <section id="notification-center-panel" class="notification-center-panel" aria-label="Notifications" hidden>
                        <div class="notification-center-heading">
                            <h2>Notifications</h2>
                            <div class="notification-center-actions">
                                <button type="button" id="notification-center-read-all">Mark all read</button>
                                <button type="button" id="notification-center-clear-all" title="Remove all notifications">Clear all</button>
                            </div>
                        </div>
                        <div id="notification-center-list" class="notification-center-list" aria-live="polite"></div>
                        <button type="button" id="notification-center-more" class="notification-center-more" hidden>Load more</button>
                    </section>
                </div>
            </div>
        </header>
