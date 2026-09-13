@php
    $notifications = collect([
        'success' => session('success'),
        'error' => session('error'),
        'warning' => session('warning'),
        'info' => session('info'),
    ])->filter(fn ($message) => is_string($message) && trim($message) !== '');

    if ($errors->any() && ! $notifications->has('error')) {
        $notifications->put('error', 'Please check the highlighted fields.');
    }
@endphp

<div id="app-notifications" class="app-notifications" aria-label="Notifications"></div>

@if ($notifications->isNotEmpty())
    <div data-notification-seeds hidden>
        @foreach ($notifications as $type => $message)
            <span data-notification-seed data-type="{{ $type }}">{{ $message }}</span>
        @endforeach
    </div>
@endif
