<x-layouts.app title="Events" subtitle="All events across the region — health, schedule and budget.">
    <div class="mb-5 flex items-center justify-end gap-3">
        <a href="{{ route('events.avatars') }}" class="rounded-xl border border-line bg-white px-4 py-2.5 text-xs font-semibold text-navy-700 transition hover:border-gold-300">Avatar Library</a>
        <a href="{{ route('events.create') }}" class="btn-gold text-xs">+ New Event</a>
    </div>

    <div class="card divide-y divide-line">
        @forelse ($events as $event)
            <div class="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center">
                <x-event-avatar :event="$event" size="md" class="hidden sm:inline-block" />
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-3">
                        <p class="truncate text-sm font-bold text-navy-900">{{ $event->name }}</p>
                        <span class="rounded-full bg-navy-50 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-navy-600">
                            {{ str($event->type)->replace('_', ' ')->title() }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-muted">
                        {{ $event->city }}, {{ $event->country }}
                        @if ($event->venue) · {{ $event->venue->name }} @endif
                        · {{ $event->starts_at?->format('M j, Y') }}
                    </p>
                </div>

                <div class="flex items-center gap-6">
                    <div class="hidden text-right md:block">
                        <p class="text-xs text-muted">Budget</p>
                        <p class="text-sm font-semibold text-navy-900">
                            ${{ \Illuminate\Support\Number::abbreviate($event->budget_cents / 100, 2) }}
                        </p>
                    </div>
                    <div class="hidden text-right md:block">
                        <p class="text-xs text-muted">Tasks</p>
                        <p class="text-sm font-semibold text-navy-900">{{ $event->tasks_count }}</p>
                    </div>
                    <div class="w-36">
                        <div class="mb-1 flex items-center justify-between text-xs">
                            <span class="font-semibold text-navy-900">{{ $event->progress }}%</span>
                            <x-status-badge :status="$event->status" />
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-navy-50">
                            <div @class([
                                    'h-full rounded-full',
                                    'bg-track' => in_array($event->status, ['on_track', 'completed']),
                                    'bg-warn' => in_array($event->status, ['in_progress', 'planning']),
                                    'bg-risk' => in_array($event->status, ['at_risk', 'behind']),
                                ]) style="width: {{ $event->progress }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <p class="px-6 py-12 text-center text-sm text-muted">No events yet.</p>
        @endforelse
    </div>
</x-layouts.app>
