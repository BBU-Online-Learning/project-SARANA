        <aside class="sidenav-menu" id="workspace-navigation" aria-label="Main navigation">
            <a href="{{ route('home') }}" class="workspace-brand" aria-label="{{ config('app.name') }} home">
                <img src="{{ asset('images/branding/bbu-mark.png') }}" class="workspace-brand-logo" alt="">
                <span class="visually-hidden">{{ config('app.name') }}</span>
            </a>
            <div class="workspace-sidebar-context">
                <div class="workspace-sidebar-context-copy">
                    <p class="workspace-context-label">Your workspace</p>
                    @include('layouts.partials.identity', ['sidebarIdentity' => true])
                </div>
                <button type="button" class="workspace-sidebar-close" data-shell-collapse aria-controls="workspace-navigation" aria-expanded="true" aria-label="Close navigation" title="Close navigation">
                    <i class="ti ti-layout-sidebar-left-collapse" aria-hidden="true"></i>
                    <span class="visually-hidden" data-shell-collapse-label>Close navigation</span>
                </button>
            </div>
            @include('layouts.navigation')
        </aside>
