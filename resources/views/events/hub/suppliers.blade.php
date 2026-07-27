@php
    $readiness = ['requested' => 10, 'quoted' => 30, 'approved' => 50, 'contracted' => 70, 'in_production' => 80, 'delivered' => 95, 'completed' => 100, 'issue' => 20];
    $avg = $event->suppliers->isEmpty() ? null : (int) round($event->suppliers->avg(fn ($s) => $readiness[$s->pivot->status] ?? 10));
    $catMeta = [
        'catering' => ['Catering', 'bg-teal-100 text-teal-700'],
        'av_lighting' => ['AV & Lighting', 'bg-violet-100 text-violet-700'],
        'production' => ['Production', 'bg-sky-100 text-sky-700'],
        'support' => ['Support', 'bg-navy-100 text-navy-700'],
        'logistics' => ['Logistics', 'bg-amber-100 text-amber-700'],
        'decor' => ['Décor', 'bg-rose-100 text-rose-700'],
    ];
@endphp

<div class="mb-5 card flex flex-wrap items-center justify-between gap-4 px-6 py-4">
    <div class="flex items-center gap-4">
        @if ($avg !== null)
            <x-health-ring :percent="$avg" :group="$avg >= 81 ? 'track' : ($avg >= 61 ? 'warn' : 'risk')" size="h-12 w-12" />
        @endif
        <div>
            <h3 class="pf text-base font-bold text-navy-900">Supplier Readiness</h3>
            <p class="text-[0.65rem] text-muted">{{ $event->suppliers->count() }} {{ str('supplier')->plural($event->suppliers->count()) }} on this event · requested → quoted → approved → contracted → in production → delivered → completed</p>
        </div>
    </div>
    <a href="{{ route('suppliers.index') }}" class="rounded-xl border border-line bg-white px-3.5 py-2 text-xs font-semibold text-navy-700 transition hover:border-gold-300">Manage in Suppliers module →</a>
</div>

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @forelse ($event->suppliers as $supplier)
        @php
            [$catLabel, $catClass] = $catMeta[$supplier->category] ?? [str($supplier->category)->replace('_', ' ')->title(), 'bg-navy-50 text-navy-600'];
            $pct = $readiness[$supplier->pivot->status] ?? 10;
        @endphp
        <div class="group op-card {{ $supplier->pivot->status === 'issue' ? '!border-red-300 ring-1 ring-red-200' : '' }}">
            <div class="flex flex-1 flex-col p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-sm font-bold text-gold-400" style="background: linear-gradient(135deg, #1E3352, #14315a);">{{ str($supplier->name)->substr(0, 1) }}</span>
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
                    <p class="mt-3 truncate text-micro text-muted">{{ collect([$supplier->email, $supplier->phone, $supplier->city])->filter()->implode(' · ') }}</p>
                @endif

                <div class="mt-3 flex items-center gap-2">
                    <span class="shrink-0 text-eyebrow font-bold uppercase tracking-wide text-navy-400">Readiness</span>
                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-navy-50">
                        <div @class(['h-full rounded-full', 'bg-risk' => $pct <= 30, 'bg-warn' => $pct > 30 && $pct < 81, 'bg-track' => $pct >= 81]) style="width: {{ $pct }}%"></div>
                    </div>
                    <span class="shrink-0 text-eyebrow font-black text-navy-500">{{ $pct }}%</span>
                </div>
                @if ($supplier->pivot->notes)
                    <p class="mt-2 rounded-lg bg-page/60 px-2.5 py-1.5 text-micro text-navy-700">{{ $supplier->pivot->notes }}</p>
                @endif
            </div>

            {{-- dark navy footer --}}
            <div class="op-card-foot">
                <x-icon name="truck" class="h-3 w-3 shrink-0 text-gold-600" />
                <span class="truncate text-eyebrow font-semibold text-navy-700">{{ \Illuminate\Support\Str::limit($event->name, 22) }}</span>
                <span class="ml-auto shrink-0 text-eyebrow font-bold uppercase tracking-wide text-navy-400">{{ $catLabel }}</span>
            </div>
        </div>
    @empty
        <div class="col-span-full card px-6 py-16 text-center">
            <p class="text-sm font-semibold text-navy-900">No suppliers on this event yet</p>
            <p class="mx-auto mt-1 max-w-md text-xs text-muted">Assign suppliers from your <a class="font-semibold text-gold-600" href="{{ route('suppliers.index') }}">Suppliers module</a> to track quotes, contracts and delivery readiness here.</p>
        </div>
    @endforelse
</div>
