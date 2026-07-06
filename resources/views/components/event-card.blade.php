@props(['event', 'health', 'metrics', 'selected' => false, 'favorite' => false])

<div wire:click="select({{ $event->id }})"
     @class([
         'group relative flex h-[350px] w-[250px] cursor-pointer flex-col rounded-3xl border bg-white p-3 transition duration-300',
         'border-gold-400 shadow-[0_18px_40px_rgba(212,175,55,0.16)] ring-1 ring-gold-300' => $selected,
         'border-line shadow-[0_12px_30px_rgba(15,23,42,0.06)] hover:-translate-y-1 hover:border-[rgba(212,175,55,0.45)] hover:shadow-[0_18px_40px_rgba(15,23,42,0.10)]' => ! $selected,
     ])>

    {{-- Avatar visual --}}
    <div class="relative h-[150px] shrink-0 overflow-hidden rounded-2xl bg-page">
        <x-event-avatar :event="$event" :ring="false" size="xl"
                        class="block h-full w-full transition duration-500 group-hover:scale-[1.04] [&>span]:h-full [&>span]:w-full [&>span]:rounded-none [&>span]:!bg-transparent [&>span]:ring-0" />
        <button type="button" wire:click.stop="toggleFavorite({{ $event->id }})"
                class="absolute right-2 top-2 flex h-7 w-7 items-center justify-center rounded-full bg-white/95 shadow ring-1 backdrop-blur transition {{ $favorite ? 'text-gold-500 ring-gold-300' : 'text-navy-300 ring-line hover:text-gold-500' }} {{ $favorite || $selected ? '' : 'opacity-0 group-hover:opacity-100' }}"
                aria-label="{{ $favorite ? 'Unstar' : 'Star' }} event">
            <x-icon name="star" class="h-3.5 w-3.5 {{ $favorite ? 'fill-current' : '' }}" />
        </button>
    </div>

    <div class="flex min-h-0 flex-1 flex-col px-0.5 pt-2.5">
        <div class="flex items-start gap-2.5">
            {{-- Health ring --}}
            <x-health-ring :percent="$health['score']" :group="$health['group']" size="h-14 w-14"
                           dark textSize="text-[14px]" class="mt-0.5 shrink-0" />

            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-1.5">
                    <a href="{{ route('events.hub', $event) }}" wire:click.stop
                       class="truncate text-[16px] font-bold leading-tight text-navy-900 hover:text-gold-700">{{ $event->name }}</a>

                    {{-- Kebab menu --}}
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
                                    class="flex w-full items-center gap-2.5 border-t border-line px-3.5 py-2.5 text-left text-xs font-semibold text-risk transition hover:bg-risk/5">
                                <x-icon name="logout" class="h-3.5 w-3.5" /> Archive
                            </button>
                        </div>
                    </details>
                </div>
                <p class="truncate text-[11px] text-muted">{{ $event->avatar?->name ?? str($event->type)->replace('_', ' ')->title() }}</p>

                <p class="mt-1 flex items-center gap-1 text-[11px] text-muted">
                    <x-icon name="pin" class="h-3 w-3 shrink-0 text-navy-400" /> {{ $event->city }}, {{ $event->country }}
                </p>
                <p class="mt-0.5 flex items-center gap-1 text-[11px] text-muted">
                    <x-icon name="calendar" class="h-3 w-3 shrink-0 text-navy-400" />
                    {{ $event->starts_at?->format('M j') }}{{ $event->ends_at && ! $event->ends_at->isSameDay($event->starts_at) ? ' – '.$event->ends_at->format('j, Y') : ', '.$event->starts_at?->format('Y') }}
                </p>
            </div>
        </div>

        <div class="mt-1.5 flex justify-end">
            <x-status-badge :status="$health['status']" class="!px-2 !py-0.5 !text-[9px] uppercase" />
        </div>

        {{-- Metrics row --}}
        <dl class="mt-auto grid h-[46px] grid-cols-3 items-center gap-1.5 border-t border-line pt-2">
            @foreach ([
                ['icon' => 'users', 'value' => $metrics['participants'] ? number_format($metrics['participants']) : '—', 'label' => str('Participant')->plural($metrics['participants'] ?? 2)],
                ['icon' => 'star', 'value' => $metrics['sponsors'], 'label' => str('Sponsor')->plural($metrics['sponsors'])],
                ['icon' => 'currency', 'value' => $metrics['budget_used'] !== null ? $metrics['budget_used'].'%' : '—', 'label' => 'Budget Used', 'risk' => ($metrics['budget_used'] ?? 0) > 85],
            ] as $metric)
                <div class="min-w-0">
                    <dd class="flex items-center gap-1 text-[11px] font-bold {{ ($metric['risk'] ?? false) ? 'text-risk' : 'text-navy-900' }}">
                        <span class="shrink-0 text-navy-500"><x-icon :name="$metric['icon']" class="h-3 w-3" /></span>
                        <span class="truncate">{{ $metric['value'] }}</span>
                    </dd>
                    <dt class="mt-0.5 truncate text-[9px] text-muted">{{ $metric['label'] }}</dt>
                </div>
            @endforeach
        </dl>
    </div>
</div>
