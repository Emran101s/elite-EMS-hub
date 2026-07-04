<x-layouts.app title="Venues" subtitle="Spaces and capacities across the region.">
    <div class="card divide-y divide-line">
        @forelse ($venues as $venue)
            <div class="flex items-center gap-4 px-6 py-5">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-navy-50 text-navy-600">
                    <x-icon name="building" class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-bold text-navy-900">{{ $venue->name }}</p>
                    <p class="mt-0.5 text-xs text-muted">{{ $venue->city }}, {{ $venue->country }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-muted">Capacity</p>
                    <p class="text-sm font-semibold text-navy-900">{{ number_format($venue->capacity) }}</p>
                </div>
                <div class="w-24 text-right">
                    <p class="text-xs text-muted">Events</p>
                    <p class="text-sm font-semibold text-navy-900">{{ $venue->events_count }}</p>
                </div>
            </div>
        @empty
            <p class="px-6 py-12 text-center text-sm text-muted">No venues yet.</p>
        @endforelse
    </div>
</x-layouts.app>
