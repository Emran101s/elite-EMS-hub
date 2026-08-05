@php
    $activeVehicles = $vehicles->where('is_active', true)->count();
    $activeServices = $services->where('is_active', true)->count();
@endphp
<div class="max-w-4xl">
    <div class="mb-5">
        <a href="{{ route('settings.index') }}" class="text-xs font-semibold text-muted hover:text-navy-900">← Settings</a>
        <h1 class="mt-1 text-lg font-bold text-navy-900">Transport Catalogue</h1>
        <p class="text-xs text-muted">
            The options offered when you add a movement to an event. Keep the list short —
            switch on the extras only when a job actually needs them.
        </p>
    </div>

    <div class="grid gap-5">

        {{-- ══ vehicles ══ --}}
        <div class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-line bg-navy-900 px-5 py-3">
                <div>
                    <span class="text-xs font-bold uppercase tracking-[0.14em] text-gold-300">Vehicles</span>
                    <p class="mt-0.5 text-eyebrow text-white/45">What you move people in, and how many fit.</p>
                </div>
                <span class="rounded-full bg-white/10 px-2.5 py-1 text-eyebrow font-bold text-white/70">{{ $activeVehicles }} in use</span>
            </div>

            <div class="divide-y divide-line">
                @foreach ($vehicles as $v)
                    <div wire:key="veh-{{ $v->id }}"
                         class="group flex items-center gap-3 px-5 py-2.5 {{ $v->is_active ? '' : 'bg-page/40' }}">
                        {{-- on/off --}}
                        <button type="button" wire:click="toggleVehicle({{ $v->id }})"
                                title="{{ $v->is_active ? 'In use — click to hide' : 'Hidden — click to use' }}"
                                class="relative h-5 w-9 shrink-0 rounded-full transition {{ $v->is_active ? 'bg-gold-400' : 'bg-navy-200' }}">
                            <span class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-all {{ $v->is_active ? 'left-[1.125rem]' : 'left-0.5' }}"></span>
                        </button>

                        <input type="text" value="{{ $v->name }}"
                               wire:change="updateVehicle({{ $v->id }}, 'name', $event.target.value)"
                               class="flex-1 rounded-lg border border-transparent bg-transparent px-2 py-1 text-sm font-semibold {{ $v->is_active ? 'text-navy-900' : 'text-navy-400' }} hover:border-line focus:border-gold-400 focus:bg-white focus:outline-none">

                        <div class="flex shrink-0 items-center gap-1.5">
                            <span class="text-eyebrow font-bold uppercase tracking-wide text-muted">max</span>
                            <input type="number" min="1" value="{{ $v->capacity }}"
                                   wire:change="updateVehicle({{ $v->id }}, 'capacity', $event.target.value)"
                                   class="w-16 rounded-lg border border-line bg-white px-2 py-1 text-center text-sm font-bold text-navy-900 focus:border-gold-400 focus:outline-none">
                            <span class="w-14 text-eyebrow text-muted">passengers</span>
                        </div>

                        <x-confirm title="Delete “{{ $v->name }}”?"
                                   body="Movements already using it keep their details."
                                   confirm="Delete" run="$wire.deleteVehicle({{ $v->id }})"
                                   class="rounded-lg px-1.5 py-1 text-eyebrow font-bold text-navy-300 opacity-0 transition hover:bg-risk/10 hover:text-red-700 group-hover:opacity-100">✕</x-confirm>
                    </div>
                @endforeach
            </div>

            <div class="flex items-center gap-2 border-t border-line bg-page/40 px-5 py-3">
                <input type="text" wire:model="newVehicle" wire:keydown.enter.prevent="addVehicle"
                       placeholder="New vehicle type…" class="input h-9 flex-1 text-sm">
                <input type="number" min="1" wire:model="newVehicleCapacity" wire:keydown.enter.prevent="addVehicle"
                       class="input h-9 w-20 text-center text-sm" title="Max passengers">
                <button type="button" wire:click="addVehicle" class="btn-navy h-9 px-4 text-xs">＋ Add</button>
            </div>
            @error('newVehicle')<p class="border-t border-line bg-page/40 px-5 pb-3 text-xs text-risk">{{ $message }}</p>@enderror
        </div>

        {{-- ══ services ══ --}}
        <div class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-line bg-navy-900 px-5 py-3">
                <div>
                    <span class="text-xs font-bold uppercase tracking-[0.14em] text-gold-300">Service Types</span>
                    <p class="mt-0.5 text-eyebrow text-white/45">What the movement is — a transfer, a full day, a pickup.</p>
                </div>
                <span class="rounded-full bg-white/10 px-2.5 py-1 text-eyebrow font-bold text-white/70">{{ $activeServices }} in use</span>
            </div>

            <div class="divide-y divide-line">
                @foreach ($services as $s)
                    <div wire:key="svc-{{ $s->id }}"
                         class="group flex items-center gap-3 px-5 py-2.5 {{ $s->is_active ? '' : 'bg-page/40' }}">
                        <button type="button" wire:click="toggleService({{ $s->id }})"
                                title="{{ $s->is_active ? 'In use — click to hide' : 'Hidden — click to use' }}"
                                class="relative h-5 w-9 shrink-0 rounded-full transition {{ $s->is_active ? 'bg-gold-400' : 'bg-navy-200' }}">
                            <span class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-all {{ $s->is_active ? 'left-[1.125rem]' : 'left-0.5' }}"></span>
                        </button>

                        <input type="text" value="{{ $s->name }}"
                               wire:change="updateService({{ $s->id }}, $event.target.value)"
                               class="flex-1 rounded-lg border border-transparent bg-transparent px-2 py-1 text-sm font-semibold {{ $s->is_active ? 'text-navy-900' : 'text-navy-400' }} hover:border-line focus:border-gold-400 focus:bg-white focus:outline-none">

                        <x-confirm title="Delete “{{ $s->name }}”?"
                                   confirm="Delete" run="$wire.deleteService({{ $s->id }})"
                                   class="rounded-lg px-1.5 py-1 text-eyebrow font-bold text-navy-300 opacity-0 transition hover:bg-risk/10 hover:text-red-700 group-hover:opacity-100">✕</x-confirm>
                    </div>
                @endforeach
            </div>

            <div class="flex items-center gap-2 border-t border-line bg-page/40 px-5 py-3">
                <input type="text" wire:model="newService" wire:keydown.enter.prevent="addService"
                       placeholder="New service type…" class="input h-9 flex-1 text-sm">
                <button type="button" wire:click="addService" class="btn-navy h-9 px-4 text-xs">＋ Add</button>
            </div>
            @error('newService')<p class="border-t border-line bg-page/40 px-5 pb-3 text-xs text-risk">{{ $message }}</p>@enderror
        </div>

        {{-- ══ drivers ══ --}}
        <div class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-line bg-navy-900 px-5 py-3">
                <div>
                    <span class="text-xs font-bold uppercase tracking-[0.14em] text-gold-300">Drivers</span>
                    <p class="mt-0.5 text-eyebrow text-white/45">The people you hire — what makes trip sheets and overload warnings possible.</p>
                </div>
                <span class="rounded-full bg-white/10 px-2.5 py-1 text-eyebrow font-bold text-white/70">{{ $drivers->where('is_active', true)->count() }} in use</span>
            </div>

            <div class="divide-y divide-line">
                @forelse ($drivers as $d)
                    <div wire:key="drv-{{ $d->id }}" class="group flex flex-wrap items-center gap-2 px-5 py-2.5">
                        <button type="button" wire:click="toggleDriver({{ $d->id }})"
                                title="{{ $d->is_active ? 'Available for assignment' : 'Off the roster' }}"
                                class="relative h-5 w-9 shrink-0 rounded-full transition {{ $d->is_active ? 'bg-gold-400' : 'bg-navy-200' }}">
                            <span class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-all {{ $d->is_active ? 'left-[1.125rem]' : 'left-0.5' }}"></span>
                        </button>

                        <input type="text" value="{{ $d->name }}"
                               wire:change="updateDriver({{ $d->id }}, 'name', $event.target.value)"
                               class="min-w-[9rem] flex-1 rounded-lg border border-transparent bg-transparent px-2 py-1 text-sm font-semibold {{ $d->is_active ? 'text-navy-900' : 'text-navy-400' }} hover:border-line focus:border-gold-400 focus:bg-white focus:outline-none">

                        <input type="text" value="{{ $d->phone }}" placeholder="phone"
                               wire:change="updateDriver({{ $d->id }}, 'phone', $event.target.value)"
                               class="w-36 rounded-lg border border-transparent bg-transparent px-2 py-1 text-xs text-navy-700 hover:border-line focus:border-gold-400 focus:bg-white focus:outline-none">

                        <select wire:change="setDriverSupplier({{ $d->id }}, $event.target.value)"
                                class="h-8 w-auto max-w-[10rem] rounded-lg border border-line px-1.5 text-eyebrow font-semibold text-navy-700">
                            <option value="">— supplier —</option>
                            @foreach ($suppliers as $s)
                                <option value="{{ $s->id }}" @selected($d->supplier_id === $s->id)>{{ $s->name }}</option>
                            @endforeach
                        </select>

                        <x-confirm title="Delete {{ $d->name }}?"
                                   body="Trips they were assigned to are kept — they just lose their driver."
                                   confirm="Delete" run="$wire.deleteDriver({{ $d->id }})"
                                   class="rounded-lg px-1.5 py-1 text-eyebrow font-bold text-navy-300 opacity-0 transition hover:bg-risk/10 hover:text-red-700 group-hover:opacity-100">✕</x-confirm>
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-xs text-muted">
                        No drivers yet. Add the ones you use and they'll be offered when you assign a movement.
                    </p>
                @endforelse
            </div>

            <div class="flex flex-wrap items-center gap-2 border-t border-line bg-page/40 px-5 py-3">
                <input type="text" wire:model="newDriver" wire:keydown.enter.prevent="addDriver"
                       placeholder="Driver name…" class="input h-9 min-w-[10rem] flex-1 text-sm">
                <input type="text" wire:model="newDriverPhone" wire:keydown.enter.prevent="addDriver"
                       placeholder="Phone" class="input h-9 w-40 text-sm">
                <select wire:model="newDriverSupplier" class="input h-9 w-auto text-sm">
                    <option value="">— supplier —</option>
                    @foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                </select>
                <button type="button" wire:click="addDriver" class="btn-navy h-9 px-4 text-xs">＋ Add</button>
            </div>
        </div>

        {{-- ══ the fleet: specific cars with plates ══ --}}
        <div class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-line bg-navy-900 px-5 py-3">
                <div>
                    <span class="text-xs font-bold uppercase tracking-[0.14em] text-gold-300">Fleet</span>
                    <p class="mt-0.5 text-eyebrow text-white/45">Specific cars with plates — optional, and worth it for VIP cars and coaches.</p>
                </div>
                <span class="rounded-full bg-white/10 px-2.5 py-1 text-eyebrow font-bold text-white/70">{{ $fleet->where('is_active', true)->count() }} in use</span>
            </div>

            <div class="divide-y divide-line">
                @forelse ($fleet as $v)
                    <div wire:key="flt-{{ $v->id }}" class="group flex flex-wrap items-center gap-2 px-5 py-2.5">
                        <button type="button" wire:click="toggleFleetVehicle({{ $v->id }})"
                                title="{{ $v->is_active ? 'In the fleet — click to retire' : 'Retired — click to use' }}"
                                class="relative h-5 w-9 shrink-0 rounded-full transition {{ $v->is_active ? 'bg-gold-400' : 'bg-navy-200' }}">
                            <span class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-all {{ $v->is_active ? 'left-[1.125rem]' : 'left-0.5' }}"></span>
                        </button>

                        <input type="text" value="{{ $v->plate_no }}" placeholder="plate"
                               wire:change="updateFleetVehicle({{ $v->id }}, 'plate_no', $event.target.value)"
                               class="w-28 rounded-lg border border-transparent bg-transparent px-2 py-1 text-sm font-bold {{ $v->is_active ? 'text-navy-900' : 'text-navy-400' }} hover:border-line focus:border-gold-400 focus:bg-white focus:outline-none">

                        <input type="text" value="{{ $v->model }}" placeholder="model"
                               wire:change="updateFleetVehicle({{ $v->id }}, 'model', $event.target.value)"
                               class="min-w-[8rem] flex-1 rounded-lg border border-transparent bg-transparent px-2 py-1 text-xs text-navy-700 hover:border-line focus:border-gold-400 focus:bg-white focus:outline-none">

                        <select wire:change="setFleetVehicleType({{ $v->id }}, $event.target.value)"
                                class="h-8 w-auto max-w-[10rem] rounded-lg border border-line px-1.5 text-eyebrow font-semibold text-navy-700">
                            <option value="">— type —</option>
                            @foreach ($vehicles as $t)
                                <option value="{{ $t->id }}" @selected($v->vehicle_type_id === $t->id)>{{ $t->label() }}</option>
                            @endforeach
                        </select>

                        <select wire:change="setFleetSupplier({{ $v->id }}, $event.target.value)"
                                class="h-8 w-auto max-w-[10rem] rounded-lg border border-line px-1.5 text-eyebrow font-semibold text-navy-700">
                            <option value="">— supplier —</option>
                            @foreach ($suppliers as $s)
                                <option value="{{ $s->id }}" @selected($v->supplier_id === $s->id)>{{ $s->name }}</option>
                            @endforeach
                        </select>

                        <x-confirm title="Delete {{ $v->label() }}?"
                                   body="Trips it was assigned to are kept."
                                   confirm="Delete" run="$wire.deleteFleetVehicle({{ $v->id }})"
                                   class="rounded-lg px-1.5 py-1 text-eyebrow font-bold text-navy-300 opacity-0 transition hover:bg-risk/10 hover:text-red-700 group-hover:opacity-100">✕</x-confirm>
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-xs text-muted">
                        No specific cars yet. You can plan entirely with vehicle types above —
                        add plates only where they matter.
                    </p>
                @endforelse
            </div>

            <div class="flex flex-wrap items-center gap-2 border-t border-line bg-page/40 px-5 py-3">
                <input type="text" wire:model="newPlate" wire:keydown.enter.prevent="addFleetVehicle"
                       placeholder="Plate…" class="input h-9 w-32 text-sm">
                <input type="text" wire:model="newModel" wire:keydown.enter.prevent="addFleetVehicle"
                       placeholder="Model, e.g. Mercedes V-Class" class="input h-9 min-w-[10rem] flex-1 text-sm">
                <select wire:model="newFleetType" class="input h-9 w-auto text-sm">
                    <option value="">— type —</option>
                    @foreach ($vehicles as $t)<option value="{{ $t->id }}">{{ $t->label() }}</option>@endforeach
                </select>
                <button type="button" wire:click="addFleetVehicle" class="btn-navy h-9 px-4 text-xs">＋ Add</button>
            </div>
            @error('newPlate')<p class="border-t border-line bg-page/40 px-5 pb-3 text-xs text-risk">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
