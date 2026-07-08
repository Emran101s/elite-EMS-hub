@php
    $roomTypeLabels = ['main_hall' => 'Main Hall', 'breakout' => 'Breakout', 'exhibition' => 'Exhibition', 'registration' => 'Registration', 'vip' => 'VIP', 'catering' => 'Catering'];
@endphp

<div class="grid gap-6 lg:grid-cols-2">
    {{-- Venue --}}
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
        @else
            <p class="text-sm text-muted">No venue assigned yet.</p>
        @endif

        <label class="mb-1 mt-5 block text-xs font-medium text-navy-800" for="venue-select">Assign venue</label>
        <select id="venue-select" wire:model.live="venue_id" class="input h-10 text-sm">
            <option value="">— No venue —</option>
            @foreach ($venues as $venue)
                <option value="{{ $venue->id }}">{{ $venue->name }} — {{ $venue->city }}, {{ $venue->country }}</option>
            @endforeach
        </select>
        <p class="mt-2 text-[0.65rem] text-muted">Venues come from the <a href="{{ route('venues.index') }}" class="font-semibold text-gold-600">Venues module</a>. Rooms below are specific to this event and power the Agenda room picker.</p>
    </div>

    {{-- Rooms --}}
    <div class="card p-6">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wide text-navy-900">Rooms & Areas</h3>
                <p class="text-[0.65rem] text-muted">Spaces you can schedule sessions into</p>
            </div>
            <button type="button" wire:click="newRoom" class="btn-gold h-9 px-3.5 text-xs">＋ Add Room</button>
        </div>

        @if ($showRoomForm)
            <form wire:submit="saveRoom" class="mb-4 rounded-2xl border border-line bg-page/50 p-4">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="sm:col-span-3">
                        <label class="field-label !mb-1 !text-[0.62rem]" for="room-name">Room name</label>
                        <input id="room-name" type="text" wire:model="room_name" class="input h-10 text-sm" placeholder="e.g. Main Hall">
                        @error('room_name') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label !mb-1 !text-[0.62rem]" for="room-type">Type</label>
                        <select id="room-type" wire:model="room_type" class="input h-10 text-sm">
                            @foreach ($roomTypeLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]" for="room-capacity">Capacity</label>
                        <input id="room-capacity" type="number" min="0" wire:model="room_capacity" class="input h-10 text-sm" placeholder="—">
                    </div>
                </div>
                <div class="mt-3 flex justify-end gap-2">
                    <button type="button" wire:click="$set('showRoomForm', false)" class="h-9 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                    <button type="submit" class="btn-navy h-9 px-5 text-xs">{{ $editingRoomId ? 'Update Room' : 'Add Room' }}</button>
                </div>
            </form>
        @endif

        <ul class="divide-y divide-line">
            @forelse ($rooms as $room)
                <li class="group flex items-center justify-between gap-3 py-3 first:pt-0">
                    <div class="flex items-center gap-3">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-navy-50 text-navy-600"><x-icon name="building" class="h-4 w-4" /></span>
                        <div>
                            <p class="text-sm font-semibold text-navy-900">{{ $room->name }}</p>
                            <p class="text-[0.62rem] uppercase tracking-wide text-muted">{{ $roomTypeLabels[$room->type] ?? $room->type }}
                                @if ($room->sessions_count) · {{ $room->sessions_count }} {{ str('session')->plural($room->sessions_count) }} @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        @php $placed = collect($room->layout ?? [])->sum(fn ($el) => (int) ($el['seats'] ?? 0)); @endphp
                        <span class="text-xs font-semibold text-navy-900">{{ $room->capacity ? number_format($room->capacity).' pax' : '—' }}</span>
                        <a href="{{ route('events.room-layout', [$event, $room]) }}" class="flex items-center gap-1 rounded-lg border border-line bg-white px-2 py-0.5 text-[0.6rem] font-bold text-navy-700 transition hover:border-gold-300" title="Seating layout builder">
                            ⊞ Layout @if ($placed) <span class="text-gold-600">· {{ $placed }}</span> @endif
                        </a>
                        <span class="flex gap-1 opacity-0 transition group-hover:opacity-100">
                            <button type="button" wire:click="editRoom({{ $room->id }})" class="rounded-lg bg-navy-50 px-1.5 py-0.5 text-[0.6rem] font-bold text-navy-600 hover:bg-navy-100" title="Edit">✎</button>
                            <button type="button" wire:click="deleteRoom({{ $room->id }})" wire:confirm="Delete “{{ $room->name }}”? Sessions here become room-less." class="rounded-lg bg-risk/10 px-1.5 py-0.5 text-[0.6rem] font-bold text-red-700 hover:bg-risk/20" title="Delete">✕</button>
                        </span>
                    </div>
                </li>
            @empty
                <li class="py-3 text-xs text-muted">No rooms yet — add one and it becomes selectable in the Agenda. Rooms contribute 40% of Venue Readiness.</li>
            @endforelse
        </ul>
    </div>
</div>
