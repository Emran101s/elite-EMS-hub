@php
    $h = $health[$event->id];
    $m = $metrics[$event->id];
    $open = $expandedId === $event->id;
    $stageHex = \App\Models\Event::stageColor($event->stage);

    $today = now()->startOfDay();
    $start = $event->starts_at?->copy()->startOfDay();
    $end = ($event->ends_at ?? $event->starts_at)?->copy()->endOfDay();
    $isLive = $start && $end && $start->lte($today) && $end->gte($today);
    $days = $start ? (int) round($today->diffInDays($start, false)) : null;
    $starred = in_array($event->id, $favoriteIds);
    $budgetUsed = $m['budget_used'];
@endphp

<div wire:key="card-{{ $event->id }}"
     @class([
         'group relative flex flex-col overflow-hidden rounded-lg border bg-white transition-all duration-300',
         'col-span-full border-gold-400 shadow-float' => $open,
         'border-line shadow-raise hover:-translate-y-1 hover:border-navy-200 hover:shadow-float' => ! $open,
     ])>

    {{-- ══ overlay actions (siblings of the toggle button, never nested) ══ --}}
    <button type="button" wire:click="toggleFavorite({{ $event->id }})"
            class="absolute right-3 top-3 z-20 flex h-8 w-8 items-center justify-center rounded-lg backdrop-blur-sm transition {{ $starred ? 'bg-gold-400/90 text-navy-950' : 'bg-navy-950/40 text-white/80 hover:bg-navy-950/60 hover:text-white' }}"
            aria-label="Star event">
        <x-icon name="star" class="h-4 w-4 {{ $starred ? 'fill-current' : '' }}" />
    </button>

    {{-- ══ clickable dossier ══ --}}
    <button type="button" wire:click="toggleExpand({{ $event->id }})" class="block w-full flex-1 text-left">

        {{-- cover: crest / avatar under a dark wash, stage colour as the base glow --}}
        <div class="relative h-28 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-navy-800 to-navy-950"></div>
            @if ($event->cover_path)
                <x-event-avatar :event="$event" :ring="false" size="lg"
                    class="absolute inset-0 opacity-80 [&>span]:h-full [&>span]:w-full [&>span]:rounded-none [&>span]:!bg-transparent [&>span]:ring-0" />
            @else
                <x-event-crest :event="$event" class="absolute inset-0 h-full w-full opacity-70 mix-blend-luminosity" />
            @endif
            {{-- moody wash so every cover reads premium-dark, whatever the crest's own tones --}}
            <div class="absolute inset-0 bg-gradient-to-t from-navy-950 via-navy-950/60 to-navy-900/40"></div>
            <div class="absolute inset-x-0 bottom-0 h-1" style="background: {{ $stageHex }}"></div>

            {{-- stage + live, bottom-left over the wash --}}
            <div class="absolute inset-x-4 bottom-2.5 flex items-center gap-1.5">
                <span class="rounded-md px-2 py-0.5 text-eyebrow font-black uppercase tracking-wider text-white" style="background: {{ $stageHex }}">
                    {{ str($event->stage)->replace('_', ' ')->title() }}
                </span>
                @if ($isLive)
                    <span class="flex items-center gap-1 rounded-md bg-gold-400 px-2 py-0.5 text-eyebrow font-black uppercase tracking-wider text-navy-950">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-navy-950"></span>Live
                    </span>
                @endif
            </div>
            <span class="absolute right-3 top-3 mr-9 rounded-md bg-navy-950/40 px-1.5 py-0.5 text-eyebrow font-bold uppercase tracking-wider text-white/70 backdrop-blur-sm">
                {{ str($event->type)->replace('_', ' ')->title() }}
            </span>
        </div>

        {{-- identity + countdown --}}
        <div class="flex items-start gap-3 px-4 pt-3.5">
            <div class="min-w-0 flex-1">
                <p class="pf truncate text-base font-bold leading-tight text-navy-900">{{ $event->name }}</p>
                <p class="mt-0.5 truncate text-micro text-muted">{{ $event->client?->name ?? 'No client' }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-2.5">
                <div class="text-right">
                    <p class="text-base font-black leading-none {{ $isLive ? 'text-gold-600' : 'text-navy-900' }}">
                        {{ $isLive ? 'LIVE' : ($days === null ? '—' : ($days > 0 ? $days : abs($days))) }}
                    </p>
                    <p class="mt-0.5 text-eyebrow font-bold uppercase tracking-wider text-muted">
                        {{ $isLive ? 'now' : ($days === null ? 'no date' : ($days > 0 ? 'days to go' : 'days ago')) }}
                    </p>
                </div>
                <x-health-ring :percent="$h['score']" :group="$h['group']" size="h-12 w-12" textSize="text-micro" class="shrink-0" />
            </div>
        </div>

        {{-- where & when --}}
        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 px-4 text-micro text-muted">
            @if ($event->city)<span class="flex items-center gap-1"><x-icon name="pin" class="h-3 w-3 text-navy-300" />{{ $event->city }}</span>@endif
            @if ($event->starts_at)<span class="flex items-center gap-1"><x-icon name="calendar" class="h-3 w-3 text-navy-300" />{{ $event->starts_at->format('j M Y') }}</span>@endif
            @if ($event->venue)<span class="flex min-w-0 items-center gap-1"><x-icon name="building" class="h-3 w-3 shrink-0 text-navy-300" /><span class="truncate">{{ $event->venue->name }}</span></span>@endif
        </div>

        {{-- detail grid — the "lots of details" the card leads with --}}
        <div class="mt-3 grid grid-cols-3 gap-px overflow-hidden rounded-xl border border-line bg-line" style="margin-inline:1rem">
            @foreach ([
                ['Tasks', $event->tasks->count()],
                ['Team', $event->teamMembers->count()],
                ['Guests', $m['participants'] ? number_format($m['participants']) : '—'],
                ['Sponsors', $m['sponsors'] ?: '—'],
                ['Suppliers', $event->suppliers->count() ?: '—'],
                ['Budget', $budgetUsed !== null ? $budgetUsed.'%' : '—'],
            ] as [$l, $v])
                <div class="bg-white px-2.5 py-2 text-center">
                    <p class="text-sm font-black leading-none text-navy-900">{{ $v }}</p>
                    <p class="mt-1 text-eyebrow font-bold uppercase tracking-wider text-muted">{{ $l }}</p>
                </div>
            @endforeach
        </div>

        {{-- budget-used bar --}}
        @if ($budgetUsed !== null)
            @php $bc = $budgetUsed > 90 ? 'var(--color-danger)' : ($budgetUsed > 70 ? 'var(--color-warning)' : 'var(--color-success)'); @endphp
            <div class="mx-4 mt-3 flex items-center gap-2">
                <span class="text-eyebrow font-bold uppercase tracking-wider text-muted">Budget used</span>
                <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-navy-50"><span class="block h-full rounded-full" style="width: {{ min(100, $budgetUsed) }}%; background: {{ $bc }}"></span></span>
                <span class="text-eyebrow font-black" style="color: {{ $bc }}">{{ $budgetUsed }}%</span>
            </div>
        @endif

        <div class="px-4 pb-3.5 pt-3">
            <span class="flex items-center gap-1 text-eyebrow font-bold uppercase tracking-wider {{ $open ? 'text-gold-600' : 'text-navy-400' }}">
                {{ $open ? 'Hide details' : 'View full detail' }}
                <svg class="h-3 w-3 transition-transform {{ $open ? 'rotate-180' : '' }}" viewBox="0 0 20 20" fill="currentColor"><path d="M5 7l5 6 5-6H5z"/></svg>
            </span>
        </div>
    </button>

    {{-- ══ expanded detail (between body and footer, so the footer is always last) ══ --}}
    @if ($open && $expanded)
        @include('livewire.partials.events.detail', ['event' => $event, 'expanded' => $expanded])
    @endif

    {{-- ══ footer actions ══ --}}
    <div class="flex items-center gap-1 border-t border-line px-3 py-2">
        <a href="{{ route('events.hub', $event) }}" class="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-navy-50 py-2 text-micro font-bold text-navy-700 transition hover:bg-navy-100">
            <x-icon name="home" class="h-3.5 w-3.5" /> Open hub
        </a>
        <details class="relative" wire:key="cmenu-{{ $event->id }}">
            <summary class="flex h-9 w-9 cursor-pointer list-none items-center justify-center rounded-lg text-navy-400 transition hover:bg-navy-50 hover:text-navy-700 [&::-webkit-details-marker]:hidden" aria-label="Actions">
                <span class="rotate-90"><x-icon name="dots" class="h-4 w-4" /></span>
            </summary>
            <div class="absolute bottom-11 right-0 z-30 w-44 overflow-hidden rounded-xl border border-line bg-white shadow-float">
                <a href="{{ route('events.hub', [$event, 'tab' => 'settings']) }}" class="flex items-center gap-2.5 px-3.5 py-2.5 text-xs font-semibold text-navy-700 transition hover:bg-gold-50/60"><x-icon name="cog" class="h-3.5 w-3.5 text-navy-500" /> Edit</a>
                <button type="button" wire:click="duplicate({{ $event->id }})" class="flex w-full items-center gap-2.5 px-3.5 py-2.5 text-left text-xs font-semibold text-navy-700 transition hover:bg-gold-50/60"><x-icon name="archive" class="h-3.5 w-3.5 text-navy-500" /> Duplicate</button>
                <button type="button" wire:click="archive({{ $event->id }})" wire:confirm="Archive “{{ $event->name }}”?" class="flex w-full items-center gap-2.5 border-t border-line px-3.5 py-2.5 text-left text-xs font-semibold text-navy-700 transition hover:bg-navy-50"><x-icon name="logout" class="h-3.5 w-3.5 text-navy-500" /> Archive</button>
                @can('manage-events')
                    <button type="button" wire:click="deleteEvent({{ $event->id }})"
                            wire:confirm="Permanently DELETE “{{ $event->name }}”?&#10;&#10;This erases the event and everything in it. It cannot be undone."
                            class="flex w-full items-center gap-2.5 border-t border-line px-3.5 py-2.5 text-left text-xs font-semibold text-risk transition hover:bg-risk/5"><x-icon name="dots" class="h-3.5 w-3.5" /> Delete forever</button>
                @endcan
            </div>
        </details>
    </div>

</div>
