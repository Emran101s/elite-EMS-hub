@props(['mission'])

{{--
    The command dock. Seven ways into the mission, on the one dark surface the
    card allows itself — this is the "important command area" the palette
    reserves navy for.
--}}

@php
    $event = $mission['event'];
    $actions = [
        ['overview', 'Overview', 'home'],
        ['budget', 'Budget', 'currency'],
        ['tasks', 'Tasks', 'clipboard'],
        ['team', 'Team', 'users'],
        ['agenda', 'Timeline', 'calendar'],
        ['files', 'Documents', 'archive'],
        ['reports', 'Insights', 'chart'],
    ];
@endphp

<div {{ $attributes->merge(['class' => 'scrollbar-none flex items-stretch gap-1 overflow-x-auto bg-navy-950 px-3 py-2.5']) }}>
    @foreach ($actions as [$tab, $label, $icon])
        @php $on = $tab === 'overview' || $event->moduleEnabled($tab); @endphp

        @if ($on)
            <a href="{{ route('events.hub', [$event, 'tab' => $tab]) }}"
               class="group/dock flex min-w-[68px] flex-1 flex-col items-center gap-1.5 rounded-xl px-2 py-2 transition hover:bg-white/10">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-white/10 text-white/70 transition group-hover/dock:bg-gold-400 group-hover/dock:text-navy-950">
                    <x-icon :name="$icon" class="h-4 w-4" />
                </span>
                <span class="text-[9.5px] font-semibold text-white/60 transition group-hover/dock:text-white">{{ $label }}</span>
            </a>
        @else
            <span class="flex min-w-[68px] flex-1 cursor-not-allowed flex-col items-center gap-1.5 rounded-xl px-2 py-2 opacity-35"
                  title="{{ $label }} is switched off for this event">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-white/5 text-white/40">
                    <x-icon :name="$icon" class="h-4 w-4" />
                </span>
                <span class="text-[9.5px] font-semibold text-white/40">{{ $label }}</span>
            </span>
        @endif
    @endforeach
</div>
