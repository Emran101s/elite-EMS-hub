@props(['venue', 'header'])

@php
    $healthColor = match ($header['health']['status']) {
        'track' => 'var(--color-success)', 'warn' => 'var(--color-warning)', default => 'var(--color-danger)',
    };
    $undocumented = $venue->spaces->reject->isFullyDocumented();
@endphp

{{-- Permanent right column, never a modal — same rule as the Event Hub's
     own Inspector (x-eo.hubx-inspector). --}}
<div class="hubx-panel">
    <div class="hubx-panel-head">
        <span class="hubx-panel-icon" style="background: color-mix(in srgb, {{ $healthColor }} 16%, transparent); color: {{ $healthColor }}">
            <x-icon name="building" class="h-4 w-4" />
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted !text-[9.5px]">Command Tower</p>
            <p class="text-[14px] font-extrabold text-ink">{{ $venue->name }}</p>
        </div>
        <span class="hubx-pill" style="background: color-mix(in srgb, {{ $healthColor }} 16%, transparent); color: {{ $healthColor }}">
            {{ $header['health']['score'] }}
        </span>
    </div>

    <div class="hubx-panel-detail">
        {{-- Conflicts — a real double-booking, worst signal a venue can carry. --}}
        <div>
            <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted !text-[9px] opacity-70">Venue Risks</p>
            @forelse ($header['conflicts'] as $conflict)
                <div class="hubx-panel-attention-row">
                    <x-icon name="bell" class="h-3.5 w-3.5 text-danger-ink" />
                    <span class="text-[11.5px] font-semibold text-ink">Double-booked</span>
                    <span class="ml-auto shrink-0 text-[10.5px] text-muted">{{ $conflict['detail'] }}</span>
                </div>
            @empty
                <p class="text-[10px] text-muted opacity-75">No overlapping bookings.</p>
            @endforelse
        </div>

        {{-- Events at this venue live in their own panel above this tower (the
             studio's reverse-link card), which lists every event and points at
             each one's Venue tab — so the tower no longer repeats an "Upcoming
             Events" list of the same bookings. --}}

        {{-- Spaces still missing setup-capacity data — a real readiness gap. --}}
        @if ($undocumented->isNotEmpty())
            <div class="mt-2.5">
                <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted !text-[9px] opacity-70">Needs Attention</p>
                @foreach ($undocumented as $space)
                    <a href="{{ route('venues.show', [$venue, 'tab' => 'spaces']) }}" wire:navigate class="hubx-panel-attention-row">
                        <x-icon name="chart" class="h-3.5 w-3.5 text-muted" />
                        <span class="text-[11.5px] font-semibold text-ink">{{ $space->name }}</span>
                        <span class="ml-auto shrink-0 text-[10.5px] text-muted">Documentation incomplete</span>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="mt-2.5">
            <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted !text-[9px] opacity-70">Quick Actions</p>
            <div class="mt-1.5 grid grid-cols-2 gap-1.5">
                <a href="{{ route('venues.show', [$venue, 'tab' => 'spaces', 'action' => 'add']) }}" wire:navigate
                   class="flex flex-col items-center gap-1 rounded-xl border border-line bg-white/70 px-2 py-1.5 text-center transition hover:border-gold-400">
                    <x-icon name="sparkles" class="h-3 w-3 text-muted" />
                    <span class="text-[9px] font-semibold text-muted">Add Space</span>
                </a>
                <a href="{{ route('venues.show', [$venue, 'tab' => 'capacity']) }}" wire:navigate
                   class="flex flex-col items-center gap-1 rounded-xl border border-line bg-white/70 px-2 py-1.5 text-center transition hover:border-gold-400">
                    <x-icon name="chart" class="h-3 w-3 text-muted" />
                    <span class="text-[9px] font-semibold text-muted">Capacity</span>
                </a>
            </div>
        </div>
    </div>
</div>
