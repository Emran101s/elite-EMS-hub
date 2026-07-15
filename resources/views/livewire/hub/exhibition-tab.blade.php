@php
    $statusMeta = [
        'reserved' => ['Reserved', 'bg-navy-100 text-navy-600'],
        'confirmed' => ['Confirmed', 'bg-amber-100 text-amber-700'],
        'paid' => ['Paid', 'bg-emerald-100 text-emerald-700'],
        'cancelled' => ['Cancelled', 'bg-navy-100 text-navy-400'],
    ];
    $pkgMeta = ['standard' => 'bg-navy-50 text-navy-700', 'premium' => 'bg-gold-500/20 text-gold-700', 'island' => 'bg-navy-900 text-white', 'custom' => 'bg-page text-muted'];
@endphp
<div>
    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
        <div class="min-w-0">

    @if ($exhibitors->isEmpty())
        <div class="card px-6 py-16 text-center">
            <p class="text-sm font-semibold text-navy-900">No exhibitors yet</p>
            <p class="mt-1 text-xs text-muted">Manage the exhibition floor separately from sponsorship — booths, sizes, packages and booth fees.</p>
            <button type="button" wire:click="newItem" class="btn-gold mt-4 h-10 px-5 text-xs">＋ Add the first exhibitor</button>
        </div>
    @else
        <x-bulk-bar :count="$this->selectedCount()" noun="exhibitor" />
        <div class="card overflow-x-auto">
            <table class="w-full min-w-[720px]">
                <thead>
                    <tr class="border-b border-line bg-page/40 text-left text-[0.6rem] font-bold uppercase tracking-wide text-muted">
                        <th class="w-8 pl-4"></th>
                        <th class="px-5 py-2.5">Exhibitor</th>
                        <th class="px-3 py-2.5">Booth</th>
                        <th class="px-3 py-2.5">Package</th>
                        <th class="px-3 py-2.5 text-right">Fee</th>
                        <th class="px-3 py-2.5 text-right">Paid</th>
                        <th class="px-3 py-2.5 text-center">Status</th>
                        <th class="px-3 py-2.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($exhibitors as $x)
                        @php [$stLabel, $stClass] = $statusMeta[$x->status] ?? $statusMeta['reserved']; @endphp
                        <tr wire:key="ex-{{ $x->id }}" class="group/ex border-b border-line last:border-0 hover:bg-page/30 {{ $this->isSelected($x->id) ? 'bg-navy-50/60' : '' }}">
                            <td class="pl-4"><button type="button" wire:click="toggleSelect({{ $x->id }})" class="flex h-4 w-4 items-center justify-center rounded border text-[0.55rem] {{ $this->isSelected($x->id) ? 'border-navy-900 bg-navy-900 text-white' : 'border-navy-200 text-transparent hover:border-navy-400' }}" title="Select">✓</button></td>
                            <td class="px-5 py-3">
                                <p class="text-sm font-semibold text-navy-900">{{ $x->company }}</p>
                                <p class="text-[0.62rem] text-muted">{{ $x->contact_name }}@if ($x->contact_name && $x->email) · @endif{{ $x->email }}</p>
                            </td>
                            <td class="px-3 py-3 text-xs text-navy-700">
                                {{ $x->booth_number ? '#'.$x->booth_number : '—' }}
                                @if ($x->booth_size)<span class="text-muted">· {{ $x->booth_size }}</span>@endif
                            </td>
                            <td class="px-3 py-3"><span class="rounded-full px-2 py-0.5 text-[0.56rem] font-bold uppercase {{ $pkgMeta[$x->package] ?? 'bg-page text-muted' }}">{{ $x->package }}</span></td>
                            <td class="px-3 py-3 text-right text-xs font-semibold text-navy-900">{{ $x->fee_cents ? $event->money($x->fee_cents) : '—' }}</td>
                            <td class="px-3 py-3 text-right text-xs font-semibold text-emerald-700">{{ $x->paid_cents ? $event->money($x->paid_cents) : '—' }}</td>
                            <td class="px-3 py-3 text-center"><span class="rounded-full px-2 py-0.5 text-[0.56rem] font-bold uppercase tracking-wide {{ $stClass }}">{{ $stLabel }}</span></td>
                            <td class="px-3 py-3">
                                <div class="flex items-center justify-end gap-1 opacity-0 transition group-hover/ex:opacity-100">
                                    <button type="button" wire:click="edit({{ $x->id }})" class="rounded-lg bg-navy-50 px-1.5 py-1 text-[0.6rem] font-bold text-navy-600 hover:bg-navy-100">✎</button>
                                    <button type="button" wire:click="delete({{ $x->id }})" wire:confirm="Remove {{ $x->company }}?" class="rounded-lg bg-risk/10 px-1.5 py-1 text-[0.6rem] font-bold text-red-700 hover:bg-risk/20">✕</button>
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
                    <span class="text-xs font-bold uppercase tracking-[0.14em] text-gold-300">Exhibition Controls</span>
                </div>
                {{-- target vs actual --}}
                <div class="border-b border-line p-4">
                    <p class="field-label !mb-2 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Booth income target</p>
                    <div class="flex items-center gap-1.5">
                        <span class="text-sm font-bold text-navy-300">{{ $event->currencySymbol() }}</span>
                        <input type="number" min="0" step="1000" wire:model.live.debounce.600ms="exhibitionTarget" placeholder="0" class="input h-9 flex-1 text-base font-bold">
                    </div>
                    @php $exPct = $target > 0 ? min(100, round($revenueTotal / $target * 100)) : 0; @endphp
                    @if ($target > 0)
                        <div class="mt-2.5">
                            <div class="mb-1 flex justify-between text-[0.58rem] font-semibold text-muted">
                                <span>{{ $event->money($revenueTotal) }} of {{ $event->money($target) }}</span>
                                <span class="{{ $exPct >= 100 ? 'text-emerald-600' : 'text-navy-700' }}">{{ $exPct }}%</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-navy-100">
                                <div class="h-full rounded-full {{ $exPct >= 100 ? 'bg-emerald-500' : 'bg-gold-500' }}" style="width: {{ $exPct }}%"></div>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="border-b border-line p-4">
                    <p class="field-label !mb-2 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-navy-300"></span> Summary</p>
                    <div class="space-y-1.5 text-xs">
                        <div class="flex justify-between"><span class="text-muted">Exhibitors</span><span class="font-bold text-navy-900">{{ $exhibitors->count() }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Confirmed</span><span class="font-bold text-emerald-700">{{ $confirmed }}</span></div>
                        <div class="flex justify-between border-t border-line pt-1.5"><span class="text-muted">Booth revenue</span><span class="font-bold text-navy-900">{{ $event->money($revenueTotal) }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Collected</span><span class="font-bold text-emerald-700">{{ $event->money($collectedTotal) }}</span></div>
                    </div>
                </div>
                <div class="space-y-2 p-4">
                    <button type="button" wire:click="newItem" class="btn-gold h-10 w-full text-xs">＋ Add Exhibitor</button>
                    <a href="{{ route('events.exhibition-floor', $event) }}" class="flex h-10 w-full items-center justify-center gap-1.5 rounded-xl border border-navy-900 bg-navy-900 text-xs font-bold text-white transition hover:bg-navy-800">🗺 Floor plan</a>
                </div>
            </div>
        </div>
    </div>

    {{-- modal --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy-900/40 p-4 pt-16 backdrop-blur-sm">
            <div class="card w-full max-w-xl p-6 shadow-2xl" @click.outside="$wire.set('showForm', false)">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="pf text-base font-bold text-navy-900">{{ $editingId ? 'Edit exhibitor' : 'New exhibitor' }}</h3>
                    <button type="button" wire:click="$set('showForm', false)" class="text-navy-400 hover:text-navy-900">✕</button>
                </div>
                <form wire:submit="save" class="grid gap-3.5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="field-label !mb-1 !text-[0.62rem]">Company</label>
                        <input type="text" wire:model="company" class="input h-10 text-sm" placeholder="Acme Technologies">
                        @error('company')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Contact name</label>
                        <input type="text" wire:model="contact_name" class="input h-10 text-sm" placeholder="—">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Email</label>
                        <input type="email" wire:model="email" class="input h-10 text-sm" placeholder="—">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Booth number</label>
                        <input type="text" wire:model="booth_number" class="input h-10 text-sm" placeholder="A-12">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Booth size</label>
                        <input type="text" wire:model="booth_size" class="input h-10 text-sm" placeholder="3×3 m">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Package</label>
                        <select wire:model="package" class="input h-10 text-sm">
                            @foreach (\App\Models\EventExhibitor::PACKAGES as $p)<option value="{{ $p }}">{{ ucfirst($p) }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Status</label>
                        <select wire:model="status" class="input h-10 text-sm">
                            @foreach (\App\Models\EventExhibitor::STATUSES as $st)<option value="{{ $st }}">{{ ucfirst($st) }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Booth fee ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="fee" class="input h-10 text-sm" placeholder="0">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Paid ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="paid" class="input h-10 text-sm" placeholder="0">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label !mb-1 !text-[0.62rem]">Notes</label>
                        <input type="text" wire:model="notes" class="input h-10 text-sm" placeholder="Deliverables, branding rights…">
                    </div>
                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                        <button type="submit" class="btn-navy h-10 px-6 text-xs">{{ $editingId ? 'Update' : 'Add exhibitor' }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
