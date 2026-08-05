@php
    $fmt = fn ($cents) => $event->money($cents);
    $stMeta = \App\Models\EventSponsor::paymentStatusMeta();
    $pkgTone = [
        'platinum' => 'bg-navy-900 text-white', 'gold' => 'bg-gold-500 text-navy-900',
        'silver' => 'bg-navy-100 text-navy-700', 'bronze' => 'bg-amber-100 text-amber-800',
    ];
    $tone = fn ($name) => $pkgTone[mb_strtolower($name)] ?? 'bg-navy-50 text-navy-700';
    $moduleHex = \App\Models\Event::moduleColor('sponsors');
@endphp

<div>
    <x-stat-strip class="mb-3" :stats="[
        ['Sponsors', $soldCount, 'star', null, null, null],
        ['Committed', $fmt($committed), 'currency', $target > 0 ? $progressPct : null, $progressPct >= 100 ? 'bg-emerald-500' : 'bg-gold-400', $target ? 'of '.$fmt($target) : 'Set a target →', 'text-navy-900'],
        ['Received', $fmt($received), 'check', null, null, null, 'text-emerald-600'],
        ['Packages', $packages->count(), 'archive', null, null, 'In the catalog'],
    ]" />

    <div class="mb-3 flex flex-wrap items-center gap-2">
        <label class="flex h-9 items-center gap-1.5 rounded-xl border border-line bg-white px-3 text-eyebrow font-bold uppercase tracking-wide text-muted shadow-sm">
            Target
            <span class="text-navy-300">{{ $event->currencySymbol() }}</span>
            <input type="number" min="0" step="1000" wire:model.live.debounce.600ms="sponsorshipTarget" placeholder="0" class="w-24 border-0 bg-transparent p-0 text-right text-sm font-bold text-navy-900 focus:outline-none focus:ring-0">
        </label>
        @if ($target > 0 && $committed < $target)
            <p class="text-eyebrow text-muted">{{ $fmt($target - $committed) }} left to target</p>
        @endif
        <button type="button" wire:click="newItem" class="btn-gold ms-auto h-9 px-3.5 text-xs">＋ Sell a sponsorship</button>
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
        {{-- ══════════ MAIN ══════════ --}}
        <div class="min-w-0">
            @if ($sponsors->isEmpty())
                <x-empty icon="star" title="No sponsors sold yet"
                         hint="Set your package prices in the catalog, then sell a package — the amount fills in automatically and rolls up into the budget's income.">
                    <x-slot:actions>
                        <button type="button" wire:click="newItem" class="btn-gold h-10 px-5 text-xs">＋ Sell a sponsorship</button>
                    </x-slot:actions>
                </x-empty>
            @else
                <x-bulk-bar :count="$this->selectedCount()" noun="sponsor" />
                <div class="card overflow-x-auto">
                    <table class="w-full min-w-[560px]">
                        <thead>
                            <tr class="border-b border-line bg-page/40 text-left text-eyebrow font-bold uppercase tracking-wide text-muted">
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
                                <tr wire:key="sp-{{ $s->id }}" class="group/sp border-b border-line last:border-0 hover:bg-page/30 {{ $this->isSelected($s->id) ? 'bg-navy-50/60' : '' }}">
                                    <td class="pl-3.5"><button type="button" wire:click="toggleSelect({{ $s->id }})" class="flex h-4 w-4 items-center justify-center rounded border text-eyebrow {{ $this->isSelected($s->id) ? 'border-navy-900 bg-navy-900 text-white' : 'border-navy-200 text-transparent hover:border-navy-400' }}" title="Select">✓</button></td>
                                    <td class="px-3.5 py-2">
                                        <p class="text-[13px] font-semibold text-navy-900">{{ $s->name }}</p>
                                        @if ($s->notes)<p class="truncate text-eyebrow text-muted">{{ $s->notes }}</p>@endif
                                    </td>
                                    <td class="px-2.5 py-2">@if ($s->package)<span class="rounded-full px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide {{ $tone($s->package) }}">{{ $s->package }}</span>@else<span class="text-muted">—</span>@endif</td>
                                    <td class="px-2.5 py-2 text-right text-xs font-bold tabular-nums text-navy-900">{{ $s->amount_cents ? $fmt($s->amount_cents) : '—' }}</td>
                                    <td class="px-2.5 py-2 text-right text-xs font-semibold tabular-nums text-emerald-700">{{ $s->paid_cents ? $fmt($s->paid_cents) : '—' }}</td>
                                    <td class="px-2.5 py-2 text-center"><span class="rounded-full px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide {{ $stClass }}">{{ $stLabel }}</span></td>
                                    <td class="px-2.5 py-2">
                                        <div class="flex items-center justify-end gap-1 opacity-0 transition group-hover/sp:opacity-100">
                                            <button type="button" wire:click="edit({{ $s->id }})" class="rounded-md bg-navy-50 px-1.5 py-0.5 text-eyebrow font-bold text-navy-600 hover:bg-navy-100">✎</button>
                                            <x-confirm title="Remove {{ $s->name }}?" confirm="Remove" run="$wire.delete({{ $s->id }})"
                                                       class="rounded-md bg-risk/10 px-1.5 py-0.5 text-eyebrow font-bold text-red-700 hover:bg-risk/20">✕</x-confirm>
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
                    <span class="relative text-2xs font-bold uppercase tracking-[0.18em] text-navy-900">Sponsors Control</span>
                    <span class="relative ml-auto text-eyebrow font-semibold text-muted">{{ $packages->count() }} pkgs</span>
                </div>

                {{-- package catalog --}}
                <div class="divide-y divide-line">
                    @foreach ($packages as $p)
                        <div wire:key="pkg-{{ $p->id }}" class="group/pkg px-4 py-2.5">
                            @if ($editingPackageId === $p->id)
                                <div class="space-y-2">
                                    <input type="text" wire:model="packageEditName" maxlength="60" placeholder="Package name" class="input h-8 w-full text-xs font-semibold">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-eyebrow font-semibold text-muted">{{ $event->currencySymbol() }}</span>
                                        <input type="number" min="0" step="100" wire:model="packageEditPrice" placeholder="Price" class="input h-8 flex-1 text-xs">
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-eyebrow font-semibold text-muted">Max</span>
                                        <input type="number" min="1" step="1" wire:model="packageEditSlots" placeholder="Slots (blank = unlimited)" class="input h-8 flex-1 text-xs">
                                    </div>
                                    <input type="text" wire:model="packageEditBlurb" maxlength="160" placeholder="Short tagline (optional)" class="input h-8 w-full text-micro">
                                    <textarea wire:model="packageEditBenefits" rows="4" placeholder="Benefits — one per line&#10;e.g. Logo on main stage&#10;2 exhibition booths&#10;Speaking slot" class="input w-full py-2 text-micro leading-snug"></textarea>
                                    <div class="flex items-center gap-2">
                                        <button type="button" wire:click="savePackage" class="rounded-lg bg-navy-900 px-3 py-1 text-eyebrow font-bold text-white">Save</button>
                                        <button type="button" wire:click="cancelEditPackage" class="text-eyebrow font-semibold text-navy-500 hover:text-navy-900">Cancel</button>
                                    </div>
                                    @error('packageEditBlurb') <p class="text-eyebrow font-semibold text-risk">{{ $message }}</p> @enderror
                                </div>
                            @else
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 shrink-0 rounded-full {{ $tone($p->name) }}"></span>
                                    @php $bn = count($p->benefits ?? []); $sold = $soldByPackage[$p->name] ?? 0; $left = $p->slots !== null ? max(0, $p->slots - $sold) : null; @endphp
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <span class="truncate text-xs font-bold text-navy-900">{{ $p->name }}</span>
                                            @if ($p->slots !== null)
                                                <span class="shrink-0 rounded-full px-1.5 text-eyebrow font-bold uppercase tracking-wide {{ $left === 0 ? 'bg-risk/10 text-red-700' : 'bg-navy-100 text-navy-600' }}">{{ $left === 0 ? 'Sold out' : $left.' left' }}</span>
                                            @endif
                                        </div>
                                        <span class="block truncate text-eyebrow text-muted">@if ($p->slots !== null){{ $sold }}/{{ $p->slots }} sold · @endif{{ $bn ? $bn.' '.str('benefit')->plural($bn) : 'no benefits' }}@if ($p->blurb) · {{ $p->blurb }}@endif</span>
                                    </div>
                                    <span class="shrink-0 text-xs font-bold {{ $p->price_cents ? 'text-navy-900' : 'text-navy-300' }}">{{ $p->price_cents ? $fmt($p->price_cents) : 'set price' }}</span>
                                    <span class="flex shrink-0 items-center gap-1 opacity-0 transition group-hover/pkg:opacity-100">
                                        <a href="{{ route('events.sponsorship', $event) }}?package={{ urlencode($p->name) }}" target="_blank" class="rounded bg-gold-50 px-1.5 py-0.5 text-eyebrow font-bold text-gold-700 hover:bg-gold-100" title="Generate this package sheet">↗</a>
                                        <button type="button" wire:click="startEditPackage({{ $p->id }})" class="rounded bg-navy-50 px-1.5 py-0.5 text-eyebrow font-bold text-navy-600 hover:bg-navy-100" title="Edit name, price & benefits">✎</button>
                                        <x-confirm title="Remove the “{{ $p->name }}” package?" body="Sold sponsors keep their amount." confirm="Remove" run="$wire.deletePackage({{ $p->id }})"
                                                   class="rounded bg-risk/10 px-1.5 py-0.5 text-eyebrow font-bold text-red-700 hover:bg-risk/20" title="Delete package">✕</x-confirm>
                                    </span>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    {{-- add package --}}
                    <div class="bg-page/30 px-4 py-3">
                        <input type="text" wire:model="newPackageName" wire:keydown.enter="addPackage" maxlength="60" class="input mb-2 h-8 w-full text-xs" placeholder="New package name…">
                        <div class="flex items-center gap-1.5">
                            <span class="text-eyebrow font-semibold text-muted">{{ $event->currencySymbol() }}</span>
                            <input type="number" min="0" step="100" wire:model="newPackagePrice" wire:keydown.enter="addPackage" placeholder="Price" class="input h-8 w-20 text-xs">
                            <input type="number" min="1" step="1" wire:model="newPackageSlots" wire:keydown.enter="addPackage" placeholder="Max" class="input h-8 w-14 text-xs" title="Max slots (blank = unlimited)">
                            <button type="button" wire:click="addPackage" class="rounded-lg border border-gold-300 bg-gold-50 px-2.5 py-1 text-eyebrow font-bold text-gold-700 hover:bg-gold-100">Add</button>
                        </div>
                        @error('newPackageName') <p class="mt-1 text-eyebrow font-semibold text-risk">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- summary + action --}}
                <div class="border-t border-line p-4">
                    <div class="mb-3 space-y-1.5 text-xs">
                        <div class="flex justify-between"><span class="text-muted">Target</span><span class="font-bold text-navy-900">{{ $target ? $fmt($target) : '—' }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Committed</span><span class="font-bold text-navy-900">{{ $fmt($committed) }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Received</span><span class="font-bold text-emerald-700">{{ $fmt($received) }}</span></div>
                        <div class="flex justify-between border-t border-line pt-1.5"><span class="text-muted">Outstanding</span><span class="font-bold text-navy-900">{{ $fmt(max(0, $committed - $received)) }}</span></div>
                    </div>
                    <button type="button" wire:click="newItem" class="btn-gold h-10 w-full text-xs">＋ Sell a sponsorship</button>
                    <a href="{{ route('events.sponsorship', $event) }}" target="_blank" class="mt-2 flex h-10 w-full items-center justify-center gap-1.5 rounded-xl border border-navy-900 bg-navy-900 text-xs font-bold text-white transition hover:bg-navy-800">
                        <span class="text-gold-400">✦</span> Generate prospectus
                    </a>
                    <a href="{{ route('events.hub', [$event, 'tab' => 'budget']) }}" class="mt-2 block text-center text-eyebrow font-semibold text-gold-600 hover:text-gold-700">Feeds the budget's income →</a>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ sell / edit modal ══ --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit sponsor' : 'Sell a sponsorship'" max="lg" close="set('showForm', false)">
                <form wire:submit="save" class="grid gap-3.5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="field-label !mb-1 !text-eyebrow">Sponsor name</label>
                        <input type="text" wire:model="name" class="input h-10 text-sm" placeholder="e.g. Royal Jordanian">
                        @error('name')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Package</label>
                        <select wire:model.live="package" class="input h-10 text-sm">
                            <option value="">— None —</option>
                            @foreach ($packages as $p)
                                @php $sold = $soldByPackage[$p->name] ?? 0; $left = $p->slots !== null ? max(0, $p->slots - $sold) : null; $full = $left === 0 && $p->name !== $package; @endphp
                                <option value="{{ $p->name }}" @disabled($full)>{{ $p->name }}@if ($p->price_cents) · {{ $fmt($p->price_cents) }}@endif @if ($p->slots !== null) · {{ $full ? 'SOLD OUT' : $left.' left' }}@endif</option>
                            @endforeach
                        </select>
                        @error('package')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                        <p class="mt-1 text-eyebrow text-muted">Picking a package fills the amount.</p>
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Amount ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="amount" class="input h-10 text-sm" placeholder="0">
                        @error('amount')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Paid to date ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="paid" class="input h-10 text-sm" placeholder="0">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Status</label>
                        <select wire:model="payment_status" class="input h-10 text-sm">
                            @foreach (\App\Models\EventSponsor::PAYMENT_STATUSES as $st)<option value="{{ $st }}">{{ ucfirst($st) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label !mb-1 !text-eyebrow">Notes</label>
                        <input type="text" wire:model="notes" class="input h-10 text-sm" placeholder="Deliverables, branding rights…">
                    </div>
                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="btn-navy h-10 px-6 text-xs">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update' : 'Add sponsor' }}</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
        </x-modal>
    @endif
</div>
