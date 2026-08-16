@props(['event', 'header', 'journey', 'activeKey'])

{{--
    Orbit Journey — Phase E.2. Replaces the old flat Journey strip and its
    separate sticky "doors" row entirely: this is the only journey element
    on the Event Hub page. The Event Core sits at the centre (not the active
    stage); the eight stages orbit it as readable cards on a progress ring;
    the active stage's own enabled tabs attach below as satellite modules.

    $journey arrives pre-computed by events/hub.blade.php with, per stage:
    key, label, tabs, and the three fields this component reads directly —
    'state' (complete|active|watch|blocked|pending|future), 'pct'
    (nullable int, only set where EventCommandHeader::meters() actually
    covers this stage's tabs), and 'issues' (int, from attention() on this
    stage's own tabs). Nothing here invents a number meters()/attention()
    didn't already compute — a stage with no real signal shows no pct and
    no issue count rather than a fabricated one.
--}}

@php
    $activeIndex = collect($journey)->search(fn ($s) => $s['key'] === $activeKey);
    $active = $journey[$activeIndex];

    $stateColor = fn (string $state) => match ($state) {
        'complete' => 'var(--color-eo-teal-deep)',
        'active' => 'var(--color-eo-teal-lit)',
        'watch' => 'var(--color-eo-warn)',
        'blocked' => 'var(--color-eo-risk)',
        'pending' => 'var(--color-eo-teal-soft)',
        default => '#e3e6ea', // future
    };

    $stateMeta = function (array $s): string {
        return match ($s['state']) {
            'complete' => '✓ Complete',
            'watch' => $s['issues'] > 0 ? $s['issues'].' '.str('issue')->plural($s['issues']) : 'Watch',
            'blocked' => 'Blocked',
            'pending' => 'Started early',
            'active' => 'Active',
            default => 'Not started',
        };
    };

    // Eight wedges, one per stage, each 45° wide and centred on that stage's
    // own node angle below — a segmented ring, not a single percentage bar,
    // because a stage can be ahead of the active one and still unresolved.
    $gradientStops = collect($journey)
        ->map(fn ($s, $i) => $stateColor($s['state']).' '.($i * 45).'deg '.(($i + 1) * 45).'deg')
        ->implode(', ');

    // Positions clockwise from the top, equal radius — a true circle so the
    // donut mask above reads correctly at every stage count.
    $pos = fn (int $i) => [
        'x' => round(50 + 44 * sin(deg2rad($i * 45)), 2),
        'y' => round(50 - 44 * cos(deg2rad($i * 45)), 2),
    ];

    // A non-active stage's door: its first enabled tab, falling back to the
    // stage's own first tab so the card is never a dead link.
    $stageDoor = fn (array $s) => collect($s['tabs'])->first(fn ($k) => $event->moduleEnabled($k)) ?? $s['tabs'][0];

    $completeCount = collect($journey)->where('state', 'complete')->count();

    // Readiness word, derived from the same pct meters()/attention() already
    // computed — not a new number, just a word for the one that exists.
    $readyWord = fn (?int $pct) => match (true) {
        // Lowercase, matching the module doors' own "Not started" wording
        // (EventOverviewReadinessTest locks this string in) — one word for
        // one state, not two different capitalisations of it.
        $pct === null || $pct === 0 => 'Not started',
        $pct >= 60 => 'On Track',
        default => 'At Risk',
    };
@endphp

<div class="eo-orbit-hero hubx-orbit-card">
    <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
        <p class="eo-label">Orbit Journey</p>
        <span class="eo-orbit-chip">{{ $completeCount }} / {{ count($journey) }} complete · {{ $active['label'] }} active</span>
    </div>

    {{-- Desktop / tablet: the ring. Hidden below 640px — see the linear
         rail further down, same data, a shape that stays legible at that
         size instead of compressing a circle into illegibility. --}}
    <div class="hidden sm:block">
        <div class="eo-orbit-ring-wrap">
            <div class="eo-orbit-track" style="background: conic-gradient(from -22.5deg, {{ $gradientStops }})" aria-hidden="true"></div>

            @foreach ($journey as $i => $stage)
                @php $p = $pos($i); @endphp
                @if ($stage['key'] === $activeKey)
                    <div class="eo-orbit-active" style="left: {{ $p['x'] }}%; top: {{ $p['y'] }}%">
                        <p class="eo-orbit-active-eyebrow">{{ sprintf('%02d', $i + 1) }} · Active</p>
                        <p class="eo-orbit-active-label">{{ $stage['label'] }}</p>
                        <div class="eo-orbit-active-stats">
                            @if ($stage['pct'] !== null)
                                <div><b>{{ $stage['pct'] }}%</b><span>Ready</span></div>
                            @endif
                            @if ($stage['issues'] > 0)
                                <div><b>{{ $stage['issues'] }}</b><span>Issues</span></div>
                            @endif
                        </div>
                        <p class="hubx-orbit-stage-word" style="color: {{ $stateColor($stage['state']) }}">{{ $readyWord($stage['pct']) }}</p>
                    </div>
                @else
                    <a href="{{ route('events.hub', [$event, 'tab' => $stageDoor($stage)]) }}" wire:navigate
                       class="eo-orbit-stage-card is-{{ $stage['state'] }}"
                       style="left: {{ $p['x'] }}%; top: {{ $p['y'] }}%; --st-color: {{ $stateColor($stage['state']) }}">
                        <span class="eo-orbit-stage-idx">{{ sprintf('%02d', $i + 1) }} · {{ strtoupper($stage['label']) }}</span>
                        <p class="eo-orbit-stage-label">{{ $stage['label'] }}</p>
                        <p class="eo-orbit-stage-meta">
                            @if ($stage['pct'] !== null)
                                {{ $stage['pct'] }}%
                            @endif
                            <span class="hubx-orbit-stage-word" style="color: {{ $stateColor($stage['state']) }}">{{ $readyWord($stage['pct']) }}</span>
                        </p>
                    </a>
                @endif
            @endforeach

            <x-eo.event-core :event="$event" :header="$header" />
        </div>

        {{-- Desktop's satellite row was dropped here — the vertical Module
             Rail (redesign) now carries this exact navigation, with the
             same readiness/issue data, so showing both was the same list
             twice. Mobile keeps its own satellites below: the rail hides
             under 900px and satellites are its only replacement there. --}}
    </div>

    {{-- Mobile: the ring degrades to a linear rail — same six states, same
         order, same stage-card colours, just laid out instead of orbited. --}}
    <div class="sm:hidden">
        <x-eo.event-core :event="$event" :header="$header" />

        <div class="eo-orbit-rail">
            <p class="eo-orbit-satellites-label" style="text-align: left; margin-bottom: 0.5625rem;">
                Orbit Journey · {{ $completeCount }}/{{ count($journey) }} complete
            </p>
            <div class="eo-orbit-rail-track">
                @foreach ($journey as $i => $stage)
                    @if (! $loop->first)
                        <div class="eo-orbit-rail-line {{ $i - 1 < $activeIndex ? 'is-done' : '' }}"></div>
                    @endif
                    @if ($stage['key'] === $activeKey)
                        <div class="eo-orbit-rail-node">
                            <span class="eo-orbit-rail-dot" style="width: 15px; height: 15px; background: radial-gradient(circle at 35% 30%, var(--color-eo-teal-lit), var(--color-eo-teal-deep)); box-shadow: 0 0 0 3px #fff, 0 0 0 5px rgba(30,172,172,.25);"></span>
                        </div>
                    @else
                        <a href="{{ route('events.hub', [$event, 'tab' => $stageDoor($stage)]) }}" wire:navigate class="eo-orbit-rail-node">
                            <span class="eo-orbit-rail-dot" style="background: {{ $stateColor($stage['state']) }}; {{ in_array($stage['state'], ['pending', 'future'], true) ? 'box-shadow: inset 0 0 0 2px #fff, 0 0 0 1px '.$stateColor($stage['state']).';' : '' }}"></span>
                        </a>
                    @endif
                @endforeach
            </div>

            <div style="text-align: center; margin: 0.5rem 0 0.5rem;">
                <b style="font-size: 12.5px; font-weight: 800; color: var(--color-eo-text);">{{ $active['label'] }}</b>
                <span style="display: block; font-size: 9px; color: var(--color-eo-muted);">
                    Active stage
                    @if ($active['pct'] !== null) · {{ $active['pct'] }}% ready @endif
                    @if ($active['issues'] > 0) · {{ $active['issues'] }} {{ str('issue')->plural($active['issues']) }} @endif
                </span>
            </div>

            {{-- Mobile's own satellite row was dropped here too — the
                 Module Dock (redesign) is horizontally scrollable below
                 900px, so it's already the on-screen navigation here; this
                 grid was the same list a second time. --}}
        </div>
    </div>
</div>
