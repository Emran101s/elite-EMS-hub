@props(['mission'])

{{--
    The command dock. Seven ways into the mission, on the one dark surface the
    card allows itself — this is the "important command area" the palette
    reserves navy for.

    Seven need 507px. A narrow plate has under 300, and because this row is a
    scroller with no scrollbar, the last three were not hidden so much as
    silently unreachable — a dock you cannot see the end of is a dock with four
    actions and a secret. The last three are marked so the deck can drop them
    outright at that size: four you can see beats seven you cannot.
--}}

@php
    $event = $mission['event'];

    // [tab, label, icon, secondary?]
    $actions = [
        ['overview', 'Overview', 'home', false],
        ['budget', 'Budget', 'currency', false],
        ['tasks', 'Tasks', 'clipboard', false],
        ['team', 'Team', 'users', false],
        ['agenda', 'Timeline', 'calendar', true],
        ['files', 'Documents', 'archive', true],
        ['reports', 'Insights', 'chart', true],
    ];
@endphp

<div {{ $attributes->merge(['class' => 'scrollbar-none flex items-stretch gap-1 overflow-x-auto bg-navy-950 px-3 py-2.5']) }}>
    @foreach ($actions as [$tab, $label, $icon, $secondary])
        @php $on = $tab === 'overview' || $event->moduleEnabled($tab); @endphp

        @if ($on)
            <a href="{{ route('events.hub', [$event, 'tab' => $tab]) }}"
               @if ($secondary) data-deck-part="dock-extra" @endif
               class="group/dock flex min-w-[68px] flex-1 flex-col items-center gap-1.5 rounded-xl px-2 py-2 transition hover:bg-white/10">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-white/10 text-white/70 transition group-hover/dock:bg-gold-400 group-hover/dock:text-navy-950">
                    <x-icon :name="$icon" class="h-4 w-4" />
                </span>
                <span class="text-[9.5px] font-semibold text-white/60 transition group-hover/dock:text-white">{{ $label }}</span>
            </a>
        @else
            <span @if ($secondary) data-deck-part="dock-extra" @endif
                  class="flex min-w-[68px] flex-1 cursor-not-allowed flex-col items-center gap-1.5 rounded-xl px-2 py-2 opacity-35"
                  title="{{ $label }} is switched off for this event">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-white/5 text-white/40">
                    <x-icon :name="$icon" class="h-4 w-4" />
                </span>
                <span class="text-[9.5px] font-semibold text-white/40">{{ $label }}</span>
            </span>
        @endif
    @endforeach
</div>
