@props(['mission', 'starred' => false])

{{--
    A mission held at the edge of the deck: enough to recognise and judge it,
    not so much that it competes with the one in the centre. Clicking brings it
    in — that is the whole interaction.
--}}

@php $m = $mission; @endphp

<button type="button" wire:click="activate({{ $m['id'] }})" wire:key="side-{{ $m['id'] }}"
        {{ $attributes->merge(['class' => 'group/side w-full overflow-hidden rounded-[20px] border border-line bg-white/85 text-left shadow-sm backdrop-blur transition hover:-translate-y-1 hover:border-indigo-200 hover:bg-white hover:shadow-[0_22px_44px_-26px_rgba(30,27,75,0.5)]']) }}>

    <div class="relative isolate h-[92px] overflow-hidden">
        @if ($m['cover'])
            <img src="{{ $m['cover'] }}" alt="" class="absolute inset-0 -z-10 h-full w-full object-cover transition duration-500 group-hover/side:scale-105" style="object-position: 50% 40%">
        @else
            <x-event-crest :event="$m['event']" class="absolute inset-0 -z-10 h-full w-full" />
        @endif
        <div class="absolute inset-0 -z-10 bg-gradient-to-t from-navy-950/45 to-transparent"></div>

        <div class="flex items-start justify-between p-2.5">
            <x-mission.badge :mission="$m" size="xs" class="!bg-white/95 !ring-white/50" />
            @if ($starred)<x-icon name="star" class="h-3.5 w-3.5 fill-gold-400 text-gold-400 drop-shadow" />@endif
        </div>
    </div>

    <div class="flex items-start gap-2.5 p-3">
        <div class="grid w-[46px] shrink-0 place-items-center rounded-xl border border-line bg-page/60 py-1.5 text-center">
            <span class="text-[8px] font-bold uppercase tracking-[0.14em] text-navy-400">{{ $m['month'] ?? '—' }}</span>
            <span class="pf text-[17px] font-black leading-none text-navy-950">{{ $m['day'] ?? '··' }}</span>
            <span class="text-[8px] text-muted">{{ $m['year'] }}</span>
        </div>

        <div class="min-w-0 flex-1">
            <p class="line-clamp-2 text-[12.5px] font-bold leading-tight text-navy-950">{{ $m['name'] }}</p>
            <p class="mt-1 flex items-center gap-1 truncate text-[10px] text-muted"><x-icon name="pin" class="h-3 w-3 shrink-0 text-navy-300" />{{ $m['where'] }}</p>
            <p class="flex items-center gap-1 truncate text-[10px] text-muted"><x-icon name="users" class="h-3 w-3 shrink-0 text-navy-300" />{{ number_format($m['attendees']) }} {{ strtolower($m['attendeeWord']) }}</p>
        </div>
    </div>

    <div class="flex items-center gap-2.5 border-t border-line px-3 py-2.5">
        <x-mission.ring :percent="$m['progress']" :hex="$m['statusHex']" :size="42" />
        <div class="flex min-w-0 flex-1 -space-x-2">
            @foreach ($m['team'] as $member)
                <x-user-avatar :user="$member" size="h-6 w-6" class="ring-2 ring-white" />
            @endforeach
            @if ($m['teamMore'])
                <span class="grid h-6 w-6 place-items-center rounded-full bg-navy-50 text-[8.5px] font-bold text-navy-500 ring-2 ring-white">+{{ $m['teamMore'] }}</span>
            @endif
        </div>
        <span class="shrink-0 text-[10px] font-semibold text-navy-400 transition group-hover/side:text-indigo-600">Bring in →</span>
    </div>
</button>
