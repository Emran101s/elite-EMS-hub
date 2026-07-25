@props(['event', 'health', 'metrics', 'selected' => false, 'favorite' => false])

@php
    $stageHex = [
        'draft' => '#94A3B8', 'proposal' => '#3B82F6', 'confirmed' => '#06B6D4', 'planning' => '#8B5CF6',
        'production' => '#D4AF37', 'live' => '#22C55E', 'completed' => '#10B981', 'closed' => '#64748B',
        'cancelled' => '#F87171', 'on_hold' => '#F59E0B',
    ][$event->stage] ?? '#64748B';

    $today = now()->startOfDay();
    $start = $event->starts_at?->copy()->startOfDay();
    $end = ($event->ends_at ?? $event->starts_at)?->copy()->endOfDay();
    $isLive = $start && $end && $start->lte($today) && $end->gte($today);
    $days = $start ? (int) round($today->diffInDays($start, false)) : null;

    $used = $metrics['budget_used'];
    $barHex = $used === null ? '#CBD5E1' : ($used > 90 ? '#EF4444' : ($used > 70 ? '#F59E0B' : '#10B981'));
@endphp

<div wire:click="select({{ $event->id }})"
     @class([
         'group relative flex h-[318px] w-full cursor-pointer flex-col overflow-hidden rounded-3xl border bg-white transition duration-300',
         'border-gold-400 shadow-[0_18px_40px_rgba(212,175,55,0.16)] ring-1 ring-gold-300' => $selected,
         'border-line shadow-[0_12px_30px_rgba(15,23,42,0.06)] hover:-translate-y-1 hover:border-[rgba(212,175,55,0.45)] hover:shadow-[0_18px_40px_rgba(15,23,42,0.12)]' => ! $selected,
     ])>

    {{-- stage edge --}}
    <span class="absolute inset-x-0 top-0 z-20 h-1" style="background: {{ $stageHex }}" title="{{ str($event->stage)->replace('_', ' ')->title() }}"></span>

    {{-- visual: the crest sits on a branded navy band, so pale logos still read --}}
    <div class="relative h-[124px] shrink-0 overflow-hidden bg-gradient-to-br from-navy-800 to-[#061225]">
        <div class="pointer-events-none absolute -right-7 -top-9 h-28 w-28 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.30),transparent_70%)]"></div>

        @if ($event->cover_path)
            <div class="relative flex h-full w-full items-center justify-center p-4 transition duration-500 group-hover:scale-105">
                <x-event-avatar :event="$event" :ring="false" size="xl"
                                class="h-full w-full [&>span]:h-full [&>span]:w-full [&>span]:rounded-xl [&>span]:!bg-transparent [&>span]:ring-0" />
            </div>
        @else
            <x-event-crest :event="$event" class="h-full w-full transition duration-500 group-hover:scale-105" />
        @endif

        @if ($start)
            <span @class([
                'absolute left-2.5 top-3 flex items-center gap-1 rounded-full px-2 py-1 text-[0.56rem] font-black uppercase tracking-wider shadow-sm backdrop-blur',
                'bg-gold-400 text-navy-950' => $isLive,
                'bg-white/95 text-navy-700' => ! $isLive,
            ])>
                @if ($isLive)
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-navy-950"></span> Live
                @elseif ($days > 0)
                    in {{ $days }}d
                @elseif ($days === 0)
                    Today
                @else
                    Done
                @endif
            </span>
        @endif

        <button type="button" wire:click.stop="toggleFavorite({{ $event->id }})"
                class="absolute right-2 top-2.5 flex h-7 w-7 items-center justify-center rounded-full bg-white/95 shadow ring-1 backdrop-blur transition {{ $favorite ? 'text-gold-700 ring-gold-300' : 'text-navy-300 ring-line hover:text-gold-700' }} {{ $favorite || $selected ? '' : 'opacity-0 group-hover:opacity-100' }}"
                aria-label="{{ $favorite ? 'Unstar' : 'Star' }} event">
            <x-icon name="star" class="h-3.5 w-3.5 {{ $favorite ? 'fill-current' : '' }}" />
        </button>
    </div>

    <div class="flex min-h-0 flex-1 flex-col p-3.5">
        <div class="flex items-start gap-2.5">
            <x-health-ring :percent="$health['score']" :group="$health['group']" size="h-12 w-12"
                           dark textSize="text-[13px]" class="mt-0.5 shrink-0" />

            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-1.5">
                    <a href="{{ route('events.hub', $event) }}" wire:click.stop
                       class="truncate text-[15px] font-bold leading-tight text-navy-900 transition hover:text-gold-700">{{ $event->name }}</a>

                    <details class="relative shrink-0" wire:click.stop>
                        <summary class="mt-0.5 flex cursor-pointer list-none text-navy-400 transition hover:text-navy-700 [&::-webkit-details-marker]:hidden" aria-label="Card actions">
                            <span class="rotate-90"><x-icon name="dots" class="h-3.5 w-3.5" /></span>
                        </summary>
                        <div class="absolute right-0 top-6 z-30 w-40 overflow-hidden rounded-xl border border-line bg-white shadow-lg">
                            <a href="{{ route('events.hub', $event) }}" class="flex items-center gap-2.5 px-3.5 py-2.5 text-xs font-semibold text-navy-700 transition hover:bg-gold-50/60">
                                <x-icon name="home" class="h-3.5 w-3.5 text-navy-500" /> Open Hub
                            </a>
                            <a href="{{ route('events.hub', [$event, 'tab' => 'settings']) }}" class="flex items-center gap-2.5 px-3.5 py-2.5 text-xs font-semibold text-navy-700 transition hover:bg-gold-50/60">
                                <x-icon name="cog" class="h-3.5 w-3.5 text-navy-500" /> Edit
                            </a>
                            <button type="button" wire:click.stop="duplicate({{ $event->id }})" class="flex w-full items-center gap-2.5 px-3.5 py-2.5 text-left text-xs font-semibold text-navy-700 transition hover:bg-gold-50/60">
                                <x-icon name="archive" class="h-3.5 w-3.5 text-navy-500" /> Duplicate
                            </button>
                            <button type="button" wire:click.stop="archive({{ $event->id }})"
                                    wire:confirm="Archive “{{ $event->name }}”? It will disappear from lists and the Operations Hub."
                                    class="flex w-full items-center gap-2.5 border-t border-line px-3.5 py-2.5 text-left text-xs font-semibold text-navy-700 transition hover:bg-gold-50/60">
                                <x-icon name="logout" class="h-3.5 w-3.5 text-navy-500" /> Archive
                            </button>
                            @can('manage-events')
                                <button type="button" wire:click.stop="deleteEvent({{ $event->id }})"
                                        wire:confirm="Permanently DELETE “{{ $event->name }}”?&#10;&#10;This erases the event and everything in it — plan, tasks, agenda, budget, suppliers, files. It cannot be undone.&#10;&#10;Archive instead if you just want it out of the way."
                                        class="flex w-full items-center gap-2.5 border-t border-line px-3.5 py-2.5 text-left text-xs font-semibold text-risk transition hover:bg-risk/5">
                                    <x-icon name="dots" class="h-3.5 w-3.5" /> Delete forever
                                </button>
                            @endcan
                        </div>
                    </details>
                </div>
                <p class="mt-0.5 truncate text-[11px] text-muted">{{ $event->client?->name ?? str($event->type)->replace('_', ' ')->title() }}</p>
            </div>
        </div>

        <p class="mt-2.5 flex items-center gap-3 truncate text-[11px] text-muted">
            <span class="flex min-w-0 items-center gap-1"><x-icon name="pin" class="h-3 w-3 shrink-0 text-navy-400" /><span class="truncate">{{ $event->city }}</span></span>
            <span class="flex shrink-0 items-center gap-1"><x-icon name="calendar" class="h-3 w-3 text-navy-400" />{{ $event->starts_at?->format('j M Y') }}</span>
        </p>

        {{-- budget + footer --}}
        <div class="mt-auto">
            <div class="mb-1 flex items-baseline justify-between">
                <span class="text-[0.56rem] font-bold uppercase tracking-wider text-muted">Budget used</span>
                <span class="text-[0.72rem] font-black" style="color: {{ $used === null ? '#94A3B8' : $barHex }}">{{ $used !== null ? $used.'%' : '—' }}</span>
            </div>
            <div class="h-1.5 overflow-hidden rounded-full bg-navy-50">
                <div class="h-full rounded-full transition-all" style="width: {{ min($used ?? 0, 100) }}%; background: {{ $barHex }}"></div>
            </div>

            <div class="mt-2.5 flex items-center gap-3 border-t border-line pt-2.5 text-[11px] text-muted">
                <span class="flex items-center gap-1" title="Expected participants"><x-icon name="users" class="h-3 w-3 text-navy-400" /><b class="font-bold text-navy-800">{{ $metrics['participants'] ? number_format($metrics['participants']) : '—' }}</b></span>
                <span class="flex items-center gap-1" title="Sponsors"><x-icon name="star" class="h-3 w-3 text-navy-400" /><b class="font-bold text-navy-800">{{ $metrics['sponsors'] }}</b></span>
                <x-status-badge :status="$health['status']" class="ml-auto !px-2 !py-0.5 !text-[9px] uppercase" />
            </div>
        </div>
    </div>
</div>
