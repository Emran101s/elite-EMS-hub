@props(['active' => false, 'eyebrow' => null, 'eyebrowDanger' => false, 'title', 'subtitle' => null, 'subtitleDanger' => false, 'amount', 'badgeLabel' => null, 'badgeTone' => 'live', 'dimmed' => false])

@php
    $badgeClasses = match ($badgeTone) {
        'risk' => 'bg-danger-soft text-danger-ink',
        'ok' => 'bg-success-soft text-success-ink',
        'warn' => 'bg-warning-soft text-warning-ink',
        default => 'bg-info-soft text-info-ink',
    };
@endphp

@if ($active)
    <div class="rounded-lg bg-navy-900 p-4">
        @if ($eyebrow)
            <p class="text-[11px] font-bold uppercase tracking-wide text-gold-400">{{ $eyebrow }}</p>
        @endif
        <p class="mt-1 truncate text-[14px] font-semibold text-white">{{ $title }}</p>
        @if ($subtitle)
            <p class="mt-1 truncate text-[12px] text-white/50">{{ $subtitle }}</p>
        @endif
        <div class="mt-3 flex items-center justify-between gap-2">
            <span class="text-[15px] font-bold tabular-nums text-white">{{ $amount }}</span>
            @if ($badgeLabel)
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10.5px] font-bold {{ $badgeClasses }}">{{ $badgeLabel }}</span>
            @endif
        </div>
    </div>
@else
    <div class="rounded-lg border border-line bg-white px-4 py-3 transition hover:border-navy-300 hover:shadow-float {{ $dimmed ? 'opacity-55' : '' }}">
        @if ($eyebrow)
            <p class="text-[11px] {{ $eyebrowDanger ? 'font-bold text-danger-ink' : 'text-muted' }}">{{ $eyebrow }}</p>
        @endif
        <p class="mt-0.5 truncate text-[13px] font-bold text-ink">{{ $title }}</p>
        <div class="mt-2 flex items-center justify-between gap-2">
            <span class="truncate text-[11px] {{ $subtitleDanger ? 'font-bold text-danger-ink' : 'text-muted' }}">{{ $subtitle }}</span>
            <span class="text-[12px] font-bold tabular-nums text-ink">{{ $amount }}</span>
        </div>
    </div>
@endif
