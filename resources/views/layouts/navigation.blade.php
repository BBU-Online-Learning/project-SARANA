@php
    $compact = $compact ?? false;
    $links = [
        ['home', 'Dashboard', 'ti-home', request()->routeIs('home')],
        ['classes.index', auth()->user()->can('access-admin') ? 'Class administration' : 'My classes', 'ti-school', request()->routeIs('classes.*')],
        ['chat.index', 'Chats', 'ti-messages', request()->routeIs('chat.*')],
    ];
    if (auth()->user()->can('access-admin')) {
        $links[] = ['users.index', auth()->user()->role->name === 'super_admin' ? 'Account administration' : 'Teachers and students', 'ti-users', request()->routeIs('users.*')];
        $links[] = ['roles.index', 'Fixed roles', 'ti-shield', request()->routeIs('roles.*')];
    }
    $links[] = ['profile.edit', 'My profile', 'ti-user-circle', request()->routeIs('profile.*')];
@endphp
<nav class="{{ $compact ? 'teams-nav-list' : 'workspace-nav' }}" aria-label="Workspace links">
    @foreach($links as [$route, $label, $icon, $active])
        <a href="{{ route($route) }}" class="{{ $compact ? 'teams-nav-link' : 'workspace-nav-link' }} {{ $active ? 'active' : '' }}"
           aria-label="{{ $label }}" title="{{ $label }}" @if($active) aria-current="page" @endif>
            <i class="ti {{ $icon }}" aria-hidden="true"></i><span class="{{ $compact ? 'visually-hidden' : '' }}">{{ $label }}</span>
        </a>
    @endforeach
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="{{ $compact ? 'teams-nav-link' : 'workspace-nav-link' }}" title="Log out" aria-label="Log out">
            <i class="ti ti-logout" aria-hidden="true"></i><span class="{{ $compact ? 'visually-hidden' : '' }}">Log out</span>
        </button>
    </form>
</nav>
