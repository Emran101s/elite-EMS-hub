@php
    $readiness = ['requested' => 10, 'quoted' => 30, 'approved' => 50, 'contracted' => 70, 'in_production' => 80, 'delivered' => 95, 'completed' => 100, 'issue' => 20];
    $avg = $event->suppliers->isEmpty() ? null : (int) round($event->suppliers->avg(fn ($s) => $readiness[$s->pivot->status] ?? 10));
    $catMeta = [
        'catering' => ['Catering', 'bg-emerald-100 text-emerald-700'],
        'av_lighting' => ['AV & Lighting', 'bg-navy-50 text-navy-700'],
        'production' => ['Production', 'bg-sky-100 text-sky-700'],
        'support' => ['Support', 'bg-navy-100 text-navy-700'],
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

@if ($event->suppliers->isNotEmpty())
    <x-stat-strip class="mb-4" :stats="[
        ['Suppliers', $event->suppliers->count(), 'truck', null, null, null],
        ['Avg readiness', $avg !== null ? $avg.'%' : '—', 'chart', $avg, $avg !== null && $avg >= 81 ? 'bg-track' : ($avg !== null && $avg >= 61 ? 'bg-warn' : 'bg-risk'), 'requested → completed'],
        ['Committed', $committedTotal > 0 ? $event->money($committedTotal) : '—', 'currency', null, null, $spend->count() ? $spend->count().' with lines' : 'Nothing costed yet', 'text-navy-900'],
        ['Issues', $issues, 'flag', null, null, null, $issues ? 'text-danger-ink' : 'text-navy-900'],
    ]" />
@endif

