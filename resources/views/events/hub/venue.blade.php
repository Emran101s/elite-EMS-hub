<div class="grid gap-6 lg:grid-cols-2">
    <div class="card p-6">
        <h3 class="mb-4 text-xs font-bold uppercase tracking-wide text-navy-900">Venue</h3>
        @if ($event->venue)
            <div class="flex items-center gap-4">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-navy-50 text-navy-600"><x-icon name="building" class="h-6 w-6" /></span>
                <div>
                    <p class="text-sm font-bold text-navy-900">{{ $event->venue->name }}</p>
                    <p class="text-xs text-muted">{{ $event->venue->city }}, {{ $event->venue->country }} · capacity {{ number_format($event->venue->capacity) }}</p>
                </div>
            </div>
            @if ($event->venue->notes)
                <p class="mt-4 rounded-xl bg-page px-4 py-3 text-xs text-muted">{{ $event->venue->notes }}</p>
            @endif
        @else
            <p class="text-sm text-muted">No venue assigned yet.</p>
        @endif
        <p class="mt-4 text-xs text-muted">Coming next: floor plan uploads, site inspection notes, setup styles, AV restrictions, contract &amp; payment status.</p>
    </div>

    <div class="card p-6">
        <h3 class="mb-4 text-xs font-bold uppercase tracking-wide text-navy-900">Rooms & Areas</h3>
        <ul class="divide-y divide-line">
            @forelse ($event->rooms as $room)
                <li class="flex items-center justify-between py-3 first:pt-0 last:pb-0">
                    <div>
                        <p class="text-sm font-semibold text-navy-900">{{ $room->name }}</p>
                        <p class="text-[0.65rem] uppercase tracking-wide text-muted">{{ str($room->type)->replace('_', ' ')->title() }}</p>
                    </div>
                    <span class="text-xs font-semibold text-navy-900">{{ $room->capacity ? number_format($room->capacity).' pax' : '—' }}</span>
                </li>
            @empty
                <li class="py-3 text-xs text-muted">No rooms configured — rooms contribute 40% of Venue Readiness.</li>
            @endforelse
        </ul>
    </div>
</div>
