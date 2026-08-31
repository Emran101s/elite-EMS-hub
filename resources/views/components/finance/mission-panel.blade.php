@props(['selected' => null, 'event' => null, 'currency'])

@php
    $money = fn (int $cents) => \App\Support\Money::abbreviated($cents, $currency);
@endphp

@if ($event)
    <div class="rounded-lg border border-line bg-white p-4">
        <p class="truncate text-[14px] font-bold text-ink">{{ $event->name }}</p>
        <p class="mt-0.5 text-[11.5px] text-muted">{{ $event->client?->name ?? 'No client' }}</p>

        <div class="mt-3.5 rounded-md bg-page px-3 py-2.5">
            @php $pn = $selected['pricedNet'] ?? ($selected['charged'] - $selected['cost']); @endphp
            <p class="text-eyebrow font-bold uppercase tracking-[0.1em] text-muted">Mission net · priced</p>
            <p class="mt-1 text-[18px] font-extrabold tabular-nums" style="color: {{ $pn < 0 ? 'var(--color-warning-ink)' : 'var(--color-success-ink)' }}">{{ $money($pn) }}</p>
            <p class="mt-0.5 text-[11px] text-muted">Charged {{ $money($selected['charged']) }} · Cost {{ $money($selected['cost']) }} · {{ $money($selected['net']) }} realized</p>
        </div>

        <div class="mt-4 space-y-2 text-[13px]">
            @foreach ([
                ['Invoiced', $money($selected['billed'] ?? 0)],
                ['Receivable', $money($selected['receivable'])],
                ['Not invoiced', $money($selected['notInvoiced'] ?? 0)],
                ['Margin', ($selected['pricedMargin'] ?? $selected['margin']) === null ? '—' : ($selected['pricedMargin'] ?? $selected['margin']).'%'],
            ] as [$k, $v])
                <div class="flex justify-between gap-3 border-b border-line/70 pb-2 last:border-0">
                    <span class="text-muted">{{ $k }}</span>
                    <span class="font-semibold text-ink">{{ $v }}</span>
                </div>
            @endforeach
        </div>
    </div>
@else
    <div class="rounded-lg border border-line bg-white p-4">
        <p class="text-[14px] font-bold text-ink">Finance Control</p>
        <p class="mt-0.5 text-[11.5px] text-muted">Select a mission from the P&amp;L queue</p>
        <p class="mt-3 text-[13px] text-muted">Pick an event to open commercial control for budget, invoices, and payments.</p>
    </div>
@endif
