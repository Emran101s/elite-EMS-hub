@props([
    'tone' => 'info', // ok | warn | risk | info
    'title' => null,
])

@php
    $styles = match ($tone) {
        'ok', 'success' => 'bg-success-soft text-ink ring-success/25',
        'warn', 'warning' => 'bg-warning-soft text-ink ring-warning/30',
        'risk', 'danger' => 'bg-danger-soft text-ink ring-danger/25',
        default => 'bg-info-soft text-ink ring-info/25',
    };
    $accent = match ($tone) {
        'ok', 'success' => 'bg-success',
        'warn', 'warning' => 'bg-warning',
        'risk', 'danger' => 'bg-danger',
        default => 'bg-info',
    };
@endphp

<div {{ $attributes->class(['relative overflow-hidden rounded-[20px] px-4 py-3.5 ring-1', $styles]) }} role="status">
    <span class="absolute inset-y-0 left-0 w-1 {{ $accent }}" aria-hidden="true"></span>
    <div class="pl-2">
        @if ($title)
            <p class="text-[13px] font-semibold">{{ $title }}</p>
        @endif
        <div class="text-[13px] {{ $title ? 'mt-0.5 text-muted' : 'font-medium' }}">
            {{ $slot }}
        </div>
    </div>
</div>
