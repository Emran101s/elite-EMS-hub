<div>
    @if (session('status'))
        <x-alert tone="ok" class="mb-3">{{ session('status') }}</x-alert>
    @endif

    <div class="mb-3 flex items-center justify-between">
        <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Space Explorer</p>
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
                <div class="rounded-lg border border-line bg-white shadow-raise group p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate text-[13.5px] font-bold text-ink">{{ $space->name }}</p>
                            <p class="text-[11px] text-muted">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold bg-page text-muted">{{ str($space->type)->replace('_', ' ')->title() }}</span>
                                @if ($space->floor_zone)
                                    <span>{{ $space->floor_zone }}</span>
                                @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-0.5 opacity-0 transition group-hover:opacity-100">
                            <button type="button" wire:click="editSpace({{ $space->id }})" class="rounded-lg bg-page px-1.5 py-1 text-[10px] font-bold text-muted hover:bg-line">✎</button>
                            <x-confirm title="Delete “{{ $space->name }}”?"
                                       body="Any booking that pointed here keeps its own layout — it just loses the link back to this space."
                                       confirm="Delete" run="$wire.delete({{ $space->id }})"
                                       class="rounded-lg bg-danger/10 px-1.5 py-1 text-[10px] font-bold text-danger-ink hover:bg-danger/20">✕</x-confirm>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-muted">
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
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold bg-gold-50 text-gold-700">{{ \App\Models\EventRoom::SEATING_ARRANGEMENTS[$key][0] ?? $key }}: {{ number_format($value) }}</span>
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
                        <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Name</label>
                        <input type="text" wire:model="name" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none" placeholder="Main Hall">
                        @error('name') <p class="mt-1 text-[11px] text-danger-ink">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Type</label>
                        <select wire:model="type" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink focus:border-navy-300 focus:outline-none">
                            @foreach ($types as $t)
                                <option value="{{ $t }}">{{ str($t)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Floor / zone</label>
                        <input type="text" wire:model="floor_zone" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none" placeholder="Ground Floor — East Wing">
                    </div>
                    <div>
                        <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Max capacity</label>
                        <input type="number" min="0" wire:model="capacity" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
                    </div>
                    <div>
                        <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Width (m) × Length (m)</label>
                        <div class="flex gap-2">
                            <input type="number" step="0.1" min="0" wire:model="width_m" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none" placeholder="Width">
                            <input type="number" step="0.1" min="0" wire:model="length_m" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none" placeholder="Length">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1.5">Capacity by setup</label>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ($arrangements as $key => [$label, $blurb, $family])
                            <div>
                                <label class="text-[10px] text-muted">{{ $label }}</label>
                                <input type="number" min="0" wire:model="setupCapacity.{{ $key }}" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
                            </div>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Notes</label>
                    <textarea wire:model="notes" rows="2" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <x-eo.button variant="ghost" size="sm" type="button" wire:click="$set('showForm', false)">Cancel</x-eo.button>
                    <x-eo.button size="sm" type="submit">{{ $editingId ? 'Save changes' : 'Add space' }}</x-eo.button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
