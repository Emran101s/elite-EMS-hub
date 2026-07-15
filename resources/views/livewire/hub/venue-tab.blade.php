@php
    $roomTypeLabels = ['main_hall' => 'Main Hall', 'breakout' => 'Breakout', 'exhibition' => 'Exhibition', 'registration' => 'Registration', 'vip' => 'VIP', 'catering' => 'Catering'];
    $typeMeta = [
        'main_hall' => ['building', 'bg-navy-100', 'text-navy-700'],
        'breakout' => ['users', 'bg-sky-100', 'text-sky-600'],
        'exhibition' => ['grid', 'bg-violet-100', 'text-violet-600'],
        'registration' => ['identification', 'bg-indigo-100', 'text-indigo-600'],
        'vip' => ['star', 'bg-amber-100', 'text-amber-700'],
        'catering' => ['home', 'bg-teal-100', 'text-teal-600'],
    ];
    $totalCost = $rooms->sum(fn ($r) => $r->totalCents());
    $totalCap = $rooms->sum('capacity');
    $evReqs = $event->event_requirements ?? [];
    $evTotal = $event->eventRequirementsTotalCents();
@endphp

<div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
    {{-- ══════════ MAIN · venues ══════════ --}}
    <div class="min-w-0">
        <x-bulk-bar :count="$this->selectedCount()" noun="venue" />

        {{-- add / edit form --}}
        @if ($showRoomForm)
            <form wire:submit="saveRoom" class="card mb-4 p-5">
                <h3 class="mb-3 pf text-base font-bold text-navy-900">{{ $editingRoomId ? 'Edit venue' : 'New venue' }}</h3>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="sm:col-span-3">
                        <label class="field-label !mb-1 !text-[0.62rem]" for="room-name">Venue name</label>
                        <input id="room-name" type="text" wire:model="room_name" class="input h-10 text-sm" placeholder="e.g. Main Summit Hall">
                        @error('room_name') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]" for="room-type">Type</label>
                        <select id="room-type" wire:model="room_type" class="input h-10 text-sm">
                            @foreach ($roomTypeLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]" for="room-capacity">Capacity</label>
                        <input id="room-capacity" type="number" min="0" wire:model="room_capacity" class="input h-10 text-sm" placeholder="—">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]" for="room-cost">Hire cost ({{ $event->currency }})</label>
                        <input id="room-cost" type="number" min="0" step="0.01" wire:model="room_cost" class="input h-10 text-sm" placeholder="0">
                        @error('room_cost') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                    </div>
                </div>
                <p class="mt-2 text-[0.6rem] text-muted">Hire cost auto-syncs into the Budget. Equipment &amp; requirements are managed inside the venue.</p>
                <div class="mt-3 flex justify-end gap-2">
                    <button type="button" wire:click="$set('showRoomForm', false)" class="h-9 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                    <button type="submit" class="btn-navy h-9 px-5 text-xs">{{ $editingRoomId ? 'Update venue' : 'Add venue' }}</button>
                </div>
            </form>
        @endif

        <div class="card overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3.5">
                <div>
                    <h3 class="pf text-base font-bold text-navy-900">Venues</h3>
                    <p class="text-[0.62rem] text-muted">{{ $rooms->count() }} {{ str('venue')->plural($rooms->count()) }} · click a venue to edit its layout, equipment &amp; requirements</p>
                </div>
                <button type="button" wire:click="newRoom" class="btn-gold h-9 px-3.5 text-xs">＋ Add Venue</button>
            </div>

            <ul class="divide-y divide-line">
                @forelse ($rooms as $room)
                    @php
                        [$tIcon, $tBg, $tText] = $typeMeta[$room->type] ?? ['building', 'bg-navy-100', 'text-navy-600'];
                        $total = $room->totalCents();
                        $eqCount = count($room->requirements ?? []);
                    @endphp
                    <li class="group flex items-center gap-3 px-4 py-3 transition hover:bg-page/40 {{ $this->isSelected($room->id) ? 'bg-navy-50/60' : '' }}" wire:key="room-{{ $room->id }}">
                        <button type="button" wire:click="toggleSelect({{ $room->id }})" class="flex h-4 w-4 shrink-0 items-center justify-center rounded border text-[0.55rem] {{ $this->isSelected($room->id) ? 'border-navy-900 bg-navy-900 text-white' : 'border-navy-200 text-transparent hover:border-navy-400' }}" title="Select">✓</button>

                        <a href="{{ route('events.room-layout', [$event, $room]) }}" class="flex min-w-0 flex-1 items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $tBg }} {{ $tText }}"><x-icon :name="$tIcon" class="h-4 w-4" /></span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-navy-900 group-hover:text-gold-700">{{ $room->name }}</p>
                                <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[0.6rem] text-muted">
                                    <span class="rounded px-1.5 py-0.5 text-[0.52rem] font-bold uppercase tracking-wide {{ $tBg }} {{ $tText }}">{{ $roomTypeLabels[$room->type] ?? $room->type }}</span>
                                    @if ($room->capacity)<span>{{ number_format($room->capacity) }} pax</span>@endif
                                    @if ($room->sessions_count)<span>{{ $room->sessions_count }} {{ str('session')->plural($room->sessions_count) }}</span>@endif
                                    @if ($eqCount)<span>🎛 {{ $eqCount }} {{ str('item')->plural($eqCount) }}</span>@endif
                                </p>
                            </div>
                        </a>

                        <div class="flex shrink-0 items-center gap-2">
                            @if ($total > 0)<span class="hidden rounded-lg bg-navy-50 px-2 py-0.5 text-[0.62rem] font-bold text-navy-900 sm:inline">{{ $event->money($total) }}</span>@endif
                            <a href="{{ route('events.room-layout', [$event, $room]) }}" class="rounded-lg border border-line bg-white px-2.5 py-1 text-[0.6rem] font-bold text-navy-700 transition hover:border-gold-300 group-hover:border-gold-300">Open →</a>
                            <span class="flex gap-1 opacity-0 transition group-hover:opacity-100">
                                <button type="button" wire:click="editRoom({{ $room->id }})" class="rounded-lg bg-navy-50 px-1.5 py-0.5 text-[0.6rem] font-bold text-navy-600 hover:bg-navy-100" title="Edit">✎</button>
                                <button type="button" wire:click="deleteRoom({{ $room->id }})" wire:confirm="Delete “{{ $room->name }}”? Sessions here become room-less." class="rounded-lg bg-risk/10 px-1.5 py-0.5 text-[0.6rem] font-bold text-red-700 hover:bg-risk/20" title="Delete">✕</button>
                            </span>
                        </div>
                    </li>
                @empty
                    <li class="px-6 py-16 text-center">
                        <p class="text-sm font-semibold text-navy-900">No venues yet</p>
                        <p class="mx-auto mt-1 max-w-sm text-xs text-muted">Add the halls, rooms and areas inside your location — each becomes a schedulable space with its own layout, equipment and requirements.</p>
                        <button type="button" wire:click="newRoom" class="btn-gold mt-4 h-10 px-5 text-xs">＋ Add the first venue</button>
                    </li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- ══════════ RIGHT · control rail ══════════ --}}
    <div class="space-y-4 xl:sticky xl:top-[76px] xl:h-fit">
        <div class="card overflow-hidden">
            <div class="border-b border-line bg-navy-900 px-4 py-3">
                <span class="text-xs font-bold uppercase tracking-[0.14em] text-gold-300">Venue Controls</span>
            </div>

            <div class="border-b border-line p-4">
                <button type="button" wire:click="newRoom" class="btn-gold h-10 w-full text-xs">＋ Add Venue</button>
            </div>

            {{-- location --}}
            <div class="border-b border-line p-4">
                <p class="field-label !mb-2 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Event location</p>
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-gold-400" style="background: linear-gradient(135deg, {{ $event->theme()['primary'] }}, #14315a);"><x-icon name="building" class="h-5 w-5" /></span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold text-navy-900">{{ $event->venue?->name ?? ($event->city ?: 'Not set') }}</p>
                        <p class="truncate text-[0.62rem] text-muted">{{ $event->city }}@if ($event->city && $event->country), {{ $event->country }}@endif</p>
                    </div>
                </div>
                <a href="{{ route('events.hub', [$event, 'tab' => 'settings']) }}" class="mt-2 block text-[0.62rem] font-semibold text-gold-600 hover:text-gold-700">✎ Change in Settings →</a>
            </div>

            {{-- summary --}}
            <div class="p-4">
                <p class="field-label !mb-2 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-navy-300"></span> Summary</p>
                <div class="space-y-1.5 text-xs">
                    <div class="flex justify-between"><span class="text-muted">Venues</span><span class="font-bold text-navy-900">{{ $rooms->count() }}</span></div>
                    <div class="flex justify-between"><span class="text-muted">Total capacity</span><span class="font-bold text-navy-900">{{ $totalCap ? number_format($totalCap).' pax' : '—' }}</span></div>
                    <div class="flex justify-between border-t border-line pt-1.5"><span class="text-muted">Venue cost</span><span class="font-bold text-navy-900">{{ $totalCost ? $event->money($totalCost) : '—' }}</span></div>
                </div>
            </div>
        </div>

        {{-- event-wide requirements --}}
        <div class="card p-4">
            <div class="mb-2 flex items-center justify-between">
                <p class="field-label !mb-0 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Event Requirements</p>
                <span class="text-xs font-bold text-navy-900">{{ $evTotal ? $event->money($evTotal) : '—' }}</span>
            </div>
            <p class="mb-2.5 text-[0.58rem] leading-snug text-muted">General requirements not tied to a venue. Syncs to the Budget.</p>

            <ul class="mb-2.5 divide-y divide-line">
                @forelse ($evReqs as $req)
                    <li wire:key="evreq-{{ $req['id'] }}" class="flex items-center justify-between gap-2 py-1.5 text-xs">
                        <span class="min-w-0 flex-1 truncate text-navy-800">{{ $req['name'] }}</span>
                        <span class="flex shrink-0 items-center gap-1.5">
                            <span class="font-semibold text-navy-900">{{ $event->money($req['cost_cents'] ?? 0) }}</span>
                            <button type="button" wire:click="removeEventRequirement('{{ $req['id'] }}')" class="rounded bg-risk/10 px-1 py-0.5 text-[0.55rem] font-bold text-red-700 hover:bg-risk/20">✕</button>
                        </span>
                    </li>
                @empty
                    <li class="py-1.5 text-[0.62rem] text-muted">None yet.</li>
                @endforelse
            </ul>

            @if ($catalog->isNotEmpty())
                <select wire:change="pickEvReq($event.target.value)" class="input mb-1.5 h-8 w-full text-xs">
                    <option value="">— Pick from catalog —</option>
                    @foreach ($catalog as $ci)<option value="{{ $ci->id }}">{{ $ci->name }}@if ($ci->unit_price_cents) · {{ number_format($ci->unit_price_cents / 100) }}@endif</option>@endforeach
                </select>
            @endif
            <input type="text" wire:model="evReqName" wire:keydown.enter="addEventRequirement" maxlength="120" placeholder="Requirement name…" class="input mb-1.5 h-8 w-full text-xs">
            <div class="flex items-center gap-1.5">
                <span class="text-[0.6rem] font-semibold text-muted">{{ $event->currencySymbol() }}</span>
                <input type="number" min="0" step="0.01" wire:model="evReqCost" wire:keydown.enter="addEventRequirement" placeholder="Cost" class="input h-8 flex-1 text-xs">
                <button type="button" wire:click="addEventRequirement" class="rounded-lg border border-gold-300 bg-gold-50 px-2.5 py-1 text-[0.62rem] font-bold text-gold-700 hover:bg-gold-100">Add</button>
            </div>
            @error('evReqName') <p class="mt-1 text-[0.6rem] font-semibold text-risk">{{ $message }}</p> @enderror
            <a href="{{ route('requirements.index') }}" class="mt-2 block text-center text-[0.6rem] font-semibold text-gold-600 hover:text-gold-700">Manage Requirements Catalog →</a>
        </div>
    </div>
</div>
