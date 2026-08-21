@props(['label' => 'Mission Radar', 'story' => null, 'missions' => [], 'stats' => []])

@php
    $toneInk = fn (?string $tone) => match ($tone) {
        'ok' => 'var(--color-success)',
        'warn' => 'var(--color-warning)',
        'risk' => 'var(--color-danger)',
        default => 'var(--color-info)', // live / unset
    };
    $toneOnDark = fn (?string $tone) => match ($tone) {
        'ok' => 'var(--color-success-on-dark)',
        'warn' => 'var(--color-warning-on-dark)',
        'risk' => 'var(--color-danger-on-dark)',
        default => 'var(--color-info-on-dark)',
    };

    // Nodes come in already positioned (an orbit angle, a days-out radius) —
    // clamped here once so a node near the rim can never crop its own tooltip
    // or spill outside the dish, the same reasoning eo/mission-radar used.
    $clamp = fn ($v) => max(14, min(86, (float) $v));
    $nodes = collect($missions)->map(fn ($m) => [
        ...$m,
        'x' => $clamp($m['x'] ?? 50),
        'y' => $clamp($m['y'] ?? 50),
    ])->all();
@endphp

<div x-data="{ open: false }">
    <button type="button" @click="open = ! open"
            class="flex w-full flex-wrap items-center gap-4 rounded-lg border border-line bg-white px-4 py-3 text-left transition hover:border-gold-300 hover:shadow-float">
        <span class="flex items-center gap-2 text-[12.5px] font-bold text-ink">
            <x-icon name="chart" class="h-4 w-4 text-gold-600" /> {{ $label }}
        </span>
        <span class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[11.5px] font-semibold text-muted">
            @foreach ($stats as $stat)
                <span class="flex items-center gap-1.5">
                    <span class="tabular-nums text-ink">{{ $stat['value'] }}</span> {{ $stat['label'] }}
                </span>
            @endforeach
        </span>
        <span class="ms-auto flex items-center gap-1.5 text-[11.5px] font-bold text-gold-600">
            <span x-text="open ? 'Hide radar' : 'Open radar'"></span>
            <x-icon name="chevron" class="h-3 w-3 transition" x-bind:class="open ? '-rotate-90' : 'rotate-90'" />
        </span>
    </button>

    <div x-show="open" x-cloak x-collapse class="mt-3 rounded-lg border border-line bg-white p-5 shadow-float">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">{{ $label }}</p>
                <p class="pf mt-1 text-[18px] font-semibold text-ink">Event orbit</p>
                @if ($story)
                    <p class="mt-1 max-w-md text-[12.5px] leading-relaxed text-muted">{{ $story }}</p>
                @endif
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-2.5 py-1 text-[10.5px] font-bold uppercase tracking-wide text-gold-700">
                <span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Live
            </span>
        </div>

        <div class="ccx-radar-field" role="img" aria-label="{{ $label }}">
            <span class="ccx-radar-cross" aria-hidden="true"></span>
            <span class="ccx-radar-ring" aria-hidden="true"></span>
            <span class="ccx-radar-ring ccx-radar-ring-2" aria-hidden="true"></span>
            <span class="ccx-radar-ring ccx-radar-ring-3" aria-hidden="true"></span>
            <span class="ccx-radar-sweep" aria-hidden="true"></span>
            <span class="ccx-radar-core" aria-hidden="true"></span>

            @foreach ($nodes as $node)
                @php
                    $tone = $node['tone'] ?? 'live';
                    $href = $node['href'] ?? null;
                    $tag = $href ? 'a' : 'span';
                @endphp
                <{{ $tag }}
                    @if ($href) href="{{ $href }}" @endif
                    @class(['ccx-radar-node', 'is-featured' => $node['featured'] ?? false])
                    style="left: {{ $node['x'] }}%; top: {{ $node['y'] }}%; color: {{ $toneOnDark($tone) }}"
                    @if ($node['label'] ?? null) title="{{ $node['label'] }}{{ isset($node['readiness']) ? ' · '.$node['readiness'].'% ready' : '' }}" @endif
                >
                    <span class="ccx-radar-node-dot" style="background: currentColor"></span>
                </{{ $tag }}>
            @endforeach
        </div>

        @if (! empty($stats))
            <div class="mt-5 grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach ($stats as $stat)
                    <div class="rounded-md bg-page px-3 py-2.5 text-center">
                        <p class="text-[18px] font-extrabold tabular-nums" style="color: {{ $toneInk($stat['tone'] ?? null) }}">
                            {{ $stat['value'] }}
                        </p>
                        <p class="mt-0.5 text-[10.5px] font-semibold text-muted">{{ $stat['label'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
