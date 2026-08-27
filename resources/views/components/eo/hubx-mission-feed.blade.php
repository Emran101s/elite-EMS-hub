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
        1 => ['bg' => 'var(--color-danger-soft)', 'fg' => 'var(--color-danger)'],
        2 => ['bg' => 'var(--color-gold-50)', 'fg' => 'var(--color-gold-700)'],
        default => ['bg' => 'var(--color-page)', 'fg' => 'var(--color-muted)'],
    };
@endphp

<div class="rounded-lg border border-line bg-white px-4 py-3.5">
    <p class="mb-1 text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Mission Timeline</p>

    @if (empty($rows))
        <p class="py-1.5 text-[12px] text-muted">Nothing scheduled and nothing logged yet.</p>
    @else
        <div class="flex flex-col">
            @foreach ($rows as $row)
                @php $c = $tone($row['tier']); @endphp
                <a href="{{ $row['href'] }}" wire:navigate class="ehc-timeline-row" style="--tl-color: {{ $c['fg'] }}">
                    <span class="ehc-timeline-track">
                        <span class="ehc-timeline-node" style="background: {{ $c['bg'] }}; color: {{ $c['fg'] }}">
                            <x-icon :name="$row['icon']" class="h-3 w-3" />
                        </span>
                    </span>
                    <span class="ehc-timeline-body">
                        <span class="min-w-0 truncate text-[12.5px] font-semibold text-ink">{{ $row['title'] }}</span>
                        <span @class([
                            'shrink-0 text-[10.5px] font-semibold text-muted',
                            'rounded-full bg-danger-soft px-1.5 py-0.5 text-danger-ink' => $row['tier'] === 1,
                        ])>{{ $row['when'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</div>
