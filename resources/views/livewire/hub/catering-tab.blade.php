@php
    $statusMeta = [
        'planned' => ['Planned', 'bg-navy-100 text-navy-600'],
        'confirmed' => ['Confirmed', 'bg-emerald-100 text-emerald-700'],
        'cancelled' => ['Cancelled', 'bg-navy-100 text-navy-400'],
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
    <x-stat-strip class="mb-4" :stats="[
        ['Occasions', $items->count(), 'cup', null, null, null],
        ['Confirmed', $confirmed, 'check', $items->count() ? round($confirmed / max($items->count(), 1) * 100) : null, 'bg-emerald-500', null, 'text-emerald-600'],
        ['Covers', number_format($covers), 'users', null, null, null],
        ['Total cost', $event->money($total), 'currency', null, null, null, 'text-navy-900'],
    ]" />

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
        <div class="min-w-0">
    @if ($items->isEmpty())
        <x-empty icon="cup" title="No food & beverage yet"
                 hint="Coffee breaks, lunches, a gala dinner at an outside restaurant — each occasion is its own line, with its own date, venue and rate.">
            <x-slot:actions>
                <button type="button" wire:click="newItem" class="btn-gold h-10 px-5 text-xs">＋ Add the first occasion</button>
            </x-slot:actions>
        </x-empty>
    @else
        <div class="mb-2.5 flex flex-wrap items-center justify-between gap-2">
            <x-bulk-bar :count="$this->selectedCount()" noun="occasion" />
            <button type="button" wire:click="newItem" class="btn-gold ms-auto h-8 px-3 text-xs">＋ Add Occasion</button>
        </div>
        <div class="space-y-4">
            @foreach ($byDate as $dateKey => $dayItems)
                <div wire:key="day-{{ $dateKey }}">
                    <p class="mb-1.5 flex items-baseline gap-2 text-eyebrow font-bold uppercase tracking-[0.14em] text-navy-400">
                        {{ $dateLabel($dateKey) }}
                        <span class="font-normal normal-case text-muted">· {{ $dayItems->count() }} {{ \Illuminate\Support\Str::plural('occasion', $dayItems->count()) }}</span>
                    </p>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($dayItems as $c)
                            @php [$stLabel, $stClass] = $statusMeta[$c->status] ?? $statusMeta['planned']; @endphp
                            <div wire:key="cat-{{ $c->id }}" class="group/ct op-card {{ $this->isSelected($c->id) ? '!border-navy-900 ring-2 ring-navy-900' : '' }}">
                                <div class="flex flex-1 flex-col p-3.5">
                                    <div class="flex items-start justify-between gap-2.5">
                                        <div class="flex min-w-0 items-center gap-2.5">
                                            <button type="button" wire:click="toggleSelect({{ $c->id }})" class="flex h-4 w-4 shrink-0 items-center justify-center rounded border text-eyebrow {{ $this->isSelected($c->id) ? 'border-navy-900 bg-navy-900 text-white' : 'border-navy-200 text-transparent hover:border-navy-400' }}" title="Select">✓</button>
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" style="color: {{ $moduleHex }}; background: {{ $moduleHex }}15">
                                                <x-icon :name="$typeIcon[$c->type] ?? 'cup'" class="h-4 w-4" />
                                            </span>
                                            <div class="min-w-0">
                                                <p class="pf truncate text-sm font-bold text-navy-900">{{ $c->title }}</p>
                                                <p class="truncate text-micro text-muted">{{ $c->typeLabel() }} · {{ $c->venueLabel() }}</p>
                                            </div>
                                        </div>
                                        <span class="pill shrink-0 {{ $stClass }}">{{ $stLabel }}</span>
                                    </div>
                                    <div class="mt-2.5 flex items-center gap-2 text-eyebrow text-muted">
                                        @if ($c->headcount)<span>{{ number_format($c->headcount) }} covers</span>@endif
                                        @if ($c->supplier)<span class="truncate">· {{ $c->supplier->name }}</span>@endif
                                    </div>
                                </div>
                                <div class="op-card-foot">
                                    <span class="truncate text-eyebrow font-semibold text-navy-700">
                                        {{ $event->money($c->totalCents()) }}
                                        @if ($c->per_person && $c->headcount)<span class="opacity-70">· {{ $event->money($c->cost_cents) }}/pp</span>@endif
                                    </span>
                                    <div class="ms-auto flex items-center gap-1 opacity-100 sm:opacity-0 sm:transition sm:group-hover/ct:opacity-100">
                                        @if ($c->status !== 'confirmed')
                                            <button type="button" wire:click="setStatus({{ $c->id }}, 'confirmed')" class="rounded-md bg-emerald-50 px-2 py-0.5 text-eyebrow font-bold text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-100">✓ Confirm</button>
                                        @endif
                                        <button type="button" wire:click="edit({{ $c->id }})" class="rounded-md bg-white px-1.5 py-0.5 text-eyebrow font-bold text-navy-600 ring-1 ring-line hover:ring-gold-300">✎</button>
                                        <button type="button" wire:click="delete({{ $c->id }})" wire:confirm="Remove {{ $c->title }}?" class="rounded-md bg-risk/10 px-1.5 py-0.5 text-eyebrow font-bold text-red-700 hover:bg-risk/20">✕</button>
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
                    <span class="relative text-2xs font-bold uppercase tracking-[0.18em] text-navy-900">F&amp;B Control</span>
                </div>
                <div class="border-b border-line p-3">
                    <p class="field-label !mb-1.5 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-navy-300"></span> Summary</p>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between"><span class="text-muted">Occasions</span><span class="font-bold text-navy-900">{{ $items->count() }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Confirmed</span><span class="font-bold text-emerald-700">{{ $confirmed }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Covers served</span><span class="font-bold text-navy-900">{{ number_format($covers) }}</span></div>
                        <div class="flex justify-between border-t border-line pt-1.5"><span class="text-muted">Total cost</span><span class="font-bold text-navy-900">{{ $event->money($total) }}</span></div>
                    </div>
                    <x-budget-routing :routing="$this->budgetRouting()" />
                </div>
                <div class="p-3">
                    <button type="button" wire:click="newItem" class="btn-gold h-9 w-full text-xs">＋ Add Occasion</button>
                </div>
            </div>
        </div>
    </div>

    {{-- modal --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit occasion' : 'New occasion'" max="xl" close="set('showForm', false)">
                <form wire:submit="save" class="grid gap-3.5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="field-label !mb-1 !text-eyebrow">What is it</label>
                        <input type="text" wire:model="title" class="input h-10 text-sm" placeholder="Welcome coffee break, Gala dinner…">
                        @error('title')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Type</label>
                        <select wire:model="type" class="input h-10 text-sm">
                            @foreach (\App\Models\EventCateringItem::TYPES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Date</label>
                        <input type="date" wire:model="occasion_date" class="input h-10 text-sm">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="field-label !mb-1 !text-eyebrow">Venue</label>
                        <div class="inline-flex rounded-xl border border-line bg-white p-1">
                            <button type="button" wire:click="$set('venue_mode', 'in_house')" class="rounded-lg px-4 py-1.5 text-xs font-bold transition {{ $venue_mode === 'in_house' ? 'bg-navy-900 text-white' : 'text-navy-600 hover:text-navy-900' }}">In venue</button>
                            <button type="button" wire:click="$set('venue_mode', 'outside')" class="rounded-lg px-4 py-1.5 text-xs font-bold transition {{ $venue_mode === 'outside' ? 'bg-navy-900 text-white' : 'text-navy-600 hover:text-navy-900' }}">Outside</button>
                        </div>
                    </div>
                    @if ($venue_mode === 'in_house')
                        <div class="sm:col-span-2">
                            <label class="field-label !mb-1 !text-eyebrow">Room</label>
                            <select wire:model="room_id" class="input h-10 text-sm">
                                <option value="">— Pick a room —</option>
                                @foreach ($rooms as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach
                            </select>
                        </div>
                    @else
                        <div class="sm:col-span-2">
                            <label class="field-label !mb-1 !text-eyebrow">Location</label>
                            <input type="text" wire:model="location" class="input h-10 text-sm" placeholder="Fakhreldin Restaurant, Amman">
                        </div>
                    @endif

                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Covers</label>
                        <input type="number" min="0" wire:model="headcount" class="input h-10 text-sm" placeholder="—">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Status</label>
                        <select wire:model="status" class="input h-10 text-sm">
                            @foreach (\App\Models\EventCateringItem::STATUSES as $st)<option value="{{ $st }}">{{ ucfirst($st) }}</option>@endforeach
                        </select>
                    </div>

                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Rate ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="cost" class="input h-10 text-sm" placeholder="0">
                        @error('cost')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                    </div>
                    <label class="flex items-end gap-2 pb-2.5">
                        <input type="checkbox" wire:model="per_person" class="h-4 w-4 rounded border-navy-300 text-gold-700">
                        <span class="text-xs font-semibold text-navy-800">Per person, not a flat total</span>
                    </label>

                    <div class="sm:col-span-2">
                        <label class="field-label !mb-1 !text-eyebrow">Supplier</label>
                        <select wire:model="supplier_id" class="input h-10 text-sm">
                            <option value="">— None on file —</option>
                            @foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label !mb-1 !text-eyebrow">Notes</label>
                        <textarea wire:model="notes" rows="2" class="input text-sm" placeholder="Dietary requirements, menu notes…"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="btn-navy h-10 px-6 text-xs">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update' : 'Add occasion' }}</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
        </x-modal>
    @endif
</div>
