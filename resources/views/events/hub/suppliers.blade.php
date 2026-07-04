@php
    $readiness = ['requested' => 10, 'quoted' => 30, 'approved' => 50, 'contracted' => 70, 'in_production' => 80, 'delivered' => 95, 'completed' => 100, 'issue' => 20];
    $avg = $event->suppliers->isEmpty() ? null : (int) round($event->suppliers->avg(fn ($s) => $readiness[$s->pivot->status] ?? 10));
@endphp

<div class="mb-6 card flex items-center justify-between px-6 py-4">
    <div>
        <h3 class="text-xs font-bold uppercase tracking-wide text-navy-900">Supplier Readiness</h3>
        <p class="text-xs text-muted">{{ $event->suppliers->count() }} suppliers on this event · pipeline: requested → quoted → approved → contracted → in production → delivered → completed</p>
    </div>
    @if ($avg !== null)
        <x-health-ring :percent="$avg" :group="$avg >= 81 ? 'track' : ($avg >= 61 ? 'warn' : 'risk')" size="h-14 w-14" />
    @endif
</div>

<div class="grid gap-4 md:grid-cols-2">
    @forelse ($event->suppliers as $supplier)
        <div class="card p-5 {{ $supplier->pivot->status === 'issue' ? 'ring-1 ring-risk/40' : '' }}">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-navy-900 text-sm font-bold text-gold-400">{{ str($supplier->name)->substr(0, 1) }}</span>
                    <div>
                        <p class="text-sm font-bold text-navy-900">{{ $supplier->name }}</p>
                        <p class="text-xs text-muted">{{ str($supplier->category)->replace('_', ' & ')->title() }} · ★ {{ number_format($supplier->rating, 1) }}</p>
                    </div>
                </div>
                <x-status-badge :status="$supplier->pivot->status" />
            </div>
            @php $pct = $readiness[$supplier->pivot->status] ?? 10; @endphp
            <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-navy-50">
                <div @class(['h-full rounded-full', 'bg-risk' => $pct <= 30, 'bg-warn' => $pct > 30 && $pct < 81, 'bg-track' => $pct >= 81]) style="width: {{ $pct }}%"></div>
            </div>
            @if ($supplier->pivot->notes)
                <p class="mt-2 text-xs text-muted">{{ $supplier->pivot->notes }}</p>
            @endif
        </div>
    @empty
        <p class="col-span-full py-12 text-center text-sm text-muted">No suppliers assigned — assign from the <a class="font-semibold text-gold-600" href="{{ route('suppliers.index') }}">Suppliers module</a>.</p>
    @endforelse
</div>
