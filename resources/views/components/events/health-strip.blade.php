@props(['deck', 'active' => null, 'selectAction' => 'activate'])

{{--
    Fleet Health Strip — one bar per mission in view, tallest/greenest first
    scan-order reversed: sorted worst-first so the events that most need a
    person are the first thing the eye lands on, left to right. Every bar is
    the same "select this mission" trigger the board/table/timeline cards
    already use, so a click here opens the exact same shared detail panel —
    this is a second way into that panel, not a second source of truth.
--}}
@php
    // Worst-first by actual severity, not by score alone — an unscored
    // event isn't necessarily a bad one, just unmeasured, so it sorts
    // after every confirmed-risk mission rather than ahead of them.
    $groupRank = fn ($m) => match ($m['healthGroup'] ?? null) {
        'risk' => 0, 'warn' => 1, 'live' => 2, 'ok' => 3, default => 4,
    };
    $bars = collect($deck)
        ->sortBy([
            fn ($m) => $groupRank($m),
            fn ($m) => $m['healthScore'] ?? 999,
        ])
        ->values();
@endphp

@if ($bars->isNotEmpty())
    <div class="rounded-lg border border-line bg-white px-4 py-3">
        <div class="mb-2.5 flex items-center justify-between">
            <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Fleet Health</p>
            <p class="text-[10.5px] text-muted">Worst first · click a bar to inspect</p>
        </div>
        <div class="flex items-end gap-[3px] overflow-x-auto pb-1">
            @foreach ($bars as $m)
                @php
                    $score = $m['healthScore'] ?? null;
                    $tone = match ($m['healthGroup'] ?? null) {
                        'ok' => 'var(--color-success)', 'warn' => 'var(--color-warning)',
                        'risk' => 'var(--color-danger)', 'live' => 'var(--color-info)',
                        default => 'var(--color-line)',
                    };
                    $h = $score !== null ? max(5, (int) round($score / 100 * 32)) : 5;
                    $isActive = $active && $active['id'] === $m['id'];
                @endphp
                <button type="button" wire:click="{{ $selectAction }}({{ $m['id'] }})"
                        title="{{ $m['name'] }} · {{ $score !== null ? $score.' health' : 'not scored' }}"
                        @class([
                            'w-[7px] shrink-0 rounded-full transition-all hover:opacity-80',
                            'ring-2 ring-gold-400 ring-offset-1' => $isActive,
                        ])
                        style="height: {{ $h }}px; background: {{ $tone }}; opacity: {{ $isActive ? 1 : 0.85 }}">
                </button>
            @endforeach
        </div>
    </div>
@endif
