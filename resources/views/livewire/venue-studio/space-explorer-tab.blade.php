<div>
    @if (session('status'))
        <x-alert tone="ok" class="mb-3">{{ session('status') }}</x-alert>
    @endif

    <div class="mb-3 flex items-center justify-between">
        <p class="eo-label">Space Explorer</p>
        <x-eo.button size="sm" wire:click="newSpace">＋ Add Space</x-eo.button>
    </div>

    @if ($spaces->isEmpty())
        <x-eo.empty-state title="No spaces on file" icon="building"
            hint="Add this venue's halls and rooms once — every event booked here can then reuse them.">
            <x-slot:actions>
                <x-eo.button size="sm" wire:click="newSpace">＋ Add Space</x-eo.button>
            </x-slot:actions>
        </x-eo.empty-state>
    @else
        <div class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
            @foreach ($spaces as $space)
                <div class="eo-soft-card group p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate text-[13.5px] font-bold text-eo-text">{{ $space->name }}</p>
                            <p class="text-[11px] text-eo-muted">
                                <span class="eo-pill bg-eo-bg text-eo-muted">{{ str($space->type)->replace('_', ' ')->title() }}</span>
                                @if ($space->floor_zone)
                                    <span>{{ $space->floor_zone }}</span>
                                @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-0.5 opacity-0 transition group-hover:opacity-100">
                            <button type="button" wire:click="editSpace({{ $space->id }})" class="rounded-lg bg-eo-bg px-1.5 py-1 text-[10px] font-bold text-eo-muted hover:bg-eo-line">✎</button>
                            <x-confirm title="Delete “{{ $space->name }}”?"
                                       body="Any booking that pointed here keeps its own layout — it just loses the link back to this space."
                                       confirm="Delete" run="$wire.delete({{ $space->id }})"
                                       class="rounded-lg bg-eo-risk/10 px-1.5 py-1 text-[10px] font-bold text-eo-risk hover:bg-eo-risk/20">✕</x-confirm>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-eo-muted">
                        @if ($space->capacity)
                            <span>{{ number_format($space->capacity) }} capacity</span>
                        @endif
                        @if ($space->areaM2())
                            <span>{{ $space->areaM2() }} m²</span>
                        @endif
                        <span>{{ $space->bookings_count }} {{ str('booking')->plural($space->bookings_count) }}</span>
                    </div>

                    @if (! empty($space->capacity_by_setup))
                        <div class="mt-2.5 flex flex-wrap gap-1">
                            @foreach ($space->capacity_by_setup as $key => $value)
                                <span class="eo-pill bg-eo-teal-soft text-eo-teal-ink">{{ \App\Models\EventRoom::SEATING_ARRANGEMENTS[$key][0] ?? $key }}: {{ number_format($value) }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit space' : 'New space'" close="set('showForm', false)" max="2xl">
            <form wire:submit="save" class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label class="eo-label mb-1">Name</label>
                        <input type="text" wire:model="name" class="eo-input" placeholder="Main Hall">
                        @error('name') <p class="mt-1 text-[11px] text-eo-risk">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="eo-label mb-1">Type</label>
                        <select wire:model="type" class="eo-select">
                            @foreach ($types as $t)
                                <option value="{{ $t }}">{{ str($t)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="eo-label mb-1">Floor / zone</label>
                        <input type="text" wire:model="floor_zone" class="eo-input" placeholder="Ground Floor — East Wing">
                    </div>
                    <div>
                        <label class="eo-label mb-1">Max capacity</label>
                        <input type="number" min="0" wire:model="capacity" class="eo-input">
                    </div>
                    <div>
                        <label class="eo-label mb-1">Width (m) × Length (m)</label>
                        <div class="flex gap-2">
                            <input type="number" step="0.1" min="0" wire:model="width_m" class="eo-input" placeholder="Width">
                            <input type="number" step="0.1" min="0" wire:model="length_m" class="eo-input" placeholder="Length">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="eo-label mb-1.5">Capacity by setup</label>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ($arrangements as $key => [$label, $blurb, $family])
                            <div>
                                <label class="text-[10px] text-eo-muted">{{ $label }}</label>
                                <input type="number" min="0" wire:model="setupCapacity.{{ $key }}" class="eo-input">
                            </div>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="eo-label mb-1">Notes</label>
                    <textarea wire:model="notes" rows="2" class="eo-input"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <x-eo.button variant="ghost" size="sm" type="button" wire:click="$set('showForm', false)">Cancel</x-eo.button>
                    <x-eo.button size="sm" type="submit">{{ $editingId ? 'Save changes' : 'Add space' }}</x-eo.button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
