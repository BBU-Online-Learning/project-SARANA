        <aside class="sidenav-menu" id="workspace-navigation" aria-label="Main navigation">
            <a href="{{ route('home') }}" class="workspace-brand">{{ config('app.name') }}</a>
            <button type="button" class="btn btn-outline-secondary workspace-mobile-only m-3" data-shell-close>Close menu</button>
            <div class="workspace-sidebar-context">
                @include('layouts.partials.identity')
                <p class="text-muted small mb-0">Your workspace</p>
            </div>
            @include('layouts.navigation')
        </aside>
