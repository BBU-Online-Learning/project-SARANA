@props(['value', 'label' => null])
@php
    $normalized = strtolower(trim((string) $value));
    $tone = match ($normalized) {
        'published', 'present', 'submitted', 'active', 'finalized' => 'success',
        'absent', 'cancelled', 'failed' => 'danger',
        'late', 'excused', 'paused', 'expired', 'rescheduled', 'unmarked', 'open' => 'warning',
        'scheduled', 'reviewed', 'pinned', 'graded' => 'info',
        default => 'neutral',
    };
@endphp
<span {{ $attributes->class(['class-status', 'class-status--'.$tone]) }}>{{ $label ?? ucfirst(str_replace('_', ' ', $normalized)) }}</span>
