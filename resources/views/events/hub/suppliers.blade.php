@php
    $readiness = ['requested' => 10, 'quoted' => 30, 'approved' => 50, 'contracted' => 70, 'in_production' => 80, 'delivered' => 95, 'completed' => 100, 'issue' => 20];
    $avg = $event->suppliers->isEmpty() ? null : (int) round($event->suppliers->avg(fn ($s) => $readiness[$s->pivot->status] ?? 10));
    $catMeta = [
        'catering' => ['Catering', 'bg-emerald-100 text-emerald-700'],
        'av_lighting' => ['AV & Lighting', 'bg-eo-bg text-eo-text'],
        'production' => ['Production', 'bg-sky-100 text-sky-700'],
        'support' => ['Support', 'bg-eo-bg text-eo-text'],
        'logistics' => ['Logistics', 'bg-amber-100 text-amber-700'],
        'decor' => ['Décor', 'bg-amber-100 text-amber-700'],
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
@if ($event->suppliers->where('rating', '>', 0)->isNotEmpty())
    <div class="eo-soft-card mb-4 divide-y divide-eo-line overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2.5">
            <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-eo-muted">Top rated on this event</p>
        </div>
        @foreach ($event->suppliers->sortByDesc('rating')->take(4) as $supplier)
            <div class="flex items-center gap-3 px-4 py-2.5">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-eo-workspace text-eyebrow font-bold text-eo-text">{{ str($supplier->name)->substr(0, 1) }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-xs font-semibold text-eo-text">{{ $supplier->name }}</span>
                    <span class="block text-eyebrow text-eo-muted">{{ str($supplier->category)->replace('_', ' & ')->title() }}</span>
                </span>
                <span class="shrink-0 text-xs font-bold text-eo-teal-ink">★ {{ number_format($supplier->rating, 1) }}</span>
            </div>
        @endforeach
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
        <p class="text-eyebrow text-eo-muted">Requested → quoted → approved → contracted → in production → delivered → completed</p>
        <div class="ms-auto flex items-center gap-2">
            @if ($event->suppliers->isNotEmpty())
                <span class="inline-flex items-center rounded-xl border border-eo-line bg-white p-0.5">
                    <button type="button" @click="setMode('list')"
                            :class="mode === 'list' ? 'bg-eo-navy-deep text-white' : 'text-eo-muted hover:text-eo-text'"
                            class="rounded-lg px-2.5 py-1.5 text-eyebrow font-bold transition">List</button>
                    <button type="button" @click="setMode('cards')"
                            :class="mode === 'cards' ? 'bg-eo-navy-deep text-white' : 'text-eo-muted hover:text-eo-text'"
                            class="rounded-lg px-2.5 py-1.5 text-eyebrow font-bold transition">Cards</button>
                </span>
            @endif
            <a href="{{ route('suppliers.index') }}" class="rounded-xl border border-eo-line bg-white px-3.5 py-2 text-xs font-semibold text-eo-text transition hover:border-eo-teal">Manage suppliers →</a>
        </div>
    </div>

    @if ($event->suppliers->isEmpty())
        <x-empty icon="truck" title="No suppliers on this event yet"
                 hint="Assign suppliers from your Suppliers module to track quotes, contracts and delivery readiness here.">
            <x-slot:actions>
                <a href="{{ route('suppliers.index') }}" class="eo-btn-primary btn-sm">Open Suppliers module →</a>
            </x-slot:actions>
        </x-empty>
    @else
        {{-- dense list + selected-supplier context panel. Cards mode below
             already shows every supplier's full detail inline, so the panel
             is scoped to List — the dense, scan-first view where a detail
             companion actually earns its place. --}}
        <div x-show="mode === 'list'" x-cloak class="hubx-ws">
            <div class="hubx-ws-main">
                <div class="hidden grid-cols-12 gap-3 border-b border-eo-line bg-eo-workspace/40 px-4 py-2 text-eyebrow font-semibold uppercase tracking-wide text-eo-muted md:grid">
                    <span class="col-span-5">Supplier</span>
                    <span class="col-span-2">Category</span>
                    <span class="col-span-2">Status</span>
                    <span class="col-span-3">Readiness</span>
                </div>
                @foreach ($event->suppliers as $supplier)
                    @php
                        [$catLabel, $catClass] = $catMeta[$supplier->category] ?? [str($supplier->category)->replace('_', ' ')->title(), 'bg-eo-bg text-eo-muted'];
                        $pct = $readiness[$supplier->pivot->status] ?? 10;
                    @endphp
                    <button type="button" wire:key="sup-list-{{ $supplier->id }}"
                            @click="selected = selected === {{ $supplier->id }} ? null : {{ $supplier->id }}"
                            :class="selected === {{ $supplier->id }} ? 'is-selected' : ''"
                            class="hubx-ws-row grid-cols-2 items-center gap-2 px-4 py-2.5 md:grid-cols-12 md:gap-3">
                        <div class="col-span-2 flex min-w-0 items-center gap-2.5 md:col-span-5">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold text-white" style="background: {{ $moduleHex }}">{{ str($supplier->name)->substr(0, 1) }}</span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-eo-text">{{ $supplier->name }}</p>
                                <p class="truncate text-eyebrow text-eo-muted">{{ collect([$supplier->email, $supplier->phone, $supplier->city])->filter()->implode(' · ') ?: '★ '.number_format($supplier->rating, 1) }}</p>
                            </div>
                        </div>
                        <div class="md:col-span-2"><span class="pill {{ $catClass }}">{{ $catLabel }}</span></div>
                        <div class="md:col-span-2"><x-status-badge :status="$supplier->pivot->status" /></div>
                        <div class="flex items-center gap-2 md:col-span-3">
                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-eo-bg">
                                <div @class(['h-full rounded-full', 'bg-eo-risk' => $pct <= 30, 'bg-warn' => $pct > 30 && $pct < 81, 'bg-track' => $pct >= 81]) style="width: {{ $pct }}%"></div>
                            </div>
                            <span class="shrink-0 text-eyebrow font-black text-eo-muted">{{ $pct }}%</span>
                        </div>
                    </button>
                @endforeach
            </div>

            <div class="hubx-ws-side">
                @foreach ($event->suppliers as $supplier)
                    @php
                        [$catLabel] = $catMeta[$supplier->category] ?? [str($supplier->category)->replace('_', ' ')->title()];
                        $money = $spend[$supplier->id] ?? null;
                    @endphp
                    <div x-show="selected === {{ $supplier->id }}" x-cloak>
                        <div class="hubx-ws-side-head">
                            <div class="min-w-0">
                                <p class="hubx-ws-side-label">Selected Supplier</p>
                                <p class="hubx-ws-side-title">{{ $supplier->name }}</p>
                            </div>
                            <button type="button" @click="selected = null" class="hubx-ws-side-close" title="Close">
                                <span aria-hidden="true">✕</span>
                            </button>
                        </div>

                        @if ($supplier->pivot->notes)
                            <p class="hubx-ws-side-note">{{ $supplier->pivot->notes }}</p>
                        @endif

                        <div class="hubx-ws-side-meta">
                            <div class="hubx-ws-side-stat">
                                <span class="hubx-ws-side-stat-label">Category</span>
                                <span class="hubx-ws-side-stat-value">{{ $catLabel }}</span>
                            </div>
                            <div class="hubx-ws-side-stat">
                                <span class="hubx-ws-side-stat-label">Rating</span>
                                <span class="hubx-ws-side-stat-value">★ {{ number_format($supplier->rating, 1) }}</span>
                            </div>
                            @if ($supplier->email || $supplier->phone)
                                <div class="hubx-ws-side-stat">
                                    <span class="hubx-ws-side-stat-label">Contact</span>
                                    <span class="hubx-ws-side-stat-value">{{ $supplier->email ?? $supplier->phone }}</span>
                                </div>
                            @endif
                            <div class="hubx-ws-side-stat">
                                <span class="hubx-ws-side-stat-label">Committed</span>
                                <span class="hubx-ws-side-stat-value">{{ $money ? $event->money($money['committed']) : '—' }}</span>
                            </div>
                            @if ($money && $money['paid'] > 0)
                                <div class="hubx-ws-side-stat">
                                    <span class="hubx-ws-side-stat-label">Paid</span>
                                    <span class="hubx-ws-side-stat-value">{{ $event->money($money['paid']) }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="hubx-ws-side-actions">
                            @if ($money)
                                <a href="{{ route('events.hub', [$event, 'tab' => 'budget']) }}" wire:navigate class="hubx-ws-side-action is-teal">Open Budget</a>
                            @endif
                            <a href="{{ route('suppliers.index') }}" class="hubx-ws-side-action">Manage →</a>
                        </div>
                    </div>
                @endforeach

                <div x-show="selected === null">
                    <div class="hubx-ws-side-empty">
                        <span class="hubx-ws-side-empty-icon"><x-icon name="truck" class="h-4 w-4" /></span>
                        <p class="text-[12px] font-semibold text-white/80">No supplier selected</p>
                        <p class="text-[11px]">Select a supplier from the list to see contact, cost, and readiness detail.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- cards --}}
        <div x-show="mode === 'cards'" x-cloak class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($event->suppliers as $supplier)
                @php
                    [$catLabel, $catClass] = $catMeta[$supplier->category] ?? [str($supplier->category)->replace('_', ' ')->title(), 'bg-eo-bg text-eo-muted'];
                    $pct = $readiness[$supplier->pivot->status] ?? 10;
                    $money = $spend[$supplier->id] ?? null;
                @endphp
                <div wire:key="sup-card-{{ $supplier->id }}" class="group op-card {{ $supplier->pivot->status === 'issue' ? '!border-red-300 ring-1 ring-red-200' : '' }}">
                    <div class="flex flex-1 flex-col p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-bold text-white" style="background: {{ $moduleHex }}">{{ str($supplier->name)->substr(0, 1) }}</span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-eo-text">{{ $supplier->name }}</p>
                                    <div class="mt-1 flex items-center gap-1.5">
                                        <span class="pill {{ $catClass }}">{{ $catLabel }}</span>
                                        <span class="text-eyebrow font-semibold text-eo-teal-ink">★ {{ number_format($supplier->rating, 1) }}</span>
                                    </div>
                                </div>
                            </div>
                            <x-status-badge :status="$supplier->pivot->status" />
                        </div>

                        @if ($supplier->email || $supplier->phone || $supplier->city)
                            <p class="mt-2.5 truncate text-micro text-eo-muted">{{ collect([$supplier->email, $supplier->phone, $supplier->city])->filter()->implode(' · ') }}</p>
                        @endif

                        <div class="mt-3 flex items-center gap-2">
                            <span class="shrink-0 text-eyebrow font-bold uppercase tracking-wide text-eo-muted">Readiness</span>
                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-eo-bg">
                                <div @class(['h-full rounded-full', 'bg-eo-risk' => $pct <= 30, 'bg-warn' => $pct > 30 && $pct < 81, 'bg-track' => $pct >= 81]) style="width: {{ $pct }}%"></div>
                            </div>
                            <span class="shrink-0 text-eyebrow font-black text-eo-muted">{{ $pct }}%</span>
                        </div>

                        <div class="mt-2.5 flex items-baseline gap-2 border-t border-eo-line/70 pt-2">
                            @if ($money)
                                <span class="text-eyebrow font-bold uppercase tracking-wide text-eo-muted">Committed</span>
                                <a href="{{ route('events.hub', [$event, 'tab' => 'budget']) }}" wire:navigate
                                   class="text-[13px] font-black tabular-nums text-eo-text hover:text-eo-teal-ink">{{ $event->money($money['committed']) }}</a>
                                <span class="text-micro text-eo-muted">{{ $money['lines'] }} {{ str('line')->plural($money['lines']) }}</span>
                                @if ($money['paid'] > 0)
                                    <span class="ms-auto text-micro font-semibold text-emerald-700">{{ $event->money($money['paid']) }} paid</span>
                                @endif
                            @else
                                <span class="text-micro italic text-eo-muted">Nothing costed against them yet</span>
                            @endif
                        </div>

                        @if ($supplier->pivot->notes)
                            <p class="mt-2 rounded-lg bg-eo-workspace/60 px-2.5 py-1.5 text-micro text-eo-text">{{ $supplier->pivot->notes }}</p>
                        @endif
                    </div>

                    <div class="op-card-foot">
                        <x-icon name="truck" class="h-3 w-3 shrink-0" style="color: {{ $moduleHex }}" />
                        <span class="truncate text-eyebrow font-semibold text-eo-text">{{ \Illuminate\Support\Str::limit($event->name, 22) }}</span>
                        <span class="ml-auto shrink-0 text-eyebrow font-bold uppercase tracking-wide text-eo-muted">{{ $catLabel }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