<div x-data="{
         mode: (() => {
             const saved = localStorage.getItem('elitehub.suppliers.mode');
             return saved === 'list' || saved === 'cards' ? saved : @js($spacesDefault);
         })(),
         setMode(m) { this.mode = m; localStorage.setItem('elitehub.suppliers.mode', m); }
     }">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p class="text-eyebrow text-muted">Requested → quoted → approved → contracted → in production → delivered → completed</p>
        <div class="ms-auto flex items-center gap-2">
            @if ($event->suppliers->isNotEmpty())
                <span class="inline-flex items-center rounded-xl border border-line bg-white p-0.5">
                    <button type="button" @click="setMode('list')"
                            :class="mode === 'list' ? 'bg-navy-950 text-white' : 'text-navy-500 hover:text-navy-900'"
                            class="rounded-lg px-2.5 py-1.5 text-eyebrow font-bold transition">List</button>
                    <button type="button" @click="setMode('cards')"
                            :class="mode === 'cards' ? 'bg-navy-950 text-white' : 'text-navy-500 hover:text-navy-900'"
                            class="rounded-lg px-2.5 py-1.5 text-eyebrow font-bold transition">Cards</button>
                </span>
            @endif
            <a href="{{ route('suppliers.index') }}" class="rounded-xl border border-line bg-white px-3.5 py-2 text-xs font-semibold text-navy-700 transition hover:border-gold-300">Manage suppliers →</a>
        </div>
    </div>

    @if ($event->suppliers->isEmpty())
        <x-empty icon="truck" title="No suppliers on this event yet"
                 hint="Assign suppliers from your Suppliers module to track quotes, contracts and delivery readiness here.">
            <x-slot:actions>
                <a href="{{ route('suppliers.index') }}" class="btn-gold btn-sm">Open Suppliers module →</a>
            </x-slot:actions>
        </x-empty>
    @else
        {{-- dense list --}}
        <div x-show="mode === 'list'" x-cloak class="card overflow-hidden">
            <div class="hidden grid-cols-12 gap-3 border-b border-line bg-page/40 px-4 py-2 text-eyebrow font-semibold uppercase tracking-wide text-muted md:grid">
                <span class="col-span-4">Supplier</span>
                <span class="col-span-2">Category</span>
                <span class="col-span-2">Status</span>
                <span class="col-span-2">Readiness</span>
                <span class="col-span-2 text-right">Committed</span>
            </div>
            @foreach ($event->suppliers as $supplier)
                @php
                    [$catLabel, $catClass] = $catMeta[$supplier->category] ?? [str($supplier->category)->replace('_', ' ')->title(), 'bg-navy-50 text-navy-600'];
                    $pct = $readiness[$supplier->pivot->status] ?? 10;
                    $money = $spend[$supplier->id] ?? null;
                @endphp
                <div wire:key="sup-list-{{ $supplier->id }}"
                     @class([
                         'grid grid-cols-2 items-center gap-2 border-b border-line/70 px-4 py-2.5 last:border-0 md:grid-cols-12 md:gap-3',
                         'bg-red-50/40' => $supplier->pivot->status === 'issue',
                     ])>
                    <div class="col-span-2 flex min-w-0 items-center gap-2.5 md:col-span-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold text-white" style="background: {{ $moduleHex }}">{{ str($supplier->name)->substr(0, 1) }}</span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-navy-900">{{ $supplier->name }}</p>
                            <p class="truncate text-eyebrow text-muted">{{ collect([$supplier->email, $supplier->phone, $supplier->city])->filter()->implode(' · ') ?: '★ '.number_format($supplier->rating, 1) }}</p>
                        </div>
                    </div>
                    <div class="md:col-span-2"><span class="pill {{ $catClass }}">{{ $catLabel }}</span></div>
                    <div class="md:col-span-2"><x-status-badge :status="$supplier->pivot->status" /></div>
                    <div class="flex items-center gap-2 md:col-span-2">
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-navy-50">
                            <div @class(['h-full rounded-full', 'bg-risk' => $pct <= 30, 'bg-warn' => $pct > 30 && $pct < 81, 'bg-track' => $pct >= 81]) style="width: {{ $pct }}%"></div>
                        </div>
                        <span class="shrink-0 text-eyebrow font-black text-navy-500">{{ $pct }}%</span>
                    </div>
                    <div class="text-right md:col-span-2">
                        @if ($money)
                            <a href="{{ route('events.hub', [$event, 'tab' => 'budget']) }}" wire:navigate
                               class="pf text-sm font-bold tabular-nums text-navy-900 hover:text-gold-700">{{ $event->money($money['committed']) }}</a>
                            @if ($money['paid'] > 0)
                                <p class="text-eyebrow font-semibold text-emerald-700">{{ $event->money($money['paid']) }} paid</p>
                            @endif
                        @else
                            <span class="text-eyebrow italic text-navy-300">—</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- cards --}}
        <div x-show="mode === 'cards'" x-cloak class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($event->suppliers as $supplier)
                @php
                    [$catLabel, $catClass] = $catMeta[$supplier->category] ?? [str($supplier->category)->replace('_', ' ')->title(), 'bg-navy-50 text-navy-600'];
                    $pct = $readiness[$supplier->pivot->status] ?? 10;
                    $money = $spend[$supplier->id] ?? null;
                @endphp
                <div wire:key="sup-card-{{ $supplier->id }}" class="group op-card {{ $supplier->pivot->status === 'issue' ? '!border-red-300 ring-1 ring-red-200' : '' }}">
                    <div class="flex flex-1 flex-col p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-bold text-white" style="background: {{ $moduleHex }}">{{ str($supplier->name)->substr(0, 1) }}</span>
                                <div class="min-w-0">
                                    <p class="pf truncate text-sm font-bold text-navy-900">{{ $supplier->name }}</p>
                                    <div class="mt-1 flex items-center gap-1.5">
                                        <span class="pill {{ $catClass }}">{{ $catLabel }}</span>
                                        <span class="text-eyebrow font-semibold text-gold-600">★ {{ number_format($supplier->rating, 1) }}</span>
                                    </div>
                                </div>
                            </div>
                            <x-status-badge :status="$supplier->pivot->status" />
                        </div>

                        @if ($supplier->email || $supplier->phone || $supplier->city)
                            <p class="mt-2.5 truncate text-micro text-muted">{{ collect([$supplier->email, $supplier->phone, $supplier->city])->filter()->implode(' · ') }}</p>
                        @endif

                        <div class="mt-3 flex items-center gap-2">
                            <span class="shrink-0 text-eyebrow font-bold uppercase tracking-wide text-navy-400">Readiness</span>
                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-navy-50">
                                <div @class(['h-full rounded-full', 'bg-risk' => $pct <= 30, 'bg-warn' => $pct > 30 && $pct < 81, 'bg-track' => $pct >= 81]) style="width: {{ $pct }}%"></div>
                            </div>
                            <span class="shrink-0 text-eyebrow font-black text-navy-500">{{ $pct }}%</span>
                        </div>

                        <div class="mt-2.5 flex items-baseline gap-2 border-t border-line/70 pt-2">
                            @if ($money)
                                <span class="text-eyebrow font-bold uppercase tracking-wide text-navy-400">Committed</span>
                                <a href="{{ route('events.hub', [$event, 'tab' => 'budget']) }}" wire:navigate
                                   class="pf text-[13px] font-black tabular-nums text-navy-900 hover:text-gold-700">{{ $event->money($money['committed']) }}</a>
                                <span class="text-micro text-muted">{{ $money['lines'] }} {{ str('line')->plural($money['lines']) }}</span>
                                @if ($money['paid'] > 0)
                                    <span class="ms-auto text-micro font-semibold text-emerald-700">{{ $event->money($money['paid']) }} paid</span>
                                @endif
                            @else
                                <span class="text-micro italic text-navy-300">Nothing costed against them yet</span>
                            @endif
                        </div>

                        @if ($supplier->pivot->notes)
                            <p class="mt-2 rounded-lg bg-page/60 px-2.5 py-1.5 text-micro text-navy-700">{{ $supplier->pivot->notes }}</p>
                        @endif
                    </div>

                    <div class="op-card-foot">
                        <x-icon name="truck" class="h-3 w-3 shrink-0" style="color: {{ $moduleHex }}" />
                        <span class="truncate text-eyebrow font-semibold text-navy-700">{{ \Illuminate\Support\Str::limit($event->name, 22) }}</span>
                        <span class="ml-auto shrink-0 text-eyebrow font-bold uppercase tracking-wide text-navy-400">{{ $catLabel }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
