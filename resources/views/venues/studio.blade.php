<x-layouts.app :title="$venue->name . ' — Venue Studio'"
               :hide-title-row="true"
               :crumbs="[
                   ['label' => 'Command Center', 'href' => route('home')],
                   ['label' => 'Venues', 'href' => route('venues.index')],
                   ['label' => $venue->name, 'href' => route('venues.show', $venue)],
                   ['label' => \App\Models\Venue::STUDIO_TABS[$tab][0] ?? ucfirst($tab)],
               ]">

    <x-eo.venue-studio.header :venue="$venue" :header="$header" />

    <div class="mt-3">
        <x-eo.venue-studio.module-nav :venue="$venue" :active-tab="$tab" />
    </div>

    <div class="mt-2">
        <x-eo.venue-studio.kpi-strip :venue="$venue" :header="$header" />
    </div>

    <div class="hubx-grid has-panel mt-2">
        <div class="min-w-0">
            @includeIf('venues.studio.' . $tab, ['venue' => $venue, 'header' => $header])
        </div>

        <div class="hubx-col-panel">
            {{-- Reverse link: which events are booked into this venue. The
                 forward link lives on each event's Venue tab; this closes the
                 loop so the two stop feeling disconnected. --}}
            <div class="mb-3 rounded-lg border border-line bg-white p-3">
                <p class="mb-2 flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">
                    <span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Events at this venue
                    <span class="ms-auto rounded-full bg-page px-1.5 py-0.5 tabular-nums text-ink">{{ $venue->events->count() }}</span>
                </p>
                @forelse ($venue->events->sortByDesc('starts_at')->take(6) as $e)
                    <a href="{{ route('events.hub', [$e, 'tab' => 'venue']) }}" wire:key="venue-event-{{ $e->id }}"
                       class="group flex items-center gap-2 rounded-lg px-1.5 py-1.5 transition hover:bg-page">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-page text-muted"><x-icon name="calendar" class="h-3.5 w-3.5" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[13px] font-bold text-ink group-hover:text-gold-700">{{ $e->name }}</span>
                            <span class="block truncate text-eyebrow text-muted">{{ $e->starts_at?->format('d M Y') ?? 'No date' }}</span>
                        </span>
                        <span aria-hidden="true" class="shrink-0 text-eyebrow font-bold text-muted group-hover:text-gold-700">→</span>
                    </a>
                @empty
                    <p class="px-1.5 py-1 text-eyebrow leading-snug text-muted">No events booked here yet. Link this venue from an event's Venue tab.</p>
                @endforelse
            </div>

            <x-eo.venue-studio.command-tower :venue="$venue" :header="$header" />
        </div>
    </div>
</x-layouts.app>
