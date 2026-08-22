@props(['rows', 'sort', 'currency', 'selectedEventId' => null])

@php
    $money = fn (int $cents) => \App\Support\Money::abbreviated($cents, $currency);
@endphp

<div class="overflow-hidden rounded-lg border border-line bg-white">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
        <div>
            <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Event P&amp;L queue</p>
            <p class="mt-1 text-[12px] text-muted">Select a mission for commercial control</p>
        </div>
        <div class="flex items-center gap-0.5 rounded-lg border border-line bg-white p-0.5">
            @foreach (['net' => 'Net', 'margin' => 'Margin', 'charged' => 'Charged', 'income' => 'Billed', 'cost' => 'Cost'] as $key => $label)
                <button type="button" wire:click="sortBy('{{ $key }}')"
                        @class(['rounded-md px-2.5 py-1 text-[11px] font-bold transition',
                                'bg-navy-900 text-white' => $sort === $key,
                                'text-muted hover:text-ink' => $sort !== $key])>{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div class="divide-y divide-line">
        @forelse ($rows as $row)
            @php
                $e = $row['event'];
                $active = $selectedEventId === $e->id;
                $m = $row['pricedMargin'] ?? $row['margin'];
            @endphp
            <button type="button" wire:click="select({{ $e->id }})" wire:key="fin-{{ $e->id }}"
                    @class([
                        'flex w-full items-center gap-3 px-5 py-3 text-start transition',
                        'bg-gold-50' => $active,
                        'hover:bg-page' => ! $active,
                    ])>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-[13px] font-bold text-ink">{{ $e->name }}</span>
                    <span class="block truncate text-[11px] text-muted">{{ $e->client?->name ?? 'No client' }} · {{ $e->starts_at?->format('M Y') ?? 'no date' }}</span>
                </span>
                <span class="shrink-0 text-end">
                    <span @class(['block text-[13px] font-bold tabular-nums', 'text-danger-ink' => $row['net'] < 0, 'text-ink' => $row['net'] >= 0])>{{ $money($row['net']) }}</span>
                    <span class="block text-[10px] text-muted">{{ $m === null ? '—' : $m.'% margin' }}</span>
                </span>
            </button>
        @empty
            <p class="px-5 py-8 text-center text-[12px] text-muted">No active events to report on.</p>
        @endforelse
    </div>
</div>
