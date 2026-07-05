@props(['event', 'health', 'metrics', 'selected' => false])

@php $theme = $event->theme(); @endphp

<div wire:click="select({{ $event->id }})"
     @class([
         'group relative cursor-pointer overflow-hidden rounded-3xl border bg-white shadow-[0_1px_3px_rgba(11,31,58,0.05)] transition duration-300',
         'border-gold-500 ring-2 ring-gold-500/40' => $selected,
         'border-line hover:-translate-y-0.5 hover:border-gold-300 hover:shadow-[0_16px_40px_rgba(11,31,58,0.12)]' => ! $selected,
     ])>

    {{-- Avatar visual --}}
    <div class="relative overflow-hidden bg-page">
        <x-event-avatar :event="$event" :ring="false" size="xl"
                        class="block w-full transition duration-500 group-hover:scale-[1.04] [&>span]:h-40 [&>span]:w-full [&>span]:rounded-none [&>span]:ring-0" />
        <button type="button" class="absolute right-11 top-3 rounded-full bg-white/90 p-1.5 text-gold-500 shadow backdrop-blur transition hover:text-gold-600" aria-label="Favorite" wire:click.stop>
            <x-icon name="star" class="h-4 w-4" />
        </button>
        <button type="button" class="absolute right-3 top-3 rounded-full bg-white/90 p-1.5 text-navy-400 shadow backdrop-blur transition hover:text-navy-700" aria-label="Actions" wire:click.stop>
            <x-icon name="dots" class="h-4 w-4" />
        </button>
    </div>

    <div class="relative px-5 pb-5 pt-9">
        {{-- Health ring overlapping the visual --}}
        <span class="absolute -top-7 left-5 rounded-full bg-white p-1 shadow-lg ring-1 ring-line">
            <x-health-ring :percent="$health['score']" :group="$health['group']" size="h-12 w-12" />
        </span>
        <span class="absolute -top-3 right-5"><x-status-badge :status="$health['status']" /></span>

        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="truncate text-sm font-bold text-navy-900 group-hover:text-gold-700">{{ $event->name }}</p>
                <p class="mt-0.5 truncate text-xs text-muted">
                    {{ $event->avatar?->name ?? str($event->type)->replace('_', ' ')->title() }}
                    @if ($event->client) · {{ $event->client->name }} @endif
                </p>
            </div>
        </div>

        <p class="mt-2 flex flex-wrap gap-x-3 text-xs text-muted">
            <span>📍 {{ $event->city }}, {{ $event->country }}</span>
            <span>📅 {{ $event->starts_at?->format('M j') }}{{ $event->ends_at && ! $event->ends_at->isSameDay($event->starts_at) ? ' – '.$event->ends_at->format('M j, Y') : ', '.$event->starts_at?->format('Y') }}</span>
        </p>

        <dl class="mt-4 grid grid-cols-3 gap-2 border-t border-line pt-3 text-center">
            <div>
                <dd class="text-sm font-bold text-navy-900">{{ $metrics['participants'] ? number_format($metrics['participants']) : '—' }}</dd>
                <dt class="text-[0.6rem] uppercase tracking-wide text-muted">Participants</dt>
            </div>
            <div>
                <dd class="text-sm font-bold text-navy-900">{{ $metrics['sponsors'] }}</dd>
                <dt class="text-[0.6rem] uppercase tracking-wide text-muted">Sponsors</dt>
            </div>
            <div>
                <dd class="text-sm font-bold {{ ($metrics['budget_used'] ?? 0) > 85 ? 'text-risk' : 'text-navy-900' }}">{{ $metrics['budget_used'] !== null ? $metrics['budget_used'].'%' : '—' }}</dd>
                <dt class="text-[0.6rem] uppercase tracking-wide text-muted">Budget Used</dt>
            </div>
        </dl>

        <a href="{{ route('events.hub', $event) }}" wire:click.stop
           class="mt-4 flex items-center justify-center rounded-xl py-2 text-xs font-bold text-navy-900 opacity-90 ring-1 ring-line transition group-hover:opacity-100"
           style="background: linear-gradient(90deg, {{ $theme['accent'] }}22, transparent); border-color: {{ $theme['accent'] }}44">
            Open Event Hub →
        </a>
    </div>
</div>
