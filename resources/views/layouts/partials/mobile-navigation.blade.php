@php
    $mobileLinks = [
        ['home', 'Home', 'ti-home', request()->routeIs('home')],
    ];

    if (auth()->user()->can('access-admin')) {
        $mobileLinks[] = ['users.index', 'Accounts', 'ti-users', request()->routeIs('users.*') || request()->routeIs('roles.*')];
    }

    $mobileLinks[] = ['classes.index', 'Classes', 'ti-school', request()->routeIs('classes.*') && ! request()->routeIs('classes.*quiz*') && ! request()->routeIs('classes.assessments.*')];

    if (in_array(auth()->user()->role->name, ['teacher', 'student'], true)) {
        $mobileLinks[] = ['assessments.index', auth()->user()->role->name === 'teacher' ? 'Assessments' : 'Quizzes', 'ti-clipboard-check', request()->routeIs('assessments.*') || request()->routeIs('classes.*quiz*') || request()->routeIs('classes.assessments.*')];
        $mobileLinks[] = ['calendar.index', 'Calendar', 'ti-calendar', request()->routeIs('calendar.*')];
    }

    $mobileLinks[] = ['search.index', 'Search', 'ti-search', request()->routeIs('search.*')];
    $mobileLinks[] = ['chat.index', 'Chats', 'ti-messages', request()->routeIs('chat.*')];
    $mobileLinks[] = ['profile.edit', 'Profile', 'ti-user-circle', request()->routeIs('profile.*') || request()->routeIs('settings.*')];
@endphp

<nav class="workspace-mobile-nav" aria-label="Primary mobile navigation" style="--workspace-mobile-nav-count: {{ count($mobileLinks) }}">
    @foreach ($mobileLinks as [$route, $label, $icon, $active])
        <a href="{{ route($route) }}" class="workspace-mobile-nav-link {{ $active ? 'active' : '' }}"
            data-workspace-nav aria-label="{{ $label }}" title="{{ $label }}" @if ($active) aria-current="page" @endif>
            <i class="ti {{ $icon }}" aria-hidden="true"></i>
            <span>{{ $label }}</span>
        </a>
    @endforeach
</nav>
