@php
    $activeVehicles = $vehicles->where('is_active', true)->count();
    $activeServices = $services->where('is_active', true)->count();
@endphp
<div class="max-w-3xl">
    <div class="mb-5">
        <a href="{{ route('settings.index') }}" class="text-xs font-semibold text-muted hover:text-navy-900">← Settings</a>
        <h1 class="mt-1 text-lg font-bold text-navy-900">Transport Types</h1>
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

                        <button type="button" wire:click="deleteVehicle({{ $v->id }})"
                                wire:confirm="Delete “{{ $v->name }}”? Movements already using it keep their details."
                                class="rounded-lg px-1.5 py-1 text-eyebrow font-bold text-navy-300 opacity-0 transition hover:bg-risk/10 hover:text-red-700 group-hover:opacity-100">✕</button>
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

                        <button type="button" wire:click="deleteService({{ $s->id }})"
                                wire:confirm="Delete “{{ $s->name }}”?"
                                class="rounded-lg px-1.5 py-1 text-eyebrow font-bold text-navy-300 opacity-0 transition hover:bg-risk/10 hover:text-red-700 group-hover:opacity-100">✕</button>
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
    </div>
</div>
