@php
    $statusMeta = [
        'planned' => ['Planned', 'bg-navy-100 text-navy-600'],
        'booked' => ['Booked', 'bg-amber-100 text-amber-700'],
        'confirmed' => ['Confirmed', 'bg-emerald-100 text-emerald-700'],
        'completed' => ['Completed', 'bg-sky-100 text-sky-700'],
    ];
    $typeIcon = ['shuttle' => '🚐', 'coach' => '🚌', 'sedan' => '🚗', 'van' => '🚐', 'vip' => '🚘', 'flight' => '✈️'];
@endphp
<div>
    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
        <div class="min-w-0">

    @if ($movements->isEmpty())
        <div class="card px-6 py-16 text-center">
            <p class="text-sm font-semibold text-navy-900">No transport planned yet</p>
            <p class="mt-1 text-xs text-muted">Plan airport transfers, shuttles and VIP cars — routes, providers, timing, capacity and cost.</p>
            <button type="button" wire:click="newItem" class="btn-gold mt-4 h-10 px-5 text-xs">＋ Add the first movement</button>
        </div>
    @else
        <div class="card divide-y divide-line">
            @foreach ($movements as $m)
                @php [$stLabel, $stClass] = $statusMeta[$m->status] ?? $statusMeta['planned']; @endphp
                <div wire:key="tr-{{ $m->id }}" class="group/tr flex items-center gap-4 px-5 py-3.5">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-page text-lg">{{ $typeIcon[$m->type] ?? '🚐' }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-navy-900">{{ $m->route }}</p>
                        <p class="text-[0.62rem] text-muted">
                            {{ ucfirst($m->type) }}@if ($m->provider) · {{ $m->provider }}@endif
                            @if ($m->depart_at) · {{ $m->depart_at->format('D M j, H:i') }}@endif
                        </p>
                    </div>
                    <div class="hidden text-right sm:block">
                        <p class="text-xs font-semibold text-navy-900">{{ $m->passengers }}@if ($m->capacity)/{{ $m->capacity }}@endif pax</p>
                        <p class="text-[0.62rem] text-muted">{{ $m->cost_cents ? $event->money($m->cost_cents) : '—' }}</p>
                    </div>
                    <span class="rounded-full px-2 py-0.5 text-[0.56rem] font-bold uppercase tracking-wide {{ $stClass }}">{{ $stLabel }}</span>
                    <div class="flex items-center gap-1 opacity-0 transition group-hover/tr:opacity-100">
                        <button type="button" wire:click="edit({{ $m->id }})" class="rounded-lg bg-navy-50 px-1.5 py-1 text-[0.6rem] font-bold text-navy-600 hover:bg-navy-100">✎</button>
                        <button type="button" wire:click="delete({{ $m->id }})" wire:confirm="Delete this movement?" class="rounded-lg bg-risk/10 px-1.5 py-1 text-[0.6rem] font-bold text-red-700 hover:bg-risk/20">✕</button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
        </div>

        {{-- ══ control rail ══ --}}
        <div class="xl:sticky xl:top-[76px] xl:h-fit">
            <div class="card overflow-hidden">
                <div class="border-b border-line bg-navy-900 px-4 py-3">
                    <span class="text-xs font-bold uppercase tracking-[0.14em] text-gold-300">Transport Controls</span>
                </div>
                <div class="border-b border-line p-4">
                    <p class="field-label !mb-2 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-navy-300"></span> Summary</p>
                    <div class="space-y-1.5 text-xs">
                        <div class="flex justify-between"><span class="text-muted">Movements</span><span class="font-bold text-navy-900">{{ $movements->count() }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Passengers</span><span class="font-bold text-navy-900">{{ $seatsTotal }}</span></div>
                        <div class="flex justify-between border-t border-line pt-1.5"><span class="text-muted">Transport cost</span><span class="font-bold text-navy-900">{{ $event->money($costTotal) }}</span></div>
                    </div>
                </div>
                <div class="p-4">
                    <button type="button" wire:click="newItem" class="btn-gold h-10 w-full text-xs">＋ Add Movement</button>
                </div>
            </div>
        </div>
    </div>

    {{-- modal --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy-900/40 p-4 pt-16 backdrop-blur-sm">
            <div class="card w-full max-w-xl p-6 shadow-2xl" @click.outside="$wire.set('showForm', false)">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="pf text-base font-bold text-navy-900">{{ $editingId ? 'Edit movement' : 'New movement' }}</h3>
                    <button type="button" wire:click="$set('showForm', false)" class="text-navy-400 hover:text-navy-900">✕</button>
                </div>
                <form wire:submit="save" class="grid gap-3.5 sm:grid-cols-2">
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Type</label>
                        <select wire:model="type" class="input h-10 text-sm">
                            @foreach (\App\Models\EventTransport::TYPES as $t)<option value="{{ $t }}">{{ ucfirst($t) }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Status</label>
                        <select wire:model="status" class="input h-10 text-sm">
                            @foreach (\App\Models\EventTransport::STATUSES as $st)<option value="{{ $st }}">{{ ucfirst($st) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label !mb-1 !text-[0.62rem]">Route</label>
                        <input type="text" wire:model="route" class="input h-10 text-sm" placeholder="Queen Alia Airport → Hotel">
                        @error('route')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Provider</label>
                        <input type="text" wire:model="provider" class="input h-10 text-sm" placeholder="—">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Departure</label>
                        <input type="datetime-local" wire:model="depart_at" class="input h-10 text-sm">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Capacity</label>
                        <input type="number" min="0" wire:model="capacity" class="input h-10 text-sm" placeholder="—">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Passengers</label>
                        <input type="number" min="0" wire:model="passengers" class="input h-10 text-sm" placeholder="0">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Cost ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="cost" class="input h-10 text-sm" placeholder="0">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Notes</label>
                        <input type="text" wire:model="notes" class="input h-10 text-sm" placeholder="—">
                    </div>
                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                        <button type="submit" class="btn-navy h-10 px-6 text-xs">{{ $editingId ? 'Update' : 'Add movement' }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
