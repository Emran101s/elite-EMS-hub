@props(['event'])

{{--
    Mission Timeline — Overview's one workspace element (evolved from
    Mission Feed: same data, same tier rules, now drawn as a connected
    timeline rather than a flat list). Reads App\Support\MissionFeed::rows(),
    which does all the curation; this component just renders whatever real
    rows came back, oldest-tier-first, as nodes on a single line. See that
    class for the tier rules (action required → upcoming → milestone) and
    the "status change only, not every edit" noise filter.
--}}

@php
    $rows = \App\Support\MissionFeed::rows($event);
    $tone = fn (int $tier) => match ($tier) {
        1 => ['bg' => 'var(--color-eo-risk-soft)', 'fg' => 'var(--color-eo-risk)'],
        2 => ['bg' => 'var(--color-eo-teal-soft)', 'fg' => 'var(--color-eo-teal-ink)'],
        default => ['bg' => 'var(--color-eo-workspace)', 'fg' => 'var(--color-eo-muted)'],
    };
@endphp

<div class="hubx-feed">
    <p class="eo-label !mb-1">Mission Timeline</p>

    @if (empty($rows))
        <p class="hubx-feed-empty">Nothing scheduled and nothing logged yet.</p>
    @else
        <div class="hubx-timeline">
            @foreach ($rows as $row)
                @php $c = $tone($row['tier']); @endphp
                <a href="{{ $row['href'] }}" wire:navigate class="hubx-timeline-row" style="--tl-color: {{ $c['fg'] }}">
                    <span class="hubx-timeline-track">
                        <span class="hubx-timeline-node" style="background: {{ $c['bg'] }}; color: {{ $c['fg'] }}">
                            <x-icon :name="$row['icon']" class="h-3 w-3" />
                        </span>
                    </span>
                    <span class="hubx-timeline-body">
                        <span class="hubx-timeline-title">{{ $row['title'] }}</span>
                        <span class="hubx-timeline-when {{ $row['tier'] === 1 ? 'is-urgent' : '' }}">{{ $row['when'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</div>
