@props(['event', 'health', 'metrics', 'selected' => false])

<div wire:click="select({{ $event->id }})"
     @class([
         'group relative cursor-pointer overflow-hidden rounded-3xl border bg-white transition duration-300',
         'border-gold-400 shadow-[0_10px_30px_rgba(212,175,55,0.18)] ring-1 ring-gold-300' => $selected,
         'border-line shadow-[0_1px_3px_rgba(11,31,58,0.05)] hover:-translate-y-0.5 hover:border-gold-300 hover:shadow-[0_16px_40px_rgba(11,31,58,0.12)]' => ! $selected,
     ])>

    {{-- Avatar visual --}}
    <div class="relative m-3 overflow-hidden rounded-2xl bg-page">
        <x-event-avatar :event="$event" :ring="false" size="xl"
                        class="block w-full transition duration-500 group-hover:scale-[1.04] [&>span]:h-44 [&>span]:w-full [&>span]:rounded-none [&>span]:ring-0" />
        <button type="button" wire:click.stop
                class="absolute right-2.5 top-2.5 rounded-full bg-white/90 p-1.5 shadow backdrop-blur transition {{ $selected ? 'text-gold-500' : 'text-navy-300 opacity-0 group-hover:opacity-100' }} hover:text-gold-500"
                aria-label="Favorite">
            <x-icon name="star" class="h-4 w-4" />
        </button>
    </div>

    <div class="px-5 pb-5">
        <div class="flex items-center gap-4">
            {{-- Health ring beside the title --}}
            <x-health-ring :percent="$health['score']" :group="$health['group']" size="h-14 w-14" class="shrink-0" />

            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                    <a href="{{ route('events.hub', $event) }}" wire:click.stop
                       class="truncate text-[0.95rem] font-bold text-navy-900 hover:text-gold-700">{{ $event->name }}</a>
                    <button type="button" wire:click.stop class="shrink-0 text-navy-300 transition hover:text-navy-700" aria-label="Actions">
                        <x-icon name="dots" class="h-4 w-4" />
                    </button>
                </div>
                <p class="truncate text-xs text-muted">{{ $event->avatar?->name ?? str($event->type)->replace('_', ' ')->title() }}</p>
                <p class="mt-1.5 flex items-center gap-1.5 text-xs text-muted">
                    <x-icon name="building" class="h-3.5 w-3.5 shrink-0" /> {{ $event->city }}, {{ $event->country }}
                </p>
                <div class="mt-1 flex items-center justify-between gap-2">
                    <p class="flex items-center gap-1.5 text-xs text-muted">
                        <x-icon name="calendar" class="h-3.5 w-3.5 shrink-0" />
                        {{ $event->starts_at?->format('M j') }}{{ $event->ends_at && ! $event->ends_at->isSameDay($event->starts_at) ? ' – '.$event->ends_at->format('j, Y') : ', '.$event->starts_at?->format('Y') }}
                    </p>
                    <x-status-badge :status="$health['status']" class="!px-2 !text-[0.6rem]" />
                </div>
            </div>
        </div>

        {{-- Metrics row --}}
        <dl class="mt-4 grid grid-cols-3 gap-2 border-t border-line pt-3.5">
            @foreach ([
                ['icon' => 'users', 'value' => $metrics['participants'] ? number_format($metrics['participants']) : '—', 'label' => str('Participant')->plural($metrics['participants'] ?? 2)],
                ['icon' => 'star', 'value' => $metrics['sponsors'], 'label' => str('Sponsor')->plural($metrics['sponsors'])],
                ['icon' => 'currency', 'value' => $metrics['budget_used'] !== null ? $metrics['budget_used'].'%' : '—', 'label' => 'Budget Used', 'risk' => ($metrics['budget_used'] ?? 0) > 85],
            ] as $metric)
                <div class="flex flex-col items-center">
                    <dd class="flex items-center gap-1.5 text-sm font-bold {{ ($metric['risk'] ?? false) ? 'text-risk' : 'text-navy-900' }}">
                        <span class="text-navy-400"><x-icon :name="$metric['icon']" class="h-3.5 w-3.5" /></span>
                        {{ $metric['value'] }}
                    </dd>
                    <dt class="mt-0.5 text-[0.6rem] text-muted">{{ $metric['label'] }}</dt>
                </div>
            @endforeach
        </dl>
    </div>
</div>
