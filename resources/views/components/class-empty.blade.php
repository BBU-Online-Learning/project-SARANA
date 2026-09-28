@props(['title', 'description' => null, 'icon' => 'ti-inbox'])
<div {{ $attributes->class(['class-empty']) }}>
    <span class="class-empty-icon" aria-hidden="true"><i class="ti {{ $icon }}"></i></span>
    <p class="class-empty-title">{{ $title }}</p>
    @if ($description)
        <p class="class-empty-description">{{ $description }}</p>
    @endif
    {{ $slot }}
</div>
