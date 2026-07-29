@props(['mission' => [], 'dark' => false, 'compact' => false])

{{--
    Budget · Tasks · Team · Risk · Timeline.

    The same five, in the same order, wherever a mission is shown at any size —
    on the hero card, in the list's detail panel, under the flight path. What
    changes between them is the scale, never the set.
--}}

@php
    $m = $mission;
    $ink = $dark ? 'text-white' : 'text-navy-950';
    $mute = $dark ? 'text-white/45' : 'text-muted';
    $label = $dark ? 'text-white/50' : 'text-navy-400';
    $rail = $dark ? 'bg-white/15' : 'bg-navy-50';
    $line = $dark ? 'divide-white/10' : 'divide-line';
    $num = $compact ? 'text-[14px]' : 'text-[17px]';
@endphp

<div {{ $attributes->merge(['class' => "grid grid-cols-2 divide-x divide-y $line sm:grid-cols-5 sm:divide-y-0"]) }}>

    <div class="px-3.5 py-3">
        <p class="text-eyebrow font-bold uppercase tracking-[0.14em] {{ $label }}">Budget</p>
        <p class="pf mt-1.5 {{ $num }} font-black leading-none {{ $ink }}">{{ $m['budgetLabel'] }}</p>
        <p class="mt-1 truncate text-[10px] {{ $mute }}">{{ $m['budgetOf'] }}</p>
        <div class="mt-2 h-[3px] overflow-hidden rounded-full {{ $rail }}">
            <div class="h-full rounded-full bg-blue-500" style="width: {{ $m['budgetPct'] ?? 0 }}%"></div>
        </div>
    </div>

    <div class="px-3.5 py-3">
        <p class="text-eyebrow font-bold uppercase tracking-[0.14em] {{ $label }}">Tasks</p>
        <p class="pf mt-1.5 {{ $num }} font-black leading-none {{ $ink }}">{{ $m['tasksTotal'] ? $m['tasksDone'].' / '.$m['tasksTotal'] : '—' }}</p>
        <p class="mt-1 truncate text-[10px] {{ $mute }}">{{ $m['tasksPct'] }}% completed</p>
        <div class="mt-2 h-[3px] overflow-hidden rounded-full {{ $rail }}">
            <div class="h-full rounded-full bg-indigo-500" style="width: {{ $m['tasksPct'] }}%"></div>
        </div>
    </div>

    <div class="px-3.5 py-3">
        <p class="text-eyebrow font-bold uppercase tracking-[0.14em] {{ $label }}">Team</p>
        <div class="mt-1.5 flex items-center -space-x-2">
            @forelse ($m['team'] as $member)
                <x-user-avatar :user="$member" size="h-7 w-7" class="ring-2 {{ $dark ? 'ring-navy-950' : 'ring-white' }}" />
            @empty
                <span class="text-[11px] {{ $mute }}">Unassigned</span>
            @endforelse
            @if ($m['teamMore'])
                <span class="grid h-7 w-7 place-items-center rounded-full text-[9px] font-bold ring-2 {{ $dark ? 'bg-white/15 text-white ring-navy-950' : 'bg-navy-50 text-navy-500 ring-white' }}">+{{ $m['teamMore'] }}</span>
            @endif
        </div>
        <p class="mt-1.5 truncate text-[10px] {{ $mute }}">{{ $m['teamCount'] }} {{ str('member')->plural($m['teamCount']) }}</p>
    </div>

    <div class="px-3.5 py-3">
        <p class="text-eyebrow font-bold uppercase tracking-[0.14em] {{ $label }}">Risk</p>
        <p class="pf mt-1.5 {{ $num }} font-black leading-none" style="color: {{ $m['riskHex'] }}">{{ $m['risk'] }}</p>
        <p class="mt-1 truncate text-[10px] {{ $mute }}">{{ $m['riskCount'] ? $m['riskCount'].' open on the register' : 'Register clear' }}</p>
        {{-- A pulse line rather than a bar: risk is a level, not a quantity. --}}
        <svg class="mt-1.5 h-3 w-full" viewBox="0 0 80 12" preserveAspectRatio="none" aria-hidden="true">
            <path d="M0 6 H22 l4 -5 l5 10 l4 -8 l4 3 H80" fill="none" stroke="{{ $m['riskHex'] }}" stroke-width="1.4" stroke-linejoin="round" opacity=".85" />
        </svg>
    </div>

    <div class="px-3.5 py-3">
        <p class="text-eyebrow font-bold uppercase tracking-[0.14em] {{ $label }}">Timeline</p>
        <p class="pf mt-1.5 {{ $num }} font-black leading-none {{ $ink }}">{{ $m['shortDates'] }}</p>
        <p class="mt-1 truncate text-[10px] {{ $mute }}">{{ $m['timeline'] }}</p>
        <div class="mt-2 h-[3px] overflow-hidden rounded-full {{ $rail }}">
            <div class="h-full rounded-full" style="width: {{ $m['progress'] }}%; background: {{ $m['statusHex'] }}"></div>
        </div>
    </div>
</div>
