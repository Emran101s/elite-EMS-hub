@props([
    'tone' => 'info', // ok | warn | risk | info
    'title' => null,
])

@php
    $styles = match ($tone) {
        'ok', 'success' => 'bg-eo-ok-soft text-eo-text ring-eo-ok/25',
        'warn', 'warning' => 'bg-eo-warn-soft text-eo-text ring-eo-warn/30',
        'risk', 'danger' => 'bg-eo-risk-soft text-eo-text ring-eo-risk/25',
        default => 'bg-eo-teal-soft text-eo-text ring-eo-teal/25',
    };
    $accent = match ($tone) {
        'ok', 'success' => 'bg-eo-ok',
        'warn', 'warning' => 'bg-eo-warn',
        'risk', 'danger' => 'bg-eo-risk',
        default => 'bg-eo-teal',
    };
@endphp

<div {{ $attributes->class(['relative overflow-hidden rounded-[20px] px-4 py-3.5 ring-1', $styles]) }} role="status">
    <span class="absolute inset-y-0 left-0 w-1 {{ $accent }}" aria-hidden="true"></span>
    <div class="pl-2">
        @if ($title)
            <p class="text-[13px] font-semibold">{{ $title }}</p>
        @endif
        <div class="text-[13px] {{ $title ? 'mt-0.5 text-eo-muted' : 'font-medium' }}">
            {{ $slot }}
        </div>
    </div>
</div>
