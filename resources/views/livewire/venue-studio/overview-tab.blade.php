<div class="rounded-lg border border-line bg-white shadow-raise p-5">
    <div class="mb-3 flex items-center justify-between">
        <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Digital Twin</p>
        <div class="flex items-center gap-1 rounded-xl bg-page p-0.5">
            <button type="button" wire:click="setMode('occupancy')"
                    class="rounded-lg px-2.5 py-1 text-[10.5px] font-bold transition {{ $mode === 'occupancy' ? 'bg-white text-ink shadow-sm' : 'text-muted hover:text-ink' }}">
                Occupancy
            </button>
            <button type="button" wire:click="setMode('readiness')"
                    class="rounded-lg px-2.5 py-1 text-[10.5px] font-bold transition {{ $mode === 'readiness' ? 'bg-white text-ink shadow-sm' : 'text-muted hover:text-ink' }}">
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
                    <p class="mb-2 text-[11px] font-bold uppercase tracking-wide text-muted">{{ $zoneName }}</p>
                    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($spaces as $space)
                            @if ($mode === 'occupancy')
                                @php $booking = $space->currentBooking(); @endphp
                                <a href="{{ route('venues.show', [$venue, 'tab' => 'spaces']) }}" wire:navigate
                                   class="rounded-2xl border p-3 transition hover:border-gold-400"
                                   style="border-color: {{ $booking ? 'var(--color-danger)' : 'var(--color-line)' }}; background: {{ $booking ? 'var(--color-danger-soft)' : 'white' }};">
                                    <p class="truncate text-[12.5px] font-bold text-ink">{{ $space->name }}</p>
                                    <p class="text-[10.5px] text-muted">{{ str($space->type)->replace('_', ' ')->title() }}</p>
                                    @if ($space->capacity)
                                        <p class="mt-1.5 text-[10px] text-muted">{{ number_format($space->capacity) }} capacity</p>
                                    @endif
                                    <p class="mt-1 text-[10px] font-semibold {{ $booking ? 'text-danger-ink' : 'text-success-ink' }}">
                                        {{ $booking ? 'Occupied — ' . $booking->event->name : 'Free today' }}
                                    </p>
                                </a>
                            @else
                                @php
                                    $state = $readinessBySpace[$space->id] ?? 'fully_documented';
                                    [$borderColor, $bg, $textColor, $label] = match ($state) {
                                        'missing_capacity' => ['var(--color-danger)', 'var(--color-danger-soft)', 'text-danger-ink', 'Missing capacity data'],
                                        'missing_dimensions' => ['var(--color-danger)', 'var(--color-danger-soft)', 'text-danger-ink', 'Missing dimensions'],
                                        'never_booked' => ['var(--color-warning)', 'var(--color-warning-soft)', 'text-warning-ink', 'Never booked'],
                                        default => ['var(--color-success)', 'var(--color-success-soft)', 'text-success-ink', 'Fully documented'],
                                    };
                                @endphp
                                <a href="{{ route('venues.show', [$venue, 'tab' => 'spaces']) }}" wire:navigate
                                   class="rounded-2xl border p-3 transition hover:border-gold-400"
                                   style="border-color: {{ $borderColor }}; background: {{ $bg }};">
                                    <p class="truncate text-[12.5px] font-bold text-ink">{{ $space->name }}</p>
                                    <p class="text-[10.5px] text-muted">{{ str($space->type)->replace('_', ' ')->title() }}</p>
                                    @if ($space->capacity)
                                        <p class="mt-1.5 text-[10px] text-muted">{{ number_format($space->capacity) }} capacity</p>
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
