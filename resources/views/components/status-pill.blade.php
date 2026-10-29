@props(['status'])

@php
    $displayStatus = match($status) {
        'ongoing', 'in_progress' => 'In Progress',
        default => ucfirst((string) $status),
    };
    $classStatus = match($status) {
        'in_progress' => 'ongoing',
        default => $status,
    };
@endphp

<span {{ $attributes->merge(['class' => "status-pill status-pill--{$classStatus}"]) }}>
    {{ $displayStatus }}
</span>
