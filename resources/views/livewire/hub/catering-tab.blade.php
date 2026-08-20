@php
    $statusMeta = [
        'planned' => ['Planned', 'bg-eo-bg text-eo-muted'],
        'confirmed' => ['Confirmed', 'bg-emerald-100 text-emerald-700'],
        'cancelled' => ['Cancelled', 'bg-eo-bg text-eo-muted'],
    ];
    $typeIcon = [
        'coffee_break' => 'cup', 'breakfast' => 'cup', 'lunch' => 'users',
        'dinner' => 'star', 'reception' => 'sparkles', 'other' => 'cup',
    ];
    // Undated occasions are real — a lunch not yet pinned to a day — so they
    // get their own group rather than being dropped or sorted at random.
    $dateLabel = fn ($k) => $k === '_undated' ? 'Date not set' : \Illuminate\Support\Carbon::parse($k)->format('l, j F Y');
    $moduleHex = \App\Models\Event::moduleColor('catering');
@endphp
<div>
    {{-- Occasions / Confirmed / Covers / Total cost already live in the
         Universal Module Header above this — the lead strip that used to
         repeat them here is gone rather than kept as a second copy. --}}

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
        <div class="min-w-0">
    @if ($items->isEmpty())
        <x-empty icon="cup" title="No food & beverage yet"
                 hint="Coffee breaks, lunches, a gala dinner at an outside restaurant — each occasion is its own line, with its own date, venue and rate.">
            <x-slot:actions>
                <x-eo.button wire:click="newItem" class="h-10 px-5 text-xs">＋ Add the first occasion</x-eo.button>
            </x-slot:actions>
        </x-empty>
    @else
        <div class="mb-2.5 flex flex-wrap items-center justify-between gap-2">
            <x-bulk-bar :count="$this->selectedCount()" noun="occasion" />
            <x-eo.button wire:click="newItem" class="ms-auto h-8 px-3 text-xs">＋ Add Occasion</x-eo.button>
        </div>
        <div class="space-y-4">
            @foreach ($byDate as $dateKey => $dayItems)
                <div wire:key="day-{{ $dateKey }}">
                    <p class="mb-1.5 flex items-baseline gap-2 text-eyebrow font-bold uppercase tracking-[0.14em] text-eo-muted">
                        {{ $dateLabel($dateKey) }}
                        <span class="font-normal normal-case text-eo-muted">· {{ $dayItems->count() }} {{ \Illuminate\Support\Str::plural('occasion', $dayItems->count()) }}</span>
                    </p>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($dayItems as $c)
                            @php [$stLabel, $stClass] = $statusMeta[$c->status] ?? $statusMeta['planned']; @endphp
                            <div wire:key="cat-{{ $c->id }}" class="group/ct op-card {{ $this->isSelected($c->id) ? '!border-eo-navy ring-2 ring-navy-900' : '' }}">
                                <div class="flex flex-1 flex-col p-3.5">
                                    <div class="flex items-start justify-between gap-2.5">
                                        <div class="flex min-w-0 items-center gap-2.5">
                                            <button type="button" wire:click="toggleSelect({{ $c->id }})" class="flex h-4 w-4 shrink-0 items-center justify-center rounded border text-eyebrow {{ $this->isSelected($c->id) ? 'border-eo-navy bg-eo-navy text-white' : 'border-eo-line text-transparent hover:border-eo-line' }}" title="Select">✓</button>
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" style="color: {{ $moduleHex }}; background: {{ $moduleHex }}15">
                                                <x-icon :name="$typeIcon[$c->type] ?? 'cup'" class="h-4 w-4" />
                                            </span>
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-bold text-eo-text">{{ $c->title }}</p>
                                                <p class="truncate text-micro text-eo-muted">{{ $c->typeLabel() }} · {{ $c->venueLabel() }}</p>
                                            </div>
                                        </div>
                                        <span class="pill shrink-0 {{ $stClass }}">{{ $stLabel }}</span>
                                    </div>
                                    <div class="mt-2.5 flex items-center gap-2 text-eyebrow text-eo-muted">
                                        @if ($c->headcount)<span>{{ number_format($c->headcount) }} covers</span>@endif
                                        @if ($c->supplier)<span class="truncate">· {{ $c->supplier->name }}</span>@endif
                                    </div>
                                </div>
                                <div class="op-card-foot">
                                    <span class="truncate text-eyebrow font-semibold text-eo-text">
                                        {{ $event->money($c->totalCents()) }}
                                        @if ($c->per_person && $c->headcount)<span class="opacity-70">· {{ $event->money($c->cost_cents) }}/pp</span>@endif
                                    </span>
                                    <div class="ms-auto flex items-center gap-1 opacity-100 sm:opacity-0 sm:transition sm:group-hover/ct:opacity-100">
                                        @if ($c->status !== 'confirmed')
                                            <button type="button" wire:click="setStatus({{ $c->id }}, 'confirmed')" class="rounded-md bg-emerald-50 px-2 py-0.5 text-eyebrow font-bold text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-100">✓ Confirm</button>
                                        @endif
                                        <button type="button" wire:click="edit({{ $c->id }})" class="rounded-md bg-white px-1.5 py-0.5 text-eyebrow font-bold text-eo-muted ring-1 ring-eo-line hover:ring-eo-teal">✎</button>
                                        <x-confirm title="Remove {{ $c->title }}?" confirm="Remove" run="$wire.delete({{ $c->id }})"
                                                   class="rounded-md bg-eo-risk/10 px-1.5 py-0.5 text-eyebrow font-bold text-red-700 hover:bg-eo-risk/20">✕</x-confirm>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
        </div>

        <div class="xl:sticky xl:top-4 xl:h-fit">
            <div class="cc-panel">
                <div class="cc-head">
                    <span class="relative flex h-7 w-7 items-center justify-center rounded-lg text-white shadow-sm" style="background: {{ $moduleHex }}">
                        <x-icon name="cup" class="h-3.5 w-3.5" />
                    </span>
                    <span class="relative text-2xs font-bold uppercase tracking-[0.18em] text-eo-text">F&amp;B Control</span>
                </div>
                <div class="border-b border-eo-line p-3">
                    <x-budget-routing :routing="$this->budgetRouting()" />
                </div>
                <div class="p-3">
                    <x-eo.button wire:click="newItem" class="h-9 w-full text-xs">＋ Add Occasion</x-eo.button>
                </div>
            </div>
        </div>
    </div>

    {{-- modal --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit occasion' : 'New occasion'" max="xl" close="set('showForm', false)">
                <form wire:submit="save" class="grid gap-3.5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="eo-label !mb-1 !text-eyebrow">What is it</label>
                        <input type="text" wire:model="title" class="eo-input h-10 text-sm" placeholder="Welcome coffee break, Gala dinner…">
                        @error('title')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="eo-label !mb-1 !text-eyebrow">Type</label>
                        <select wire:model="type" class="eo-input h-10 text-sm">
                            @foreach (\App\Models\EventCateringItem::TYPES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="eo-label !mb-1 !text-eyebrow">Date</label>
                        <input type="date" wire:model="occasion_date" class="eo-input h-10 text-sm">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="eo-label !mb-1 !text-eyebrow">Venue</label>
                        <div class="inline-flex rounded-xl border border-eo-line bg-white p-1">
                            <button type="button" wire:click="$set('venue_mode', 'in_house')" class="rounded-lg px-4 py-1.5 text-xs font-bold transition {{ $venue_mode === 'in_house' ? 'bg-eo-navy text-white' : 'text-eo-muted hover:text-eo-text' }}">In venue</button>
                            <button type="button" wire:click="$set('venue_mode', 'outside')" class="rounded-lg px-4 py-1.5 text-xs font-bold transition {{ $venue_mode === 'outside' ? 'bg-eo-navy text-white' : 'text-eo-muted hover:text-eo-text' }}">Outside</button>
                        </div>
                    </div>
                    @if ($venue_mode === 'in_house')
                        <div class="sm:col-span-2">
                            <label class="eo-label !mb-1 !text-eyebrow">Room</label>
                            <select wire:model="room_id" class="eo-input h-10 text-sm">
                                <option value="">— Pick a room —</option>
                                @foreach ($rooms as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach
                            </select>
                        </div>
                    @else
                        <div class="sm:col-span-2">
                            <label class="eo-label !mb-1 !text-eyebrow">Location</label>
                            <input type="text" wire:model="location" class="eo-input h-10 text-sm" placeholder="Fakhreldin Restaurant, Amman">
                        </div>
                    @endif

                    <div>
                        <label class="eo-label !mb-1 !text-eyebrow">Covers</label>
                        <input type="number" min="0" wire:model="headcount" class="eo-input h-10 text-sm" placeholder="—">
                    </div>
                    <div>
                        <label class="eo-label !mb-1 !text-eyebrow">Status</label>
                        <select wire:model="status" class="eo-input h-10 text-sm">
                            @foreach (\App\Models\EventCateringItem::STATUSES as $st)<option value="{{ $st }}">{{ ucfirst($st) }}</option>@endforeach
                        </select>
                    </div>

                    <div>
                        <label class="eo-label !mb-1 !text-eyebrow">Rate ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="cost" class="eo-input h-10 text-sm" placeholder="0">
                        @error('cost')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                    </div>
                    <label class="flex items-end gap-2 pb-2.5">
                        <input type="checkbox" wire:model="per_person" class="h-4 w-4 rounded border-eo-line text-eo-teal">
                        <span class="text-xs font-semibold text-eo-text">Per person, not a flat total</span>
                    </label>

                    <div class="sm:col-span-2">
                        <label class="eo-label !mb-1 !text-eyebrow">Supplier</label>
                        <select wire:model="supplier_id" class="eo-input h-10 text-sm">
                            <option value="">— None on file —</option>
                            @foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="eo-label !mb-1 !text-eyebrow">Notes</label>
                        <textarea wire:model="notes" rows="2" class="eo-input text-sm" placeholder="Dietary requirements, menu notes…"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-eo-muted hover:text-eo-text">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="eo-btn-navy h-10 px-6 text-xs">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update' : 'Add occasion' }}</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
        </x-modal>
    @endif
</div>
