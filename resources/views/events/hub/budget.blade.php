@php
    $estimated = $event->budgetItems->sum('estimated_cents');
    $actual = $event->budgetItems->sum('actual_cents');
    $paid = $event->budgetItems->where('payment_status', 'paid')->sum('actual_cents');
    $fmt = fn (int $cents) => '$'.number_format($cents / 100);
@endphp

<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
    @foreach ([
        ['label' => 'Event Budget', 'value' => $fmt($event->budget_cents)],
        ['label' => 'Estimated Costs', 'value' => $fmt($estimated)],
        ['label' => 'Actual Costs', 'value' => $fmt($actual), 'risk' => $actual > $estimated],
        ['label' => 'Paid', 'value' => $fmt($paid)],
        ['label' => 'Outstanding', 'value' => $fmt(max($actual - $paid, 0))],
    ] as $stat)
        <div class="card p-4">
            <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-muted">{{ $stat['label'] }}</p>
            <p class="mt-1.5 text-xl font-bold {{ ($stat['risk'] ?? false) ? 'text-risk' : 'text-navy-900' }}">{{ $stat['value'] }}</p>
        </div>
    @endforeach
</div>

<div class="card divide-y divide-line">
    <div class="hidden grid-cols-12 gap-3 px-6 py-3 text-[0.65rem] font-semibold uppercase tracking-wide text-muted md:grid">
        <span class="col-span-4">Line Item</span>
        <span class="col-span-2 text-right">Estimated</span>
        <span class="col-span-2 text-right">Actual</span>
        <span class="col-span-2">Supplier</span>
        <span class="col-span-2 text-right">Payment</span>
    </div>
    @forelse ($event->budgetItems as $item)
        <div class="grid grid-cols-2 items-center gap-3 px-6 py-4 md:grid-cols-12">
            <div class="col-span-2 md:col-span-4">
                <p class="text-sm font-semibold text-navy-900">{{ $item->description ?? str($item->category)->title() }}</p>
                <p class="text-[0.65rem] uppercase tracking-wide text-muted">{{ str($item->category)->replace('_', ' & ')->title() }}
                    @if ($item->invoice_number) · {{ $item->invoice_number }} @endif
                </p>
            </div>
            <p class="text-xs font-semibold text-navy-900 md:col-span-2 md:text-right">{{ $item->estimated_cents ? '$'.number_format($item->estimated_cents / 100) : '—' }}</p>
            <p class="text-xs font-semibold md:col-span-2 md:text-right {{ $item->actual_cents > $item->estimated_cents ? 'text-risk' : 'text-navy-900' }}">
                {{ $item->actual_cents ? '$'.number_format($item->actual_cents / 100) : '—' }}
            </p>
            <p class="truncate text-xs text-muted md:col-span-2">{{ $item->supplier?->name ?? '—' }}</p>
            <div class="md:col-span-2 md:text-right"><x-status-badge :status="$item->payment_status" /></div>
        </div>
    @empty
        <p class="px-6 py-12 text-center text-sm text-muted">No budget lines yet — add estimates to activate Budget Health.</p>
    @endforelse
</div>
<p class="mt-4 text-xs text-muted">Coming next: add/edit lines, client invoices, revenue &amp; profit margin, budget-vs-actual chart, sync with the Finance module.</p>
