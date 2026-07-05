@props(['event', 'health', 'metrics', 'selected' => false])

<div wire:click="select({{ $event->id }})"
     @class([
         'group relative cursor-pointer overflow-hidden rounded-3xl border bg-white transition duration-300',
         'border-gold-400 shadow-[0_10px_30px_rgba(212,175,55,0.18)] ring-1 ring-gold-300' => $selected,
         'border-line shadow-[0_1px_3px_rgba(11,31,58,0.05)] hover:-translate-y-0.5 hover:border-gold-300 hover:shadow-[0_16px_40px_rgba(11,31,58,0.12)]' => ! $selected,
     ])>

    {{-- Avatar visual (full-bleed top) --}}
    <div class="relative overflow-hidden">
        <x-event-avatar :event="$event" :ring="false" size="xl"
                        class="block w-full transition duration-500 group-hover:scale-[1.03] [&>span]:h-56 [&>span]:w-full [&>span]:rounded-none [&>span]:bg-white [&>span]:ring-0" />
        <button type="button" wire:click.stop
                class="absolute right-4 top-4 flex h-9 w-9 items-center justify-center rounded-full bg-white/95 text-gold-500 shadow ring-1 ring-gold-300 backdrop-blur transition hover:text-gold-600 {{ $selected ? '' : 'opacity-0 group-hover:opacity-100' }}"
                aria-label="Favorite">
            <x-icon name="star" class="h-4.5 w-4.5" />
        </button>
    </div>

    <div class="px-6 pb-6 pt-1">
        <div class="flex items-start gap-4">
            {{-- Health ring --}}
            <x-health-ring :percent="$health['score']" :group="$health['group']" size="h-[4.5rem] w-[4.5rem]"
                           dark textSize="text-base" class="mt-1 shrink-0" />

            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                    <a href="{{ route('events.hub', $event) }}" wire:click.stop
                       class="truncate text-xl font-bold text-navy-900 hover:text-gold-700">{{ $event->name }}</a>
                    <button type="button" wire:click.stop class="mt-1 shrink-0 rotate-90 text-navy-400 transition hover:text-navy-700" aria-label="Actions">
                        <x-icon name="dots" class="h-4.5 w-4.5" />
                    </button>
                </div>
                <p class="truncate text-sm text-muted">{{ $event->avatar?->name ?? str($event->type)->replace('_', ' ')->title() }}</p>

                <p class="mt-2 flex items-center gap-2 text-sm text-navy-700">
                    <x-icon name="pin" class="h-4 w-4 shrink-0 text-navy-400" /> {{ $event->city }}, {{ $event->country }}
                </p>
                <div class="mt-1.5 flex items-center justify-between gap-2">
                    <p class="flex items-center gap-2 text-sm text-navy-700">
                        <x-icon name="calendar" class="h-4 w-4 shrink-0 text-navy-400" />
                        {{ $event->starts_at?->format('M j') }}{{ $event->ends_at && ! $event->ends_at->isSameDay($event->starts_at) ? ' – '.$event->ends_at->format('j, Y') : ', '.$event->starts_at?->format('Y') }}
                    </p>
                    <x-status-badge :status="$health['status']" class="!text-[0.62rem] uppercase" />
                </div>
            </div>
        </div>

        {{-- Metrics row --}}
        <dl class="mt-5 grid grid-cols-3 gap-2 border-t border-line pt-4">
            @foreach ([
                ['icon' => 'users', 'value' => $metrics['participants'] ? number_format($metrics['participants']) : '—', 'label' => str('Participant')->plural($metrics['participants'] ?? 2)],
                ['icon' => 'star', 'value' => $metrics['sponsors'], 'label' => str('Sponsor')->plural($metrics['sponsors'])],
                ['icon' => 'currency', 'value' => $metrics['budget_used'] !== null ? $metrics['budget_used'].'%' : '—', 'label' => 'Budget Used', 'risk' => ($metrics['budget_used'] ?? 0) > 85],
            ] as $metric)
                <div>
                    <dd class="flex items-center gap-2 text-base font-bold {{ ($metric['risk'] ?? false) ? 'text-risk' : 'text-navy-900' }}">
                        <span class="text-navy-500"><x-icon :name="$metric['icon']" class="h-4.5 w-4.5" /></span>
                        {{ $metric['value'] }}
                    </dd>
                    <dt class="mt-1 text-xs text-muted">{{ $metric['label'] }}</dt>
                </div>
            @endforeach
        </dl>
    </div>
</div>
