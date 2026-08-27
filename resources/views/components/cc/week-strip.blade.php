@props(['week'])

<div class="overflow-hidden rounded-lg border border-line bg-white">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-5 py-4">
        <div>
            <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">The week ahead</p>
            <p class="mt-1 text-[15px] font-semibold text-ink">Sessions · movements · deadlines</p>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-2.5 py-1 text-[10.5px] font-bold uppercase tracking-wide text-gold-700">
            <span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> 7-day strip
        </span>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7">
        @foreach ($week as $day)
            <div @class([
                'border-line px-3 py-4 sm:border-r',
                'bg-gold-50/60' => $day['today'],
            ])>
                <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">{{ $day['date']->format('D') }}</p>
                <p class="mt-1 text-[20px] font-bold tabular-nums text-ink">{{ $day['date']->format('j') }}</p>
                <p class="mt-2 text-[12px] text-muted">
                    {{ $day['load'] ? $day['load'].' items' : 'Clear' }}
                </p>
                @if ($day['starting']->isNotEmpty())
                    <p class="mt-1 truncate text-[11px] font-semibold text-gold-700">
                        {{ $day['starting']->first()->name }}
                    </p>
                @endif
            </div>
        @endforeach
    </div>
</div>
