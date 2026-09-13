<span {{ $attributes->class(['user-avatar'])->merge(['style' => "--user-avatar-size: {$size}px"]) }} aria-hidden="true">
    <span class="user-avatar-initials">{{ $user->initials() }}</span>
    @if ($user->profileUrl())
        <img src="{{ $user->profileUrl() }}" alt="" width="{{ $size }}" height="{{ $size }}" loading="lazy">
    @endif
    {{ $slot }}
</span>
