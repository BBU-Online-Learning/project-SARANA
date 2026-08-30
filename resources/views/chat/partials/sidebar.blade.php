{{-- resources/views/chat/partials/sidebar.blade.php --}}
<div class="teams-sidebar-inner">
    <div class="teams-sidebar-top">
        <div class="teams-app-mark" aria-hidden="true">BBU</div>

        <nav class="teams-nav-list">
            <a href="#" class="teams-nav-link active" aria-label="Chats">
                <i class="ti ti-message-circle"></i>
            </a>

            <a href="#" class="teams-nav-link" aria-label="Teams">
                <i class="ti ti-users-group"></i>
            </a>

            <a href="#" class="teams-nav-link" aria-label="Calls">
                <i class="ti ti-phone"></i>
            </a>

            <a href="#" class="teams-nav-link" aria-label="Calendar">
                <i class="ti ti-calendar"></i>
            </a>
        </nav>
    </div>

    <div class="teams-sidebar-bottom">
        <div class="teams-profile-avatar" title="{{ auth()->user()->name }}">
            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
        </div>
    </div>
</div>