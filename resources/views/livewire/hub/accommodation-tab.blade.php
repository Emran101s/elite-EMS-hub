@php
    $statusMeta = [
        'held' => ['Held', 'bg-navy-100 text-navy-600'],
        'booked' => ['Booked', 'bg-amber-100 text-amber-700'],
        'confirmed' => ['Confirmed', 'bg-emerald-100 text-emerald-700'],
        'cancelled' => ['Cancelled', 'bg-navy-100 text-navy-400'],
    ];
@endphp
<div>
    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
        <div class="min-w-0">

    @if ($bookings->isEmpty())
        <div class="card px-6 py-16 text-center">
            <p class="text-sm font-semibold text-navy-900">No accommodation yet</p>
            <p class="mt-1 text-xs text-muted">Track hotel bookings for speakers, VIPs and the organising team — rooms, nights, rates and confirmations.</p>
            <button type="button" wire:click="newItem" class="btn-gold mt-4 h-10 px-5 text-xs">＋ Add the first booking</button>
        </div>
    @else
        <div class="card overflow-x-auto">
            <table class="w-full min-w-[760px]">
                <thead>
                    <tr class="border-b border-line bg-page/40 text-left text-[0.6rem] font-bold uppercase tracking-wide text-muted">
                        <th class="px-5 py-2.5">Hotel / guest</th>
                        <th class="px-3 py-2.5">Dates</th>
                        <th class="px-3 py-2.5 text-center">Rooms</th>
                        <th class="px-3 py-2.5 text-center">Nights</th>
                        <th class="px-3 py-2.5 text-right">Cost</th>
                        <th class="px-3 py-2.5 text-center">Status</th>
                        <th class="px-3 py-2.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bookings as $b)
                        @php [$stLabel, $stClass] = $statusMeta[$b->status] ?? $statusMeta['held']; @endphp
                        <tr wire:key="ac-{{ $b->id }}" class="group/ac border-b border-line last:border-0 hover:bg-page/30">
                            <td class="px-5 py-3">
                                <p class="text-sm font-semibold text-navy-900">{{ $b->hotel }}</p>
                                <p class="text-[0.62rem] text-muted">{{ $b->guest }}@if ($b->guest && $b->room_type) · @endif{{ $b->room_type }}@if ($b->confirmation_number) · #{{ $b->confirmation_number }}@endif</p>
                            </td>
                            <td class="px-3 py-3 text-xs text-navy-700">
                                @if ($b->check_in){{ $b->check_in->format('M j') }} – {{ $b->check_out?->format('M j') ?? '?' }}@else — @endif
                            </td>
                            <td class="px-3 py-3 text-center text-xs font-semibold text-navy-900">{{ $b->rooms }}</td>
                            <td class="px-3 py-3 text-center text-xs text-navy-700">{{ $b->nights() ?: '—' }}</td>
                            <td class="px-3 py-3 text-right text-xs font-semibold text-navy-900">{{ $b->cost_cents ? $event->money($b->cost_cents) : '—' }}</td>
                            <td class="px-3 py-3 text-center"><span class="rounded-full px-2 py-0.5 text-[0.56rem] font-bold uppercase tracking-wide {{ $stClass }}">{{ $stLabel }}</span></td>
                            <td class="px-3 py-3">
                                <div class="flex items-center justify-end gap-1 opacity-0 transition group-hover/ac:opacity-100">
                                    <button type="button" wire:click="edit({{ $b->id }})" class="rounded-lg bg-navy-50 px-1.5 py-1 text-[0.6rem] font-bold text-navy-600 hover:bg-navy-100">✎</button>
                                    <button type="button" wire:click="delete({{ $b->id }})" wire:confirm="Delete this booking?" class="rounded-lg bg-risk/10 px-1.5 py-1 text-[0.6rem] font-bold text-red-700 hover:bg-risk/20">✕</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
        </div>

        {{-- ══ control rail ══ --}}
        <div class="xl:sticky xl:top-[76px] xl:h-fit">
            <div class="card overflow-hidden">
                <div class="border-b border-line bg-navy-900 px-4 py-3">
                    <span class="text-xs font-bold uppercase tracking-[0.14em] text-gold-300">Accommodation Controls</span>
                </div>
                <div class="border-b border-line p-4">
                    <p class="field-label !mb-2 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-navy-300"></span> Summary</p>
                    <div class="space-y-1.5 text-xs">
                        <div class="flex justify-between"><span class="text-muted">Bookings</span><span class="font-bold text-navy-900">{{ $bookings->count() }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Room-nights</span><span class="font-bold text-navy-900">{{ $roomNightsTotal }}</span></div>
                        <div class="flex justify-between border-t border-line pt-1.5"><span class="text-muted">Accommodation cost</span><span class="font-bold text-navy-900">{{ $event->money($costTotal) }}</span></div>
                    </div>
                </div>
                <div class="p-4">
                    <button type="button" wire:click="newItem" class="btn-gold h-10 w-full text-xs">＋ Add Booking</button>
                </div>
            </div>
        </div>
    </div>

    {{-- modal --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy-900/40 p-4 pt-16 backdrop-blur-sm">
            <div class="card w-full max-w-xl p-6 shadow-2xl" @click.outside="$wire.set('showForm', false)">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="pf text-base font-bold text-navy-900">{{ $editingId ? 'Edit booking' : 'New booking' }}</h3>
                    <button type="button" wire:click="$set('showForm', false)" class="text-navy-400 hover:text-navy-900">✕</button>
                </div>
                <form wire:submit="save" class="grid gap-3.5 sm:grid-cols-2">
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Hotel</label>
                        <input type="text" wire:model="hotel" class="input h-10 text-sm" placeholder="Kempinski Amman">
                        @error('hotel')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Guest / group</label>
                        <input type="text" wire:model="guest" class="input h-10 text-sm" placeholder="Keynote speakers">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Room type</label>
                        <input type="text" wire:model="room_type" class="input h-10 text-sm" placeholder="Deluxe King">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Rooms</label>
                        <input type="number" min="1" wire:model="rooms" class="input h-10 text-sm">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Check-in</label>
                        <input type="date" wire:model="check_in" class="input h-10 text-sm">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Check-out</label>
                        <input type="date" wire:model="check_out" class="input h-10 text-sm">
                        @error('check_out')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Rate / night ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="rate" class="input h-10 text-sm" placeholder="0">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Total cost ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="cost" class="input h-10 text-sm" placeholder="auto from rate">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Status</label>
                        <select wire:model="status" class="input h-10 text-sm">
                            @foreach (\App\Models\EventAccommodation::STATUSES as $st)<option value="{{ $st }}">{{ ucfirst($st) }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Confirmation #</label>
                        <input type="text" wire:model="confirmation_number" class="input h-10 text-sm" placeholder="—">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label !mb-1 !text-[0.62rem]">Notes</label>
                        <input type="text" wire:model="notes" class="input h-10 text-sm" placeholder="—">
                    </div>
                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                        <button type="submit" class="btn-navy h-10 px-6 text-xs">{{ $editingId ? 'Update' : 'Add booking' }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
