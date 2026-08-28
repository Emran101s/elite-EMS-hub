@php
    $readiness = ['requested' => 10, 'quoted' => 30, 'approved' => 50, 'contracted' => 70, 'in_production' => 80, 'delivered' => 95, 'completed' => 100, 'issue' => 20];
    $avg = $event->suppliers->isEmpty() ? null : (int) round($event->suppliers->avg(fn ($s) => $readiness[$s->pivot->status] ?? 10));
    $catMeta = [
        'catering' => ['Catering', 'bg-success-soft text-success-ink'],
        'av_lighting' => ['AV & Lighting', 'bg-page text-ink'],
        'production' => ['Production', 'bg-info-soft text-info-ink'],
        'support' => ['Support', 'bg-page text-ink'],
        'logistics' => ['Logistics', 'bg-warning-soft text-warning-ink'],
        'decor' => ['Décor', 'bg-warning-soft text-warning-ink'],
    ];

    $spend = $event->budgetItems->whereNotNull('supplier_id')
        ->groupBy('supplier_id')
        ->map(fn ($lines) => [
            'committed' => (int) $lines->sum(fn ($i) => $i->costCents()),
            'paid' => (int) $lines->sum('paid_cents'),
            'lines' => $lines->count(),
        ]);

    $committedTotal = (int) $spend->sum('committed');
    $issues = $event->suppliers->filter(fn ($s) => $s->pivot->status === 'issue')->count();
    $moduleHex = \App\Models\Event::moduleColor('suppliers');
    $spacesDefault = $event->suppliers->isNotEmpty() && $event->suppliers->count() <= 6 ? 'cards' : 'list';
@endphp

{{-- Suppliers / Avg readiness / Committed / Issues already live in the
     Universal Module Header above this — this stat strip repeated the
     exact same four figures, so it's gone rather than kept as a second
     copy of the same row. --}}

