@php
    $statusMeta = [
        'reserved' => ['Reserved', 'bg-eo-bg text-eo-muted'],
        'confirmed' => ['Confirmed', 'bg-amber-100 text-amber-700'],
        'paid' => ['Paid', 'bg-emerald-100 text-emerald-700'],
        'cancelled' => ['Cancelled', 'bg-eo-bg text-eo-muted'],
    ];
    $pkgMeta = ['standard' => 'bg-eo-bg text-eo-text', 'premium' => 'bg-gold-500/20 text-gold-700', 'island' => 'bg-eo-navy text-white', 'custom' => 'bg-eo-workspace text-eo-muted'];
    $moduleHex = \App\Models\Event::moduleColor('exhibition');
    $exPct = $target > 0 ? min(100, round($revenueTotal / $target * 100)) : null;
@endphp
<div>
    <div class="mb-3 flex flex-wrap items-center gap-2">
        <a href="{{ route('events.exhibition-floor', $event) }}"
           class="flex h-9 items-center gap-1.5 rounded-xl border border-eo-line bg-white px-3 text-xs font-bold text-eo-text shadow-sm transition hover:border-eo-teal">
            <x-icon name="grid" class="h-3.5 w-3.5" style="color: {{ $moduleHex }}" /> Floor plan
        </a>
        <button type="button" wire:click="newItem" class="eo-btn-primary ms-auto h-9 px-3.5 text-xs">＋ Add Exhibitor</button>
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
        <div class="min-w-0">
    @if ($exhibitors->isEmpty())
        <x-empty icon="grid" title="No exhibitors yet"
                 hint="Manage the exhibition floor separately from sponsorship — booths, sizes, packages and booth fees.">
            <x-slot:actions>
                <button type="button" wire:click="newItem" class="eo-btn-primary h-10 px-5 text-xs">＋ Add the first exhibitor</button>
            </x-slot:actions>
        </x-empty>
    @else
        <x-bulk-bar :count="$this->selectedCount()" noun="exhibitor" />
        <div class="eo-soft-card overflow-x-auto bg-white/90 backdrop-blur-xl">
            <table class="w-full min-w-[720px]">
                <thead>
                    <tr class="border-b border-eo-line bg-eo-workspace/40 text-left text-eyebrow font-bold uppercase tracking-wide text-eo-muted">
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
                <tbody>
                    @foreach ($exhibitors as $x)
                        @php [$stLabel, $stClass] = $statusMeta[$x->status] ?? $statusMeta['reserved']; @endphp
                        <tr wire:key="ex-{{ $x->id }}" class="group/ex border-b border-eo-line last:border-0 hover:bg-eo-workspace/30 {{ $this->isSelected($x->id) ? 'bg-eo-bg/60' : '' }}">
                            <td class="pl-3.5"><button type="button" wire:click="toggleSelect({{ $x->id }})" class="flex h-4 w-4 items-center justify-center rounded border text-eyebrow {{ $this->isSelected($x->id) ? 'border-eo-navy bg-eo-navy text-white' : 'border-eo-line text-transparent hover:border-eo-line' }}" title="Select">✓</button></td>
                            <td class="px-3.5 py-2">
                                <p class="text-[13px] font-semibold text-eo-text">{{ $x->company }}</p>
                                <p class="text-eyebrow text-eo-muted">{{ $x->contact_name }}@if ($x->contact_name && $x->email) · @endif{{ $x->email }}</p>
                            </td>
                            <td class="px-2.5 py-2 text-xs text-eo-text">
                                {{ $x->booth_number ? '#'.$x->booth_number : '—' }}
                                @if ($x->booth_size)<span class="text-eo-muted">· {{ $x->booth_size }}</span>@endif
                            </td>
                            <td class="px-2.5 py-2"><span class="rounded-full px-2 py-0.5 text-eyebrow font-bold uppercase {{ $pkgMeta[$x->package] ?? 'bg-eo-workspace text-eo-muted' }}">{{ $x->package }}</span></td>
                            <td class="px-2.5 py-2 text-right text-xs font-semibold tabular-nums text-eo-text">{{ $x->fee_cents ? $event->money($x->fee_cents) : '—' }}</td>
                            <td class="px-2.5 py-2 text-right text-xs font-semibold tabular-nums text-emerald-700">{{ $x->paid_cents ? $event->money($x->paid_cents) : '—' }}</td>
                            <td class="px-2.5 py-2 text-center"><span class="rounded-full px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide {{ $stClass }}">{{ $stLabel }}</span></td>
                            <td class="px-2.5 py-2">
                                <div class="flex items-center justify-end gap-1 opacity-100 transition sm:opacity-0 sm:group-hover/ex:opacity-100">
                                    <button type="button" wire:click="edit({{ $x->id }})" class="rounded-md bg-eo-bg px-1.5 py-0.5 text-eyebrow font-bold text-eo-muted hover:bg-eo-bg">✎</button>
                                    <x-confirm title="Remove {{ $x->company }}?" confirm="Remove" run="$wire.delete({{ $x->id }})"
                                               class="rounded-md bg-eo-risk/10 px-1.5 py-0.5 text-eyebrow font-bold text-red-700 hover:bg-eo-risk/20">✕</x-confirm>
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
            <div class="cc-panel bg-white/90 backdrop-blur-xl">
                <div class="cc-head border-eo-line bg-eo-navy-deep">
                    <span class="relative flex h-7 w-7 items-center justify-center rounded-lg text-white shadow-sm" style="background: {{ $moduleHex }}">
                        <x-icon name="grid" class="h-3.5 w-3.5" />
                    </span>
                    <span class="relative text-2xs font-bold uppercase tracking-[0.18em] text-white">Exhibition Control</span>
                </div>
                {{-- Exhibitors / Confirmed / Revenue / Collected are the same
                     figures the Universal Module Header already shows above
                     this component — this panel carries only what the header
                     doesn't: the target itself, and how far it is from being met. --}}
                <div class="border-b border-eo-line p-3">
                    <p class="eo-label !mb-1.5 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-eo-teal"></span> Booth income target</p>
                    <div class="flex items-center gap-1.5">
                        <span class="text-sm font-bold text-eo-muted">{{ $event->currencySymbol() }}</span>
                        <input type="number" min="0" step="1000" wire:model.live.debounce.600ms="exhibitionTarget" placeholder="0" class="eo-input h-9 flex-1 text-base font-bold">
                    </div>
                    @if ($target > 0)
                        <div class="mt-2">
                            <div class="mb-1 flex justify-between text-eyebrow font-semibold text-eo-muted">
                                <span>{{ $event->money($revenueTotal) }} of {{ $event->money($target) }}</span>
                                <span class="{{ ($exPct ?? 0) >= 100 ? 'text-emerald-600' : 'text-eo-text' }}">{{ $exPct }}%</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-eo-bg">
                                <div class="h-full rounded-full {{ ($exPct ?? 0) >= 100 ? 'bg-emerald-500' : 'bg-eo-teal' }}" style="width: {{ $exPct }}%"></div>
                            </div>
                            @if ($revenueTotal < $target)
                                <p class="mt-1.5 text-eyebrow text-eo-muted">{{ $event->money($target - $revenueTotal) }} remaining to target</p>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="space-y-2 p-3">
                    <button type="button" wire:click="newItem" class="eo-btn-primary h-9 w-full text-xs">＋ Add Exhibitor</button>
                    <a href="{{ route('events.exhibition-floor', $event) }}" class="flex h-9 w-full items-center justify-center gap-1.5 rounded-xl border border-eo-navy bg-eo-navy text-xs font-bold text-white transition hover:bg-eo-navy-mid">
                        <x-icon name="grid" class="h-3.5 w-3.5 text-eo-teal-lit" /> Floor plan
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
                        <label class="eo-label !mb-1 !text-eyebrow">Company</label>
                        <input type="text" wire:model="company" class="eo-input h-10 text-sm" placeholder="Acme Technologies">
                        @error('company')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="eo-label !mb-1 !text-eyebrow">Contact name</label>
                        <input type="text" wire:model="contact_name" class="eo-input h-10 text-sm" placeholder="—">
                    </div>
                    <div>
                        <label class="eo-label !mb-1 !text-eyebrow">Email</label>
                        <input type="email" wire:model="email" class="eo-input h-10 text-sm" placeholder="—">
                    </div>
                    <div>
                        <label class="eo-label !mb-1 !text-eyebrow">Booth number</label>
                        <input type="text" wire:model="booth_number" class="eo-input h-10 text-sm" placeholder="A-12">
                    </div>
                    <div>
                        <label class="eo-label !mb-1 !text-eyebrow">Booth size</label>
                        <input type="text" wire:model="booth_size" class="eo-input h-10 text-sm" placeholder="3×3 m">
                    </div>
                    <div>
                        <label class="eo-label !mb-1 !text-eyebrow">Package</label>
                        <select wire:model="package" class="eo-input h-10 text-sm">
                            @foreach (\App\Support\Taxonomy::options('exhibitor_package') as $pk => $pl)<option value="{{ $pk }}">{{ $pl }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="eo-label !mb-1 !text-eyebrow">Status</label>
                        <select wire:model="status" class="eo-input h-10 text-sm">
                            @foreach (\App\Models\EventExhibitor::STATUSES as $st)<option value="{{ $st }}">{{ ucfirst($st) }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="eo-label !mb-1 !text-eyebrow">Booth fee ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="fee" class="eo-input h-10 text-sm" placeholder="0">
                    </div>
                    <div>
                        <label class="eo-label !mb-1 !text-eyebrow">Paid ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="paid" class="eo-input h-10 text-sm" placeholder="0">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="eo-label !mb-1 !text-eyebrow">Notes</label>
                        <input type="text" wire:model="notes" class="eo-input h-10 text-sm" placeholder="Deliverables, branding rights…">
                    </div>
                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-eo-muted hover:text-eo-text">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="eo-btn-navy h-10 px-6 text-xs">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update' : 'Add exhibitor' }}</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
        </x-modal>
    @endif
</div>
