<div class="rounded-lg border border-line bg-white shadow-raise p-5">
    <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Exhibition History</p>
    <p class="mt-1 text-[12px] text-muted">Every exhibition hall used at this venue, across every event booked here. Booth sales stay with the event that sold them — open the floor plan to change anything.</p>

    @if ($rows->isEmpty())
        <x-eo.empty-state title="No exhibition halls yet" icon="grid" class="mt-4"
            hint="An exhibition hall shows up here once an event booked at this venue opens its Exhibition Floor Plan." />
    @else
        <div class="mt-4 space-y-2.5">
            @foreach ($rows as $row)
                <div class="rounded-lg border border-line bg-white shadow-raise p-3.5">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-[12.5px] font-bold text-ink">{{ $row['hall']->name }}</p>
                            <p class="text-[10.5px] text-muted">
                                {{ $row['hall']->event?->name ?? 'No event' }}
                                @if ($row['hall']->event?->starts_at)
                                    · {{ $row['hall']->event->starts_at->format('j M Y') }}
                                @endif
                            </p>
                        </div>
                        @if ($row['hall']->event)
                            <a href="{{ route('events.exhibition-floor', $row['hall']->event) }}" wire:navigate
                               class="shrink-0 text-[10.5px] font-bold text-gold-700 hover:underline">Open floor plan →</a>
                        @endif
                    </div>

                    <div class="mt-2.5 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-muted">
                        <span>{{ $row['booths'] }} {{ str('booth')->plural($row['booths']) }}</span>
                        <span>{{ $row['sold'] }} sold</span>
                        @if ($row['revenue'] > 0 && $row['hall']->event)
                            <span>{{ $row['hall']->event->money($row['revenue']) }} revenue</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
