{{-- Live event health, one click from anywhere in the platform. --}}
<details class="group relative">
    <summary class="relative grid h-10 w-10 cursor-pointer list-none place-items-center rounded-full bg-white text-navy-600 shadow-[0_2px_10px_-4px_rgba(11,31,58,0.25)] transition hover:text-navy-900 [&::-webkit-details-marker]:hidden"
             title="Event Radar — live event health">
        <x-icon name="chart" class="h-[18px] w-[18px]" />
        @if ($attention > 0)
            <span class="absolute -right-0.5 -top-0.5 grid h-[17px] min-w-[17px] place-items-center rounded-full bg-warn px-1 text-[9px] font-bold text-white ring-2 ring-page">{{ $attention }}</span>
        @endif
    </summary>

    <div class="absolute right-0 z-40 mt-2 w-[304px] overflow-hidden rounded-2xl border border-line bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-line px-4 py-3">
            <div>
                <p class="eyebrow">Event Radar</p>
                <p class="text-[11px] text-muted">Live event health</p>
            </div>
            <span class="flex items-center gap-1.5 text-[10px] font-bold text-emerald-600">
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-track"></span> Live
            </span>
        </div>

        <div class="scrollbar-none max-h-[340px] overflow-y-auto p-1.5">
            @forelse ($events as $event)
                <a href="{{ route('events.hub', $event) }}"
                   class="flex items-center gap-2.5 rounded-xl px-2.5 py-2 transition hover:bg-navy-50">
                    <x-health-ring :percent="$event->radar_score" :group="$event->radar_group"
                                   size="h-8 w-8" textSize="text-[8px]" class="shrink-0" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-[12.5px] font-semibold text-navy-900">{{ $event->name }}</span>
                        <span class="block truncate text-[10.5px] text-muted">{{ $event->radar_status }}</span>
                    </span>
                    <span class="shrink-0 text-[10.5px] tabular-nums text-navy-300">
                        {{ $event->starts_at?->format('d M') ?? '—' }}
                    </span>
                </a>
            @empty
                <p class="px-3 py-4 text-[12px] text-muted">No active events on the radar.</p>
            @endforelse
        </div>

        <a href="{{ route('events.index') }}"
           class="block border-t border-line px-4 py-2.5 text-center text-[12px] font-bold text-gold-600 transition hover:bg-navy-50 hover:text-gold-700">
            All events →
        </a>
    </div>
</details>
