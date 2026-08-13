@props(['event', 'header'])

{{--
    Event Core — Phase E.2. The centre of the Orbit Journey: the event
    itself, not the active stage. Every field here reads off
    EventCommandHeader::for() or the Event model directly — nothing is
    computed fresh for this card, so it cannot disagree with the header
    above it or the tabs it links to.
--}}

@php
    $title = $header['title'];
    $readiness = $header['readiness'];
    $critical = $header['critical'];

    // The countdown block already lives in scale() — "26d to go" / "Day 3"
    // / "Delivered" — reused rather than recomputed.
    $countdown = collect($header['scale'])->firstWhere('icon', 'clock');

    $where = collect([$event->city, $event->country])->filter()->implode(', ');
@endphp

<div class="eo-orbit-core">
    <p class="eo-orbit-core-eyebrow">Event Core</p>
    <h2 class="eo-orbit-core-name">
        {{ $title['lead'] }}@if ($title['tail'])<span class="tail"> · {{ $title['tail'] }}</span>@endif
    </h2>

    <div class="eo-orbit-core-grid">
        <div class="eo-orbit-core-field">
            <span>Health</span>
            <b class="tone-teal">{{ $header['health']['score'] ?? '—' }}</b>
        </div>
        <div class="eo-orbit-core-field">
            <span>Readiness</span>
            <b class="tone-teal">{{ $readiness['pct'] }}%</b>
        </div>
        <div class="eo-orbit-core-field">
            <span>{{ $countdown['label'] ?? 'Days out' }}</span>
            <b>{{ $countdown['value'] ?? '—' }}</b>
        </div>
        <div class="eo-orbit-core-field">
            <span>Client</span>
            <b class="small">{{ $event->client?->name ?? '—' }}</b>
        </div>
        <div class="eo-orbit-core-field">
            <span>Location</span>
            <b class="small">{{ $where ?: '—' }}</b>
        </div>
        <div class="eo-orbit-core-field">
            <span>Owner</span>
            <b class="small">{{ $event->projectManager?->name ?? 'Unassigned' }}</b>
        </div>
    </div>

    @if ($critical)
        <div class="eo-orbit-core-divider"></div>
        <div class="eo-orbit-core-next">
            <span>Next action</span>
            <b>{{ $critical['title'] }}</b>
        </div>
    @endif

    {{-- Inside the Hub itself "Open Event Hub" has nowhere further to go —
         this jumps to Overview, the one door every stage sits in front of,
         rather than repeat a label that would be a no-op on this page. --}}
    <a href="{{ route('events.hub', [$event, 'tab' => 'overview']) }}" wire:navigate class="eo-orbit-core-cta">
        Jump to Overview →
    </a>
</div>
