@php
    $fmt = fn ($cents) => $event->money($cents);
    $stMeta = \App\Models\EventSponsor::paymentStatusMeta();
    $pkgTone = [
        'platinum' => 'bg-eo-navy text-white', 'gold' => 'bg-gold-500 text-eo-text',
        'silver' => 'bg-eo-bg text-eo-text', 'bronze' => 'bg-amber-100 text-amber-800',
    ];
    $tone = fn ($name) => $pkgTone[mb_strtolower($name)] ?? 'bg-eo-bg text-eo-text';
    $moduleHex = \App\Models\Event::moduleColor('sponsors');
@endphp

<div>
    {{-- Sponsors / Committed / Received / Packages already live in the
         Universal Module Header above this — the lead strip that used to
         repeat them here is gone rather than kept as a second copy. The
         right rail's own Target/Committed/Received/Outstanding summary
         stays: it tells the funnel story (progress toward target, what's
         still uncollected), not just the same four headline numbers. --}}

    <div class="mb-3 flex flex-wrap items-center gap-2">
        <label class="flex h-9 items-center gap-1.5 rounded-xl border border-eo-line bg-white px-3 text-eyebrow font-bold uppercase tracking-wide text-eo-muted shadow-sm">
            Target
            <span class="text-eo-muted">{{ $event->currencySymbol() }}</span>
            <input type="number" min="0" step="1000" wire:model.live.debounce.600ms="sponsorshipTarget" placeholder="0" class="w-24 border-0 bg-transparent p-0 text-right text-sm font-bold text-eo-text focus:outline-none focus:ring-0">
        </label>
        @if ($target > 0 && $committed < $target)
            <p class="text-eyebrow text-eo-muted">{{ $fmt($target - $committed) }} left to target</p>
        @endif
        <button type="button" wire:click="newItem" class="eo-btn-primary ms-auto h-9 px-3.5 text-xs">＋ Sell a sponsorship</button>
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
        {{-- ══════════ MAIN ══════════ --}}
        <div class="min-w-0">
            @if ($sponsors->isEmpty())
                <x-empty icon="star" title="No sponsors sold yet"
                         hint="Set your package prices in the catalog, then sell a package — the amount fills in automatically and rolls up into the budget's income.">
                    <x-slot:actions>
                        <button type="button" wire:click="newItem" class="eo-btn-primary h-10 px-5 text-xs">＋ Sell a sponsorship</button>
                    </x-slot:actions>
                </x-empty>
            @else
                <x-bulk-bar :count="$this->selectedCount()" noun="sponsor" />
                <div class="eo-soft-card overflow-x-auto">
                    <table class="w-full min-w-[560px]">
                        <thead>
                            <tr class="border-b border-eo-line bg-eo-workspace/40 text-left text-eyebrow font-bold uppercase tracking-wide text-eo-muted">
                                <th class="w-8 pl-3.5"></th>
                                <th class="px-3.5 py-2">Sponsor</th>
                                <th class="px-2.5 py-2">Package</th>
                                <th class="px-2.5 py-2 text-right">Amount</th>
                                <th class="px-2.5 py-2 text-right">Paid</th>
                                <th class="px-2.5 py-2 text-center">Status</th>
                                <th class="px-2.5 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sponsors as $s)
                                @php [$stLabel, $stClass] = $stMeta[$s->payment_status] ?? $stMeta['pending']; @endphp
                                <tr wire:key="sp-{{ $s->id }}" class="group/sp border-b border-eo-line last:border-0 hover:bg-eo-workspace/30 {{ $this->isSelected($s->id) ? 'bg-eo-bg/60' : '' }}">
                                    <td class="pl-3.5"><button type="button" wire:click="toggleSelect({{ $s->id }})" class="flex h-4 w-4 items-center justify-center rounded border text-eyebrow {{ $this->isSelected($s->id) ? 'border-eo-navy bg-eo-navy text-white' : 'border-eo-line text-transparent hover:border-eo-line' }}" title="Select">✓</button></td>
                                    <td class="px-3.5 py-2">
                                        <p class="text-[13px] font-semibold text-eo-text">{{ $s->name }}</p>
                                        @if ($s->notes)<p class="truncate text-eyebrow text-eo-muted">{{ $s->notes }}</p>@endif
                                    </td>
                                    <td class="px-2.5 py-2">@if ($s->package)<span class="rounded-full px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide {{ $tone($s->package) }}">{{ $s->package }}</span>@else<span class="text-eo-muted">—</span>@endif</td>
                                    <td class="px-2.5 py-2 text-right text-xs font-bold tabular-nums text-eo-text">{{ $s->amount_cents ? $fmt($s->amount_cents) : '—' }}</td>
                                    <td class="px-2.5 py-2 text-right text-xs font-semibold tabular-nums text-emerald-700">{{ $s->paid_cents ? $fmt($s->paid_cents) : '—' }}</td>
                                    <td class="px-2.5 py-2 text-center"><span class="rounded-full px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide {{ $stClass }}">{{ $stLabel }}</span></td>
                                    <td class="px-2.5 py-2">
                                        <div class="flex items-center justify-end gap-1 opacity-100 transition sm:opacity-0 sm:group-hover/sp:opacity-100">
                                            <button type="button" wire:click="edit({{ $s->id }})" class="rounded-md bg-eo-bg px-1.5 py-0.5 text-eyebrow font-bold text-eo-muted hover:bg-eo-bg">✎</button>
                                            <x-confirm title="Remove {{ $s->name }}?" confirm="Remove" run="$wire.delete({{ $s->id }})"
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

        {{-- ══════════ RIGHT · control rail ══════════ --}}
        <div class="xl:sticky xl:top-12 xl:h-fit">
            <div class="cc-panel">
                <div class="cc-head">
                    <span class="relative flex h-7 w-7 items-center justify-center rounded-lg text-white shadow-sm" style="background: {{ $moduleHex }}">
                        <x-icon name="star" class="h-3.5 w-3.5" />
                    </span>
                    <span class="relative text-2xs font-bold uppercase tracking-[0.18em] text-eo-text">Sponsors Control</span>
                    <span class="relative ml-auto text-eyebrow font-semibold text-eo-muted">{{ $packages->count() }} pkgs</span>
                </div>

                {{-- package catalog --}}
                <div class="divide-y divide-eo-line">
                    @foreach ($packages as $p)
                        <div wire:key="pkg-{{ $p->id }}" class="group/pkg px-4 py-2.5">
                            @if ($editingPackageId === $p->id)
                                <div class="space-y-2">
                                    <input type="text" wire:model="packageEditName" maxlength="60" placeholder="Package name" class="eo-input h-8 w-full text-xs font-semibold">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-eyebrow font-semibold text-eo-muted">{{ $event->currencySymbol() }}</span>
                                        <input type="number" min="0" step="100" wire:model="packageEditPrice" placeholder="Price" class="eo-input h-8 flex-1 text-xs">
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-eyebrow font-semibold text-eo-muted">Max</span>
                                        <input type="number" min="1" step="1" wire:model="packageEditSlots" placeholder="Slots (blank = unlimited)" class="eo-input h-8 flex-1 text-xs">
                                    </div>
                                    <input type="text" wire:model="packageEditBlurb" maxlength="160" placeholder="Short tagline (optional)" class="eo-input h-8 w-full text-micro">
                                    <textarea wire:model="packageEditBenefits" rows="4" placeholder="Benefits — one per line&#10;e.g. Logo on main stage&#10;2 exhibition booths&#10;Speaking slot" class="eo-input w-full py-2 text-micro leading-snug"></textarea>
                                    <div class="flex items-center gap-2">
                                        <button type="button" wire:click="savePackage" class="rounded-lg bg-eo-navy px-3 py-1 text-eyebrow font-bold text-white">Save</button>
                                        <button type="button" wire:click="cancelEditPackage" class="text-eyebrow font-semibold text-eo-muted hover:text-eo-text">Cancel</button>
                                    </div>
                                    @error('packageEditBlurb') <p class="text-eyebrow font-semibold text-eo-risk-ink">{{ $message }}</p> @enderror
                                </div>
                            @else
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 shrink-0 rounded-full {{ $tone($p->name) }}"></span>
                                    @php $bn = count($p->benefits ?? []); $sold = $soldByPackage[$p->name] ?? 0; $left = $p->slots !== null ? max(0, $p->slots - $sold) : null; @endphp
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <span class="truncate text-xs font-bold text-eo-text">{{ $p->name }}</span>
                                            @if ($p->slots !== null)
                                                <span class="shrink-0 rounded-full px-1.5 text-eyebrow font-bold uppercase tracking-wide {{ $left === 0 ? 'bg-eo-risk/10 text-red-700' : 'bg-eo-bg text-eo-muted' }}">{{ $left === 0 ? 'Sold out' : $left.' left' }}</span>
                                            @endif
                                        </div>
                                        <span class="block truncate text-eyebrow text-eo-muted">@if ($p->slots !== null){{ $sold }}/{{ $p->slots }} sold · @endif{{ $bn ? $bn.' '.str('benefit')->plural($bn) : 'no benefits' }}@if ($p->blurb) · {{ $p->blurb }}@endif</span>
                                    </div>
                                    <span class="shrink-0 text-xs font-bold {{ $p->price_cents ? 'text-eo-text' : 'text-eo-muted' }}">{{ $p->price_cents ? $fmt($p->price_cents) : 'set price' }}</span>
                                    <span class="flex shrink-0 items-center gap-1 opacity-100 transition sm:opacity-0 sm:group-hover/pkg:opacity-100">
                                        <a href="{{ route('events.sponsorship', $event) }}?package={{ urlencode($p->name) }}" target="_blank" class="rounded bg-eo-teal-soft px-1.5 py-0.5 text-eyebrow font-bold text-eo-teal-deep hover:bg-eo-teal-soft/70" title="Generate this package sheet">↗</a>
                                        <button type="button" wire:click="startEditPackage({{ $p->id }})" class="rounded bg-eo-bg px-1.5 py-0.5 text-eyebrow font-bold text-eo-muted hover:bg-eo-bg" title="Edit name, price & benefits">✎</button>
                                        <x-confirm title="Remove the “{{ $p->name }}” package?" body="Sold sponsors keep their amount." confirm="Remove" run="$wire.deletePackage({{ $p->id }})"
                                                   class="rounded bg-eo-risk/10 px-1.5 py-0.5 text-eyebrow font-bold text-red-700 hover:bg-eo-risk/20" title="Delete package">✕</x-confirm>
                                    </span>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    {{-- add package --}}
                    <div class="bg-eo-workspace/30 px-4 py-3">
                        <input type="text" wire:model="newPackageName" wire:keydown.enter="addPackage" maxlength="60" class="eo-input mb-2 h-8 w-full text-xs" placeholder="New package name…">
                        <div class="flex items-center gap-1.5">
                            <span class="text-eyebrow font-semibold text-eo-muted">{{ $event->currencySymbol() }}</span>
                            <input type="number" min="0" step="100" wire:model="newPackagePrice" wire:keydown.enter="addPackage" placeholder="Price" class="eo-input h-8 w-20 text-xs">
                            <input type="number" min="1" step="1" wire:model="newPackageSlots" wire:keydown.enter="addPackage" placeholder="Max" class="eo-input h-8 w-14 text-xs" title="Max slots (blank = unlimited)">
                            <button type="button" wire:click="addPackage" class="rounded-lg border border-eo-teal/40 bg-eo-teal-soft px-2.5 py-1 text-eyebrow font-bold text-eo-teal-deep hover:bg-eo-teal-soft/70">Add</button>
                        </div>
                        @error('newPackageName') <p class="mt-1 text-eyebrow font-semibold text-eo-risk-ink">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- summary + action --}}
                <div class="border-t border-eo-line p-4">
                    <div class="mb-3 space-y-1.5 text-xs">
                        <div class="flex justify-between"><span class="text-eo-muted">Target</span><span class="font-bold text-eo-text">{{ $target ? $fmt($target) : '—' }}</span></div>
                        <div class="flex justify-between"><span class="text-eo-muted">Committed</span><span class="font-bold text-eo-text">{{ $fmt($committed) }}</span></div>
                        <div class="flex justify-between"><span class="text-eo-muted">Received</span><span class="font-bold text-emerald-700">{{ $fmt($received) }}</span></div>
                        <div class="flex justify-between border-t border-eo-line pt-1.5"><span class="text-eo-muted">Outstanding</span><span class="font-bold text-eo-text">{{ $fmt(max(0, $committed - $received)) }}</span></div>
                    </div>
                    <button type="button" wire:click="newItem" class="eo-btn-primary h-10 w-full text-xs">＋ Sell a sponsorship</button>
                    <a href="{{ route('events.sponsorship', $event) }}" target="_blank" class="mt-2 flex h-10 w-full items-center justify-center gap-1.5 rounded-xl border border-eo-navy bg-eo-navy text-xs font-bold text-white transition hover:bg-eo-navy-mid">
                        <span class="text-eo-teal-lit">✦</span> Generate prospectus
                    </a>
                    <a href="{{ route('events.hub', [$event, 'tab' => 'budget']) }}" class="mt-2 block text-center text-eyebrow font-semibold text-eo-teal-ink hover:text-eo-teal-deep">Feeds the budget's income →</a>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ sell / edit modal ══ --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit sponsor' : 'Sell a sponsorship'" max="lg" close="set('showForm', false)">
                <form wire:submit="save" class="grid gap-3.5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="eo-label !mb-1 !text-eyebrow">Sponsor name</label>
                        <input type="text" wire:model="name" class="eo-input h-10 text-sm" placeholder="e.g. Royal Jordanian">
                        @error('name')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="eo-label !mb-1 !text-eyebrow">Package</label>
                        <select wire:model.live="package" class="eo-input h-10 text-sm">
                            <option value="">— None —</option>
                            @foreach ($packages as $p)
                                @php $sold = $soldByPackage[$p->name] ?? 0; $left = $p->slots !== null ? max(0, $p->slots - $sold) : null; $full = $left === 0 && $p->name !== $package; @endphp
                                <option value="{{ $p->name }}" @disabled($full)>{{ $p->name }}@if ($p->price_cents) · {{ $fmt($p->price_cents) }}@endif @if ($p->slots !== null) · {{ $full ? 'SOLD OUT' : $left.' left' }}@endif</option>
                            @endforeach
                        </select>
                        @error('package')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                        <p class="mt-1 text-eyebrow text-eo-muted">Picking a package fills the amount.</p>
                    </div>
                    <div>
                        <label class="eo-label !mb-1 !text-eyebrow">Amount ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="amount" class="eo-input h-10 text-sm" placeholder="0">
                        @error('amount')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="eo-label !mb-1 !text-eyebrow">Paid to date ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="paid" class="eo-input h-10 text-sm" placeholder="0">
                    </div>
                    <div>
                        <label class="eo-label !mb-1 !text-eyebrow">Status</label>
                        <select wire:model="payment_status" class="eo-input h-10 text-sm">
                            @foreach (\App\Models\EventSponsor::PAYMENT_STATUSES as $st)<option value="{{ $st }}">{{ ucfirst($st) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="eo-label !mb-1 !text-eyebrow">Notes</label>
                        <input type="text" wire:model="notes" class="eo-input h-10 text-sm" placeholder="Deliverables, branding rights…">
                    </div>
                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-eo-muted hover:text-eo-text">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="eo-btn-navy h-10 px-6 text-xs">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update' : 'Add sponsor' }}</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
        </x-modal>
    @endif
</div>
