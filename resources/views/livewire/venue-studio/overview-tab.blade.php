<div class="eo-soft-card p-5">
    <div class="mb-3 flex items-center justify-between">
        <p class="eo-label">Digital Twin</p>
        <div class="flex items-center gap-1 rounded-xl bg-eo-bg p-0.5">
            <button type="button" wire:click="setMode('occupancy')"
                    class="rounded-lg px-2.5 py-1 text-[10.5px] font-bold transition {{ $mode === 'occupancy' ? 'bg-white text-eo-text shadow-sm' : 'text-eo-muted hover:text-eo-text' }}">
                Occupancy
            </button>
            <button type="button" wire:click="setMode('readiness')"
                    class="rounded-lg px-2.5 py-1 text-[10.5px] font-bold transition {{ $mode === 'readiness' ? 'bg-white text-eo-text shadow-sm' : 'text-eo-muted hover:text-eo-text' }}">
                Readiness
            </button>
        </div>
    </div>

    @if ($zones->isEmpty())
        <x-eo.empty-state title="No spaces yet" icon="building"
            hint="Add the venue's halls and rooms in Space Explorer to see them here.">
            <x-slot:actions>
                <x-eo.button size="sm" href="{{ route('venues.show', [$venue, 'tab' => 'spaces']) }}">Open Space Explorer</x-eo.button>
            </x-slot:actions>
        </x-eo.empty-state>
    @else
        <div class="space-y-5">
            @foreach ($zones as $zoneName => $spaces)
                <div>
                    <p class="mb-2 text-[11px] font-bold uppercase tracking-wide text-eo-muted">{{ $zoneName }}</p>
                    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($spaces as $space)
                            @if ($mode === 'occupancy')
                                @php $booking = $space->currentBooking(); @endphp
                                <a href="{{ route('venues.show', [$venue, 'tab' => 'spaces']) }}" wire:navigate
                                   class="rounded-2xl border p-3 transition hover:border-eo-teal"
                                   style="border-color: {{ $booking ? 'var(--color-eo-risk)' : 'var(--color-eo-line)' }}; background: {{ $booking ? 'var(--color-eo-risk-soft)' : 'white' }};">
                                    <p class="truncate text-[12.5px] font-bold text-eo-text">{{ $space->name }}</p>
                                    <p class="text-[10.5px] text-eo-muted">{{ str($space->type)->replace('_', ' ')->title() }}</p>
                                    @if ($space->capacity)
                                        <p class="mt-1.5 text-[10px] text-eo-muted">{{ number_format($space->capacity) }} capacity</p>
                                    @endif
                                    <p class="mt-1 text-[10px] font-semibold {{ $booking ? 'text-eo-risk' : 'text-eo-ok-ink' }}">
                                        {{ $booking ? 'Occupied — ' . $booking->event->name : 'Free today' }}
                                    </p>
                                </a>
                            @else
                                @php
                                    $state = $readinessBySpace[$space->id] ?? 'fully_documented';
                                    [$borderColor, $bg, $textColor, $label] = match ($state) {
                                        'missing_capacity' => ['var(--color-eo-risk)', 'var(--color-eo-risk-soft)', 'text-eo-risk', 'Missing capacity data'],
                                        'missing_dimensions' => ['var(--color-eo-risk)', 'var(--color-eo-risk-soft)', 'text-eo-risk', 'Missing dimensions'],
                                        'never_booked' => ['var(--color-eo-warn)', 'var(--color-eo-warn-soft)', 'text-eo-warn-ink', 'Never booked'],
                                        default => ['var(--color-eo-ok)', 'var(--color-eo-ok-soft)', 'text-eo-ok-ink', 'Fully documented'],
                                    };
                                @endphp
                                <a href="{{ route('venues.show', [$venue, 'tab' => 'spaces']) }}" wire:navigate
                                   class="rounded-2xl border p-3 transition hover:border-eo-teal"
                                   style="border-color: {{ $borderColor }}; background: {{ $bg }};">
                                    <p class="truncate text-[12.5px] font-bold text-eo-text">{{ $space->name }}</p>
                                    <p class="text-[10.5px] text-eo-muted">{{ str($space->type)->replace('_', ' ')->title() }}</p>
                                    @if ($space->capacity)
                                        <p class="mt-1.5 text-[10px] text-eo-muted">{{ number_format($space->capacity) }} capacity</p>
                                    @endif
                                    <p class="mt-1 text-[10px] font-semibold {{ $textColor }}">{{ $label }}</p>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
