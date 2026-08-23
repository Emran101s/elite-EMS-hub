@php
    $statusMeta = [
        'reserved' => ['Reserved', 'bg-page text-muted'],
        'confirmed' => ['Confirmed', 'bg-warning-soft text-warning-ink'],
        'paid' => ['Paid', 'bg-success-soft text-success-ink'],
        'cancelled' => ['Cancelled', 'bg-page text-muted'],
    ];
    $pkgMeta = ['standard' => 'bg-page text-ink', 'premium' => 'bg-gold-50 text-gold-700', 'island' => 'bg-navy-900 text-white', 'custom' => 'bg-page text-muted'];
    $moduleHex = \App\Models\Event::moduleColor('exhibition');
    $exPct = $target > 0 ? min(100, round($revenueTotal / $target * 100)) : null;
@endphp
<div>
    <div class="mb-3 flex flex-wrap items-center gap-2">
        <a href="{{ route('events.exhibition-floor', $event) }}"
           class="flex h-9 items-center gap-1.5 rounded-full border border-line bg-white px-3 text-xs font-bold text-ink shadow-sm transition hover:border-navy-300">
            <x-icon name="grid" class="h-3.5 w-3.5" style="color: {{ $moduleHex }}" /> Floor plan
        </a>
        <button type="button" wire:click="newItem" class="ms-auto h-9 rounded-full bg-gold-500 px-3.5 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">＋ Add Exhibitor</button>
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
        <div class="min-w-0">
    @if ($exhibitors->isEmpty())
        <x-empty icon="grid" title="No exhibitors yet"
                 hint="Manage the exhibition floor separately from sponsorship — booths, sizes, packages and booth fees.">
            <x-slot:actions>
                <button type="button" wire:click="newItem" class="h-10 rounded-full bg-gold-500 px-5 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">＋ Add the first exhibitor</button>
            </x-slot:actions>
        </x-empty>
    @else
        <x-bulk-bar :count="$this->selectedCount()" noun="exhibitor" />
        <div class="overflow-hidden overflow-x-auto rounded-lg border border-line bg-white">
            <table class="w-full min-w-[720px]">
                <thead>
                    <tr class="border-b border-line bg-page text-left text-eyebrow font-bold uppercase tracking-wide text-muted">
                        <th class="w-8 pl-3.5"></th>
                        <th class="px-3.5 py-2">Exhibitor</th>
                        <th class="px-2.5 py-2">Booth</th>
                        <th class="px-2.5 py-2">Package</th>
                        <th class="px-2.5 py-2 text-right">Fee</th>
                        <th class="px-2.5 py-2 text-right">Paid</th>
                        <th class="px-2.5 py-2 text-center">Status</th>
                        <th class="px-2.5 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($exhibitors as $x)
                        @php [$stLabel, $stClass] = $statusMeta[$x->status] ?? $statusMeta['reserved']; @endphp
                        <tr wire:key="ex-{{ $x->id }}" class="group/ex transition hover:bg-page {{ $this->isSelected($x->id) ? 'bg-page' : '' }}">
                            <td class="pl-3.5"><button type="button" wire:click="toggleSelect({{ $x->id }})" class="flex h-4 w-4 items-center justify-center rounded border text-eyebrow {{ $this->isSelected($x->id) ? 'border-navy-900 bg-navy-900 text-white' : 'border-line text-transparent hover:border-muted' }}" title="Select">✓</button></td>
                            <td class="px-3.5 py-2">
                                <p class="text-[13px] font-semibold text-ink">{{ $x->company }}</p>
                                <p class="text-eyebrow text-muted">{{ $x->contact_name }}@if ($x->contact_name && $x->email) · @endif{{ $x->email }}</p>
                            </td>
                            <td class="px-2.5 py-2 text-xs text-ink">
                                {{ $x->booth_number ? '#'.$x->booth_number : '—' }}
                                @if ($x->booth_size)<span class="text-muted">· {{ $x->booth_size }}</span>@endif
                            </td>
                            <td class="px-2.5 py-2"><span class="rounded-full px-2 py-0.5 text-eyebrow font-bold uppercase {{ $pkgMeta[$x->package] ?? 'bg-page text-muted' }}">{{ $x->package }}</span></td>
                            <td class="px-2.5 py-2 text-right text-xs font-semibold tabular-nums text-ink">{{ $x->fee_cents ? $event->money($x->fee_cents) : '—' }}</td>
                            <td class="px-2.5 py-2 text-right text-xs font-semibold tabular-nums text-success-ink">{{ $x->paid_cents ? $event->money($x->paid_cents) : '—' }}</td>
                            <td class="px-2.5 py-2 text-center"><span class="rounded-full px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide {{ $stClass }}">{{ $stLabel }}</span></td>
                            <td class="px-2.5 py-2">
                                <div class="flex items-center justify-end gap-1 opacity-100 transition sm:opacity-0 sm:group-hover/ex:opacity-100">
                                    <button type="button" wire:click="edit({{ $x->id }})" class="rounded-md bg-page px-1.5 py-0.5 text-eyebrow font-bold text-muted hover:bg-line">✎</button>
                                    <x-confirm title="Remove {{ $x->company }}?" confirm="Remove" run="$wire.delete({{ $x->id }})"
                                               class="rounded-md bg-danger-soft px-1.5 py-0.5 text-eyebrow font-bold text-danger-ink hover:bg-danger-soft/70">✕</x-confirm>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
        </div>

        <div class="xl:sticky xl:top-12 xl:h-fit">
            <div class="cc-panel">
                <div class="cc-head">
                    <span class="relative flex h-7 w-7 items-center justify-center rounded-lg text-white shadow-sm" style="background: {{ $moduleHex }}">
                        <x-icon name="grid" class="h-3.5 w-3.5" />
                    </span>
                    <span class="relative text-2xs font-bold uppercase tracking-[0.18em] text-ink">Exhibition Control</span>
                </div>
                {{-- Exhibitors / Confirmed / Revenue / Collected are the same
                     figures the Universal Module Header already shows above
                     this component — this panel carries only what the header
                     doesn't: the target itself, and how far it is from being met. --}}
                <div class="border-b border-line p-3">
                    <p class="mb-1.5 flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.12em] text-muted"><span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Booth income target</p>
                    <div class="flex items-center gap-1.5">
                        <span class="text-sm font-bold text-muted">{{ $event->currencySymbol() }}</span>
                        <input type="number" min="0" step="1000" wire:model.live.debounce.600ms="exhibitionTarget" placeholder="0" class="h-9 flex-1 rounded-lg border border-line bg-white px-2 text-base font-bold text-ink focus:border-navy-300 focus:outline-none">
                    </div>
                    @if ($target > 0)
                        <div class="mt-2">
                            <div class="mb-1 flex justify-between text-eyebrow font-semibold text-muted">
                                <span>{{ $event->money($revenueTotal) }} of {{ $event->money($target) }}</span>
                                <span class="{{ ($exPct ?? 0) >= 100 ? 'text-success-ink' : 'text-ink' }}">{{ $exPct }}%</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-page">
                                <div class="h-full rounded-full {{ ($exPct ?? 0) >= 100 ? 'bg-success' : 'bg-gold-500' }}" style="width: {{ $exPct }}%"></div>
                            </div>
                            @if ($revenueTotal < $target)
                                <p class="mt-1.5 text-eyebrow text-muted">{{ $event->money($target - $revenueTotal) }} remaining to target</p>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="space-y-2 p-3">
                    <button type="button" wire:click="newItem" class="h-9 w-full rounded-full bg-gold-500 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">＋ Add Exhibitor</button>
                    <a href="{{ route('events.exhibition-floor', $event) }}" class="flex h-9 w-full items-center justify-center gap-1.5 rounded-full border border-line bg-white text-xs font-bold text-ink transition hover:border-navy-300">
                        <x-icon name="grid" class="h-3.5 w-3.5 text-gold-600" /> Floor plan
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- modal --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit exhibitor' : 'New exhibitor'" max="xl" close="set('showForm', false)">
                <form wire:submit="save" class="grid gap-3.5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Company</label>
                        <input type="text" wire:model="company" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Acme Technologies">
                        @error('company')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Contact name</label>
                        <input type="text" wire:model="contact_name" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="—">
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Email</label>
                        <input type="email" wire:model="email" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="—">
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Booth number</label>
                        <input type="text" wire:model="booth_number" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="A-12">
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Booth size</label>
                        <input type="text" wire:model="booth_size" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="3×3 m">
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Package</label>
                        <select wire:model="package" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                            @foreach (\App\Support\Taxonomy::options('exhibitor_package') as $pk => $pl)<option value="{{ $pk }}">{{ $pl }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Status</label>
                        <select wire:model="status" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                            @foreach (\App\Models\EventExhibitor::STATUSES as $st)<option value="{{ $st }}">{{ ucfirst($st) }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Booth fee ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="fee" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="0">
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Paid ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="paid" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="0">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Notes</label>
                        <input type="text" wire:model="notes" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Deliverables, branding rights…">
                    </div>
                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-muted hover:text-ink">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="h-10 rounded-full bg-gold-500 px-6 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update' : 'Add exhibitor' }}</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
        </x-modal>
    @endif
</div>
