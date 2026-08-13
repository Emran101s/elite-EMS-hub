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

    // The active stage's own enabled doors — same route, same enable check,
    // same attention signal the old sticky nav row read; only the
    // presentation (attached satellites, not a separate strip) changed.
    $modules = \App\Models\Event::HUB_TABS;
    $attention = $header['attention'] ?? [];
    $satellites = collect($active['tabs'])
        ->filter(fn (string $key) => $event->moduleEnabled($key))
        ->map(function (string $key) use ($modules, $event, $attention) {
            [$label] = $modules[$key] ?? [ucfirst($key)];

            return [
                'key' => $key,
                'label' => $label,
                'href' => route('events.hub', [$event, 'tab' => $key]),
                'hex' => \App\Models\Event::moduleColor($key),
                'icon' => $modules[$key][2] ?? 'archive',
                'n' => $attention[$key] ?? null,
            ];
        })
        ->values();

    // A non-active stage's door: its first enabled tab, falling back to the
    // stage's own first tab so the card is never a dead link.
    $stageDoor = fn (array $s) => collect($s['tabs'])->first(fn ($k) => $event->moduleEnabled($k)) ?? $s['tabs'][0];

    $completeCount = collect($journey)->where('state', 'complete')->count();
@endphp

<div class="eo-orbit-hero">
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
                    </div>
                @else
                    <a href="{{ route('events.hub', [$event, 'tab' => $stageDoor($stage)]) }}" wire:navigate
                       class="eo-orbit-stage-card is-{{ $stage['state'] }}"
                       style="left: {{ $p['x'] }}%; top: {{ $p['y'] }}%; --st-color: {{ $stateColor($stage['state']) }}">
                        <span class="eo-orbit-stage-idx">{{ sprintf('%02d', $i + 1) }} · {{ strtoupper($stage['label']) }}</span>
                        <p class="eo-orbit-stage-label">{{ $stage['label'] }}</p>
                        <p class="eo-orbit-stage-meta">{{ $stateMeta($stage) }}</p>
                    </a>
                @endif
            @endforeach

            <x-eo.event-core :event="$event" :header="$header" />
        </div>

        <div class="eo-orbit-satellites" aria-label="{{ $active['label'] }} — satellites">
            <span class="eo-orbit-satellites-label">{{ $active['label'] }} · satellites</span>
            <div class="eo-orbit-sat-grid">
                @foreach ($satellites as $sat)
                    <a href="{{ $sat['href'] }}" wire:navigate class="eo-orbit-sat" style="--sat-color: {{ $sat['hex'] }}">
                        <span class="eo-orbit-sat-icon"><x-icon :name="$sat['icon']" class="h-3 w-3" /></span>
                        <b>{{ $sat['label'] }}</b>
                        @if ($sat['n'])
                            <span class="eo-orbit-sat-note">
                                {{ $sat['n']['why'] }}
                                <span class="eo-orbit-sat-badge">{{ $sat['n']['count'] }}</span>
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
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

            <div class="eo-orbit-sat-grid" aria-label="{{ $active['label'] }} — satellites">
                @foreach ($satellites as $sat)
                    <a href="{{ $sat['href'] }}" wire:navigate class="eo-orbit-sat" style="--sat-color: {{ $sat['hex'] }}">
                        <span class="eo-orbit-sat-icon"><x-icon :name="$sat['icon']" class="h-3 w-3" /></span>
                        <b>{{ $sat['label'] }}</b>
                        @if ($sat['n'])
                            <span class="eo-orbit-sat-note">
                                {{ $sat['n']['why'] }}
                                <span class="eo-orbit-sat-badge">{{ $sat['n']['count'] }}</span>
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>
