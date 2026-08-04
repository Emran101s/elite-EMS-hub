@props(['mission'])

{{--
    The detail panel: the hero card's facts, laid horizontally.

    List View and Flight Path both use it, and both update it in place rather
    than opening a modal — the whole point of picking a row is to keep the rest
    of the book in view while you read one of it.
--}}

@php
    $m = $mission;
    $event = $m['event'];

    $quick = [
        ['budget', 'Budget Overview', 'currency'],
        ['tasks', 'Task Report', 'clipboard'],
        ['team', 'Team Workload', 'users'],
        ['agenda', 'Timeline Details', 'calendar'],
        ['files', 'Documents', 'archive'],
        ['reports', 'Event Insights', 'chart'],
    ];
@endphp

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-[22px] border border-gold-200/70 bg-white shadow-[0_20px_50px_-32px_rgba(11,31,58,0.45)] ring-1 ring-navy-950/5']) }}>
    <div class="grid gap-0 xl:grid-cols-[280px_minmax(0,1fr)_236px]">

        {{-- ── the picture ── --}}
        <div class="relative isolate min-h-[190px] overflow-hidden xl:min-h-full">
            @if ($m['cover'])
                <img src="{{ $m['cover'] }}" alt="" class="absolute inset-0 -z-10 h-full w-full object-cover" style="object-position: 50% 40%">
            @else
                <x-event-crest :event="$event" class="absolute inset-0 -z-10 h-full w-full" />
            @endif
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-navy-950/70 via-navy-950/10 to-navy-950/40"></div>

            <div class="flex h-full flex-col justify-between p-4">
                <x-mission.badge :mission="$m" class="self-start !bg-white/95 !ring-white/40" />

                <a href="{{ route('events.hub', $event) }}"
                   class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-navy-900 to-navy-950 px-4 text-[12px] font-bold text-white shadow-lg ring-1 ring-gold-400/30 transition hover:shadow-[0_10px_24px_-8px_rgba(212,175,55,0.5)]">
                    Open Event <span aria-hidden="true" class="text-gold-400">↗</span>
                </a>
            </div>
        </div>

        {{-- ── who, where, when, and what the rules make of it ── --}}
        <div class="min-w-0 border-y border-line xl:border-x xl:border-y-0">
            <div class="flex flex-wrap items-start gap-4 p-4 lg:p-5">
                {{-- the date block --}}
                <div class="grid w-[74px] shrink-0 place-items-center rounded-2xl border border-gold-200/70 bg-page/70 py-2.5 text-center">
                    <span class="text-eyebrow font-bold uppercase tracking-[0.16em] text-gold-600">{{ $m['month'] ?? '—' }}</span>
                    <span class="pf text-[26px] font-black leading-none text-navy-950">{{ $m['day'] ?? '··' }}</span>
                    <span class="text-[10px] text-muted">{{ $m['year'] }}</span>
                </div>

                <div class="min-w-0 flex-1">
                    <h3 class="pf text-[20px] font-black leading-tight text-navy-950">
                        <a href="{{ route('events.hub', $event) }}" class="transition hover:text-gold-700">{{ $m['name'] }}</a>
                    </h3>

                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11.5px] text-navy-600">
                        <span class="flex items-center gap-1.5"><x-icon name="pin" class="h-3.5 w-3.5 text-navy-300" />{{ $m['where'] }}</span>
                        <span class="flex items-center gap-1.5"><x-icon name="calendar" class="h-3.5 w-3.5 text-navy-300" />{{ $m['dates'] }}</span>
                        <span class="flex items-center gap-1.5"><x-icon name="users" class="h-3.5 w-3.5 text-navy-300" />{{ number_format($m['attendees']) }} {{ strtolower($m['attendeeWord']) }}</span>
                    </div>

                    @if ($m['description'])
                        <p class="mt-2.5 max-w-[52ch] text-[12px] leading-relaxed text-muted">{{ $m['description'] }}</p>
                    @endif
                </div>

                <x-mission.ring :percent="$m['progress']" :hex="$m['statusHex']" :size="72" label="Overall" class="shrink-0" />
            </div>

            <x-mission.kpis :mission="$m" class="border-t border-line" />

            {{-- what happens next, and the one line about it --}}
            <div class="grid divide-y divide-line border-t border-line sm:grid-cols-2 sm:divide-x sm:divide-y-0">
                <div class="px-4 py-3">
                    <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-navy-400">Next milestone</p>
                    <p class="pf mt-1 truncate text-[13px] font-bold text-navy-950">{{ $m['milestone']['title'] }}</p>
                    <p class="mt-0.5 text-[11px] {{ $m['milestone']['overdue'] ? 'font-semibold text-red-600' : 'text-muted' }}">{{ $m['milestone']['due'] }}</p>
                </div>

                <div class="bg-gold-50/40 px-4 py-3">
                    <p class="flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.14em] text-gold-700">
                        AI insight
                        <x-icon name="sparkles" class="ms-auto h-3.5 w-3.5 text-gold-500" />
                    </p>
                    <p class="mt-1 text-[12px] leading-relaxed text-navy-700">{{ $m['insight'] }}</p>
                </div>
            </div>
        </div>

        {{-- ── the way into each module ── --}}
        <div class="p-4">
            <p class="pf text-[13px] font-bold text-navy-950">Quick actions</p>
            <div class="mt-2.5 space-y-0.5">
                @foreach ($quick as [$tab, $label, $icon])
                    @php $on = $tab === 'overview' || $event->moduleEnabled($tab); @endphp
                    @unless ($on)
                        <span class="flex cursor-not-allowed items-center gap-2.5 rounded-xl px-2 py-2 text-[12px] font-semibold text-navy-300"
                              title="{{ $label }} is switched off for this event">
                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-page/60 text-navy-200">
                                <x-icon :name="$icon" class="h-3.5 w-3.5" />
                            </span>
                            {{ $label }}
                        </span>
                        @continue
                    @endunless
                    <a href="{{ route('events.hub', [$event, 'tab' => $tab]) }}"
                       class="group/qa flex items-center gap-2.5 rounded-xl px-2 py-2 text-[12px] font-semibold text-navy-600 transition hover:bg-gold-50 hover:text-navy-950">
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-page text-navy-400 transition group-hover/qa:bg-navy-950 group-hover/qa:text-gold-400">
                            <x-icon :name="$icon" class="h-3.5 w-3.5" />
                        </span>
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>
