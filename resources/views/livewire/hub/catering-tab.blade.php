@php
    $statusMeta = [
        'planned' => ['Planned', 'bg-page text-muted'],
        'confirmed' => ['Confirmed', 'bg-success-soft text-success-ink'],
        'cancelled' => ['Cancelled', 'bg-page text-muted'],
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
                <button type="button" wire:click="newItem" class="h-10 rounded-full bg-gold-500 px-5 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">＋ Add the first occasion</button>
            </x-slot:actions>
        </x-empty>
    @else
        <div class="mb-2.5 flex flex-wrap items-center justify-between gap-2">
            <x-bulk-bar :count="$this->selectedCount()" noun="occasion" />
            <button type="button" wire:click="newItem" class="ms-auto h-8 rounded-full bg-gold-500 px-3 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">＋ Add Occasion</button>
        </div>
        <div class="space-y-4">
            @foreach ($byDate as $dateKey => $dayItems)
                <div wire:key="day-{{ $dateKey }}">
                    <p class="mb-1.5 flex items-baseline gap-2 text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">
                        {{ $dateLabel($dateKey) }}
                        <span class="font-normal normal-case text-muted">· {{ $dayItems->count() }} {{ \Illuminate\Support\Str::plural('occasion', $dayItems->count()) }}</span>
                    </p>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($dayItems as $c)
                            @php [$stLabel, $stClass] = $statusMeta[$c->status] ?? $statusMeta['planned']; @endphp
                            <div wire:key="cat-{{ $c->id }}" class="group/ct flex flex-col overflow-hidden rounded-lg border border-line bg-white {{ $this->isSelected($c->id) ? '!border-navy-900 ring-2 ring-navy-900' : '' }}">
                                <div class="flex flex-1 flex-col p-3.5">
                                    <div class="flex items-start justify-between gap-2.5">
                                        <div class="flex min-w-0 items-center gap-2.5">
                                            <button type="button" wire:click="toggleSelect({{ $c->id }})" class="flex h-4 w-4 shrink-0 items-center justify-center rounded border text-eyebrow {{ $this->isSelected($c->id) ? 'border-navy-900 bg-navy-900 text-white' : 'border-line text-transparent hover:border-muted' }}" title="Select">✓</button>
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" style="color: {{ $moduleHex }}; background: {{ $moduleHex }}15">
                                                <x-icon :name="$typeIcon[$c->type] ?? 'cup'" class="h-4 w-4" />
                                            </span>
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-bold text-ink">{{ $c->title }}</p>
                                                <p class="truncate text-[10.5px] text-muted">{{ $c->typeLabel() }} · {{ $c->venueLabel() }}</p>
                                            </div>
                                        </div>
                                        <span class="shrink-0 rounded-full px-2 py-0.5 text-eyebrow font-bold {{ $stClass }}">{{ $stLabel }}</span>
                                    </div>
                                    <div class="mt-2.5 flex items-center gap-2 text-eyebrow text-muted">
                                        @if ($c->headcount)<span>{{ number_format($c->headcount) }} covers</span>@endif
                                        @if ($c->supplier)<span class="truncate">· {{ $c->supplier->name }}</span>@endif
                                    </div>
                                </div>
                                <div class="mt-auto flex items-center gap-2 border-t border-line bg-page px-3.5 py-2">
                                    <span class="truncate text-eyebrow font-semibold text-ink">
                                        {{ $event->money($c->totalCents()) }}
                                        @if ($c->per_person && $c->headcount)<span class="opacity-70">· {{ $event->money($c->cost_cents) }}/pp</span>@endif
                                    </span>
                                    <div class="ms-auto flex items-center gap-1 opacity-100 sm:opacity-0 sm:transition sm:group-hover/ct:opacity-100">
                                        @if ($c->status !== 'confirmed')
                                            <button type="button" wire:click="setStatus({{ $c->id }}, 'confirmed')" class="rounded-md bg-success-soft px-2 py-0.5 text-eyebrow font-bold text-success-ink hover:bg-success-soft/70">✓ Confirm</button>
                                        @endif
                                        <button type="button" wire:click="edit({{ $c->id }})" class="rounded-md bg-white px-1.5 py-0.5 text-eyebrow font-bold text-muted ring-1 ring-line hover:ring-navy-300">✎</button>
                                        <x-confirm title="Remove {{ $c->title }}?" confirm="Remove" run="$wire.delete({{ $c->id }})"
                                                   class="rounded-md bg-danger-soft px-1.5 py-0.5 text-eyebrow font-bold text-danger-ink hover:bg-danger-soft/70">✕</x-confirm>
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
                    <span class="relative text-2xs font-bold uppercase tracking-[0.18em] text-ink">F&amp;B Control</span>
                </div>
                <div class="border-b border-line p-3">
                    <x-budget-routing :routing="$this->budgetRouting()" />
                </div>
                <div class="p-3">
                    <button type="button" wire:click="newItem" class="h-9 w-full rounded-full bg-gold-500 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">＋ Add Occasion</button>
                </div>
            </div>
        </div>
    </div>

    {{-- modal --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit occasion' : 'New occasion'" max="xl" close="set('showForm', false)">
                <form wire:submit="save" class="grid gap-3.5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">What is it</label>
                        <input type="text" wire:model="title" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Welcome coffee break, Gala dinner…">
                        @error('title')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Type</label>
                        <select wire:model="type" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                            @foreach (\App\Models\EventCateringItem::TYPES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Date</label>
                        <input type="date" wire:model="occasion_date" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Venue</label>
                        <div class="inline-flex rounded-xl border border-line bg-white p-1">
                            <button type="button" wire:click="$set('venue_mode', 'in_house')" class="rounded-lg px-4 py-1.5 text-xs font-bold transition {{ $venue_mode === 'in_house' ? 'bg-navy-900 text-white' : 'text-muted hover:text-ink' }}">In venue</button>
                            <button type="button" wire:click="$set('venue_mode', 'outside')" class="rounded-lg px-4 py-1.5 text-xs font-bold transition {{ $venue_mode === 'outside' ? 'bg-navy-900 text-white' : 'text-muted hover:text-ink' }}">Outside</button>
                        </div>
                    </div>
                    @if ($venue_mode === 'in_house')
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Room</label>
                            <select wire:model="room_id" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                                <option value="">— Pick a room —</option>
                                @foreach ($rooms as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach
                            </select>
                        </div>
                    @else
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Location</label>
                            <input type="text" wire:model="location" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Fakhreldin Restaurant, Amman">
                        </div>
                    @endif

                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Covers</label>
                        <input type="number" min="0" wire:model="headcount" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="—">
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Status</label>
                        <select wire:model="status" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                            @foreach (\App\Models\EventCateringItem::STATUSES as $st)<option value="{{ $st }}">{{ ucfirst($st) }}</option>@endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Rate ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="cost" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="0">
                        @error('cost')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                    </div>
                    <label class="flex items-end gap-2 pb-2.5">
                        <input type="checkbox" wire:model="per_person" class="h-4 w-4 rounded border-line text-gold-600">
                        <span class="text-xs font-semibold text-ink">Per person, not a flat total</span>
                    </label>

                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Supplier</label>
                        <select wire:model="supplier_id" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                            <option value="">— None on file —</option>
                            @foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Notes</label>
                        <textarea wire:model="notes" rows="2" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none text-sm" placeholder="Dietary requirements, menu notes…"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-muted hover:text-ink">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="h-10 rounded-full bg-gold-500 px-6 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update' : 'Add occasion' }}</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
        </x-modal>
    @endif
</div>
