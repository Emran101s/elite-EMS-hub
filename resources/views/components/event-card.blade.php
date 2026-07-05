@props(['event', 'health', 'metrics', 'selected' => false])

<div wire:click="select({{ $event->id }})"
     @class([
         'group relative cursor-pointer rounded-3xl border bg-white p-3.5 transition duration-300',
         'border-gold-400 shadow-[0_18px_40px_rgba(212,175,55,0.16)] ring-1 ring-gold-300' => $selected,
         'border-line shadow-[0_12px_30px_rgba(15,23,42,0.06)] hover:-translate-y-1 hover:border-[rgba(212,175,55,0.45)] hover:shadow-[0_18px_40px_rgba(15,23,42,0.10)]' => ! $selected,
     ])>

    {{-- Avatar visual: 165px, radius 18 --}}
    <div class="relative h-[165px] overflow-hidden rounded-[18px] bg-page">
        <x-event-avatar :event="$event" :ring="false" size="xl"
                        class="block h-full w-full transition duration-500 group-hover:scale-[1.04] [&>span]:h-full [&>span]:w-full [&>span]:rounded-none [&>span]:bg-white [&>span]:ring-0" />
        <button type="button" wire:click.stop
                class="absolute right-2.5 top-2.5 flex h-8 w-8 items-center justify-center rounded-full bg-white/95 text-gold-500 shadow ring-1 ring-gold-300 backdrop-blur transition hover:text-gold-600 {{ $selected ? '' : 'opacity-0 group-hover:opacity-100' }}"
                aria-label="Favorite">
            <x-icon name="star" class="h-4 w-4" />
        </button>
    </div>

    <div class="px-1.5 pb-1 pt-3">
        <div class="flex items-start gap-3.5">
            {{-- Health ring 62px --}}
            <x-health-ring :percent="$health['score']" :group="$health['group']" size="h-[62px] w-[62px]"
                           dark textSize="text-[16px]" class="mt-0.5 shrink-0" />

            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                    <a href="{{ route('events.hub', $event) }}" wire:click.stop
                       class="truncate text-[18px] font-bold text-navy-900 hover:text-gold-700">{{ $event->name }}</a>
                    <button type="button" wire:click.stop class="mt-1 shrink-0 rotate-90 text-navy-400 transition hover:text-navy-700" aria-label="Actions">
                        <x-icon name="dots" class="h-4 w-4" />
                    </button>
                </div>
                <p class="truncate text-xs text-muted">{{ $event->avatar?->name ?? str($event->type)->replace('_', ' ')->title() }}</p>

                <p class="mt-1.5 flex items-center gap-1.5 text-[12px] text-muted">
                    <x-icon name="pin" class="h-3.5 w-3.5 shrink-0 text-navy-400" /> {{ $event->city }}, {{ $event->country }}
                </p>
                <div class="mt-1 flex items-center justify-between gap-2">
                    <p class="flex items-center gap-1.5 text-[12px] text-muted">
                        <x-icon name="calendar" class="h-3.5 w-3.5 shrink-0 text-navy-400" />
                        {{ $event->starts_at?->format('M j') }}{{ $event->ends_at && ! $event->ends_at->isSameDay($event->starts_at) ? ' – '.$event->ends_at->format('j, Y') : ', '.$event->starts_at?->format('Y') }}
                    </p>
                    <x-status-badge :status="$health['status']" class="!px-2 !py-0.5 !text-[10px] uppercase" />
                </div>
            </div>
        </div>

        {{-- Metrics row (52px) --}}
        <dl class="mt-3.5 grid h-[52px] grid-cols-3 items-center gap-2 border-t border-line pt-3">
            @foreach ([
                ['icon' => 'users', 'value' => $metrics['participants'] ? number_format($metrics['participants']) : '—', 'label' => str('Participant')->plural($metrics['participants'] ?? 2)],
                ['icon' => 'star', 'value' => $metrics['sponsors'], 'label' => str('Sponsor')->plural($metrics['sponsors'])],
                ['icon' => 'currency', 'value' => $metrics['budget_used'] !== null ? $metrics['budget_used'].'%' : '—', 'label' => 'Budget Used', 'risk' => ($metrics['budget_used'] ?? 0) > 85],
            ] as $metric)
                <div>
                    <dd class="flex items-center gap-1.5 text-[12px] font-bold {{ ($metric['risk'] ?? false) ? 'text-risk' : 'text-navy-900' }}">
                        <span class="text-navy-500"><x-icon :name="$metric['icon']" class="h-3.5 w-3.5" /></span>
                        {{ $metric['value'] }}
                    </dd>
                    <dt class="mt-0.5 text-[10px] text-muted">{{ $metric['label'] }}</dt>
                </div>
            @endforeach
        </dl>
    </div>
</div>
