@php
    $compact = $compact ?? false;
    $links = [
        ['home', 'Dashboard', 'ti-home', request()->routeIs('home')],
    ];
    if (auth()->user()->can('access-admin')) {
        $links[] = ['users.index', auth()->user()->role->name === 'super_admin' ? 'Account administration' : 'Teachers and students', 'ti-users', request()->routeIs('users.*')];
        $links[] = ['classes.index', 'Class administration', 'ti-school', request()->routeIs('classes.*')];
        $links[] = ['roles.index', 'Fixed roles', 'ti-shield', request()->routeIs('roles.*')];
    } else {
        $links[] = ['classes.index', 'My classes', 'ti-school', request()->routeIs('classes.*')];
    }
    if (in_array(auth()->user()->role->name, ['teacher', 'student'], true)) {
        if (auth()->user()->can('manage-classes')) {
            $links[] = ['classes.index', 'Create Class', 'ti-plus', false, 'create-class'];
        } else {
            $links[] = ['classes.index', 'Join Class', 'ti-plus', false, 'join-class'];
        }
    }
    $links[] = ['chat.index', 'Chats', 'ti-messages', request()->routeIs('chat.*')];
    $links[] = ['profile.edit', 'My profile', 'ti-user-circle', request()->routeIs('profile.*')];
@endphp
<nav class="{{ $compact ? 'teams-nav-list' : 'workspace-nav' }}" aria-label="Workspace links">
    @foreach($links as $link)
        @php
            [$route, $label, $icon, $active] = $link;
            $fragment = $link[4] ?? null;
        @endphp
        <a href="{{ route($route) }}{{ $fragment ? '#'.$fragment : '' }}" class="{{ $compact ? 'teams-nav-link' : 'workspace-nav-link' }} {{ $active ? 'active' : '' }}"
           aria-label="{{ $label }}" title="{{ $label }}" @if($active) aria-current="page" @endif>
            @if (! $compact && $route === 'profile.edit')
                <x-user-avatar :user="auth()->user()" :size="24" />
            @else
                <i class="ti {{ $icon }}" aria-hidden="true"></i>
            @endif
            <span class="{{ $compact ? 'visually-hidden' : '' }}">{{ $label }}</span>
        </a>
    @endforeach
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="{{ $compact ? 'teams-nav-link' : 'workspace-nav-link' }}" title="Log out" aria-label="Log out">
            <i class="ti ti-logout" aria-hidden="true"></i><span class="{{ $compact ? 'visually-hidden' : '' }}">Log out</span>
        </button>
    </form>
</nav>