{{-- Top rated — relocated from the old Event Hub Overview dashboard, which
     showed the same top-4-by-rating list on every visit whether or not
     anyone was looking at Suppliers. Real content ($supplier->rating,
     the same field), just living on the page it's actually about now. --}}
<div class="cx-canvas">
@if ($event->suppliers->where('rating', '>', 0)->isNotEmpty())
    <div class="cx-lcard">
        <div class="cx-lcard-head"><span class="cx-lt">Top rated on this event</span></div>
        <div class="divide-y divide-line">
            @foreach ($event->suppliers->sortByDesc('rating')->take(4) as $supplier)
                <div class="flex items-center gap-3 px-4 py-2.5">
                    <span class="cx-cathex" style="background: {{ $moduleHex }}">{{ str($supplier->name)->substr(0, 1) }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-xs font-semibold text-ink">{{ $supplier->name }}</span>
                        <span class="block text-eyebrow text-muted">{{ str($supplier->category)->replace('_', ' & ')->title() }}</span>
                    </span>
                    <span class="shrink-0 text-xs font-bold text-gold-700">★ {{ number_format($supplier->rating, 1) }}</span>
                </div>
            @endforeach
        </div>
    </div>
@endif

<div x-data="{
         mode: (() => {
             const saved = localStorage.getItem('elitehub.suppliers.mode');
             return saved === 'list' || saved === 'cards' ? saved : @js($spacesDefault);
         })(),
         setMode(m) { this.mode = m; localStorage.setItem('elitehub.suppliers.mode', m); },
         selected: null,
     }">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p class="text-eyebrow text-muted">Requested → quoted → approved → contracted → in production → delivered → completed</p>
        <div class="ms-auto flex items-center gap-2">
            @if ($event->suppliers->isNotEmpty())
                <span class="cx-seg">
                    <button type="button" @click="setMode('list')" :aria-pressed="mode === 'list'">List</button>
                    <button type="button" @click="setMode('cards')" :aria-pressed="mode === 'cards'">Cards</button>
                </span>
            @endif
            <a href="{{ route('suppliers.index') }}" class="rounded-full border border-line bg-white px-3.5 py-2 text-xs font-semibold text-ink transition hover:border-navy-300">Manage suppliers →</a>
        </div>
    </div>

    @if ($event->suppliers->isEmpty())
        <div class="cx-empty">
            <h3>No suppliers on this event yet</h3>
            <p>Assign suppliers from your Suppliers module to track quotes, contracts and delivery readiness here.</p>
            <a href="{{ route('suppliers.index') }}" class="cx-btn cx-btn-accent" style="display:inline-flex">Open Suppliers module →</a>
        </div>
    @else
        {{-- dense list + selected-supplier context panel. Cards mode below
             already shows every supplier's full detail inline, so the panel
             is scoped to List — the dense, scan-first view where a detail
             companion actually earns its place. --}}
        <div x-show="mode === 'list'" x-cloak>
            <x-hub-content.split>
                <x-hub-content.split-list>
                    <x-slot:columns>
                        <span class="col-span-5">Supplier</span>
                        <span class="col-span-2">Category</span>
                        <span class="col-span-2">Status</span>
                        <span class="col-span-3">Readiness</span>
                    </x-slot:columns>

                    @foreach ($event->suppliers as $supplier)
                        @php
                            [$catLabel, $catClass] = $catMeta[$supplier->category] ?? [str($supplier->category)->replace('_', ' ')->title(), 'bg-page text-muted'];
                            $pct = $readiness[$supplier->pivot->status] ?? 10;
                        @endphp
                        <button type="button" wire:key="sup-list-{{ $supplier->id }}"
                                @click="selected = selected === {{ $supplier->id }} ? null : {{ $supplier->id }}"
                                class="w-full text-start">
                            <x-hub-content.split-row
                                x-bind:class="selected === {{ $supplier->id }} ? 'is-selected' : ''"
                                class="grid grid-cols-2 items-center gap-2 md:grid-cols-12 md:gap-3"
                            >
                                <div class="col-span-2 flex min-w-0 items-center gap-2.5 md:col-span-5">
                                    <span class="cx-cathex" style="background: {{ $moduleHex }}">{{ str($supplier->name)->substr(0, 1) }}</span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-ink">{{ $supplier->name }}</p>
                                        <p class="truncate text-eyebrow text-muted">{{ collect([$supplier->email, $supplier->phone, $supplier->city])->filter()->implode(' · ') ?: '★ '.number_format($supplier->rating, 1) }}</p>
                                    </div>
                                </div>
                                <div class="md:col-span-2"><span class="inline-flex rounded-full px-2 py-0.5 text-eyebrow font-bold {{ $catClass }}">{{ $catLabel }}</span></div>
                                <div class="md:col-span-2"><x-status-badge :status="$supplier->pivot->status" /></div>
                                <div class="flex items-center gap-2 md:col-span-3">
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-page">
                                        <div @class(['h-full rounded-full', 'bg-danger' => $pct <= 30, 'bg-warning' => $pct > 30 && $pct < 81, 'bg-success' => $pct >= 81]) style="width: {{ $pct }}%"></div>
                                    </div>
                                    <span class="shrink-0 text-eyebrow font-black text-muted">{{ $pct }}%</span>
                                </div>
                            </x-hub-content.split-row>
                        </button>
                    @endforeach
                </x-hub-content.split-list>

                <div class="ehc-detail">
                    @foreach ($event->suppliers as $supplier)
                        @php
                            [$catLabel] = $catMeta[$supplier->category] ?? [str($supplier->category)->replace('_', ' ')->title()];
                            $money = $spend[$supplier->id] ?? null;
                        @endphp
                        <div x-show="selected === {{ $supplier->id }}" x-cloak>
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-[9.5px] font-extrabold uppercase tracking-[0.08em] text-white/45">Selected Supplier</p>
                                    <p class="mt-1 text-[15px] font-extrabold leading-tight text-white">{{ $supplier->name }}</p>
                                </div>
                                <button type="button" @click="selected = null" title="Close"
                                        class="grid h-[22px] w-[22px] shrink-0 place-items-center rounded-full text-white/50 transition hover:bg-white/10 hover:text-white">
                                    <span aria-hidden="true">✕</span>
                                </button>
                            </div>

                            @if ($supplier->pivot->notes)
                                <p class="mt-1.5 text-[11.5px] leading-relaxed text-white/60">{{ $supplier->pivot->notes }}</p>
                            @endif

                            <div class="mt-3.5 space-y-2">
                                <div class="ehc-detail-stat"><span class="flex items-center gap-1.5 text-white/50"><x-icon name="archive" class="h-3 w-3" />Category</span><span class="font-bold text-white">{{ $catLabel }}</span></div>
                                <div class="ehc-detail-stat"><span class="flex items-center gap-1.5 text-white/50"><x-icon name="star" class="h-3 w-3" />Rating</span><span class="font-bold text-white">★ {{ number_format($supplier->rating, 1) }}</span></div>
                                @if ($supplier->email || $supplier->phone)
                                    <div class="ehc-detail-stat"><span class="flex items-center gap-1.5 text-white/50"><x-icon name="chat" class="h-3 w-3" />Contact</span><span class="font-bold text-white">{{ $supplier->email ?? $supplier->phone }}</span></div>
                                @endif
                                <div class="ehc-detail-stat"><span class="flex items-center gap-1.5 text-white/50"><x-icon name="currency" class="h-3 w-3" />Committed</span><span class="font-bold text-white">{{ $money ? $event->money($money['committed']) : '—' }}</span></div>
                                @if ($money && $money['paid'] > 0)
                                    <div class="ehc-detail-stat"><span class="flex items-center gap-1.5 text-white/50"><x-icon name="check" class="h-3 w-3" />Paid</span><span class="font-bold text-white">{{ $event->money($money['paid']) }}</span></div>
                                @endif
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-1.5">
                                @if ($money)
                                    <a href="{{ route('events.hub', [$event, 'tab' => 'budget']) }}" wire:navigate class="ehc-detail-action is-gold">Open Budget</a>
                                @endif
                                <a href="{{ route('suppliers.index') }}" class="ehc-detail-action {{ $money ? '' : 'col-span-2' }}">Manage →</a>
                            </div>
                        </div>
                    @endforeach

                    <div x-show="selected === null">
                        <div class="flex flex-col items-center gap-1.5 py-7 text-center">
                            <span class="grid h-9 w-9 place-items-center rounded-full bg-white/10 text-white/60">
                                <x-icon name="truck" class="h-4 w-4" />
                            </span>
                            <p class="text-[12px] font-semibold text-white/80">No supplier selected</p>
                            <p class="text-[11px] text-white/50">Select a supplier from the list to see contact, cost, and readiness detail.</p>
                        </div>
                    </div>
                </div>
            </x-hub-content.split>
        </div>

        {{-- cards --}}
        <div x-show="mode === 'cards'" x-cloak class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($event->suppliers as $supplier)
                @php
                    [$catLabel, $catClass] = $catMeta[$supplier->category] ?? [str($supplier->category)->replace('_', ' ')->title(), 'bg-page text-muted'];
                    $pct = $readiness[$supplier->pivot->status] ?? 10;
                    $money = $spend[$supplier->id] ?? null;
                @endphp
                <div wire:key="sup-card-{{ $supplier->id }}" class="group cx-lcard !mb-0 {{ $supplier->pivot->status === 'issue' ? '!border-danger ring-1 ring-danger-soft' : '' }}">
                    <div class="flex flex-1 flex-col p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="cx-cathex" style="width:38px;height:42px;background: {{ $moduleHex }}">{{ str($supplier->name)->substr(0, 1) }}</span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-ink">{{ $supplier->name }}</p>
                                    <div class="mt-1 flex items-center gap-1.5">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-eyebrow font-bold {{ $catClass }}">{{ $catLabel }}</span>
                                        <span class="text-eyebrow font-semibold text-gold-700">★ {{ number_format($supplier->rating, 1) }}</span>
                                    </div>
                                </div>
                            </div>
                            <x-status-badge :status="$supplier->pivot->status" />
                        </div>

                        @if ($supplier->email || $supplier->phone || $supplier->city)
                            <p class="mt-2.5 truncate text-[11px] text-muted">{{ collect([$supplier->email, $supplier->phone, $supplier->city])->filter()->implode(' · ') }}</p>
                        @endif

                        <div class="mt-3 flex items-center gap-2">
                            <span class="shrink-0 text-eyebrow font-bold uppercase tracking-wide text-muted">Readiness</span>
                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-page">
                                <div @class(['h-full rounded-full', 'bg-danger' => $pct <= 30, 'bg-warning' => $pct > 30 && $pct < 81, 'bg-success' => $pct >= 81]) style="width: {{ $pct }}%"></div>
                            </div>
                            <span class="shrink-0 text-eyebrow font-black text-muted">{{ $pct }}%</span>
                        </div>

                        <div class="mt-2.5 flex items-baseline gap-2 border-t border-line/70 pt-2">
                            @if ($money)
                                <span class="text-eyebrow font-bold uppercase tracking-wide text-muted">Committed</span>
                                <a href="{{ route('events.hub', [$event, 'tab' => 'budget']) }}" wire:navigate
                                   class="text-[13px] font-black tabular-nums text-ink hover:text-gold-700">{{ $event->money($money['committed']) }}</a>
                                <span class="text-[10.5px] text-muted">{{ $money['lines'] }} {{ str('line')->plural($money['lines']) }}</span>
                                @if ($money['paid'] > 0)
                                    <span class="ms-auto text-[10.5px] font-semibold text-success-ink">{{ $event->money($money['paid']) }} paid</span>
                                @endif
                            @else
                                <span class="text-[10.5px] italic text-muted">Nothing costed against them yet</span>
                            @endif
                        </div>

                        @if ($supplier->pivot->notes)
                            <p class="mt-2 rounded-lg bg-page px-2.5 py-1.5 text-[10.5px] text-ink">{{ $supplier->pivot->notes }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
</div>
