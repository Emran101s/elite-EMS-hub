@props(['selected', 'offer'])

@php
    $money = fn (int $cents, string $cur = 'JOD') => \App\Support\Money::abbreviated($cents, $cur);
@endphp

<div class="rounded-lg border border-line bg-white p-4">
    <div class="mb-3 flex items-start justify-between gap-2">
        <div class="min-w-0">
            <p class="truncate text-[14px] font-bold text-ink">{{ $selected->title }}</p>
            <p class="mt-0.5 text-[11.5px] text-muted">Deal · {{ $selected->stageLabel() }}</p>
        </div>
        <button type="button" wire:click="editDeal({{ $selected->id }})" class="shrink-0 rounded-full border border-line bg-white px-2.5 py-1 text-[11px] font-bold text-ink transition hover:border-navy-300">Edit</button>
    </div>

    <div class="space-y-3.5">
        <div>
            <div class="flex items-baseline justify-between gap-2 text-[11px]">
                <span class="text-muted">{{ $selected->stageLabel() }}</span>
                <span class="font-bold tabular-nums" style="color: {{ $selected->stageHex() }}">{{ $selected->probability }}%</span>
            </div>
            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-page">
                <div class="h-full rounded-full" style="width: {{ $selected->probability }}%; background: {{ $selected->stageHex() }}"></div>
            </div>
        </div>

        <div class="rounded-md bg-page px-3 py-2.5">
            <p class="text-eyebrow font-bold uppercase tracking-[0.1em] text-muted">Deal value</p>
            <p class="mt-1 text-[18px] font-extrabold tabular-nums text-ink">{{ $money($selected->value_cents, $selected->currency) }}</p>
            <p class="mt-0.5 text-[11px] text-muted">{{ $selected->client?->name ?? 'No client' }} · Weighted {{ $money($selected->weightedCents(), $selected->currency) }}</p>
        </div>

        @foreach ([
            ['Value', $money($selected->value_cents, $selected->currency)],
            ['Weighted', $money($selected->weightedCents(), $selected->currency)],
            ['Client', $selected->client?->name ?? '—', $selected->client ? route('crm.client', $selected->client) : null],
            ['Contact', $selected->contact?->name ?? 'None set'],
            ['Owner', $selected->owner?->name ?? 'Unassigned'],
            ['Decision by', $selected->expected_close_on?->format('j M Y') ?? '—'],
            ['Event date', $selected->expected_event_on?->format('j M Y') ?? '—'],
            ['Source', $selected->source ?? '—'],
        ] as $row)
            @php [$k, $v] = $row; $href = $row[2] ?? null; @endphp
            <div class="flex justify-between gap-3 border-b border-line/70 pb-2 text-[12px] last:border-0">
                <span class="text-muted">{{ $k }}</span>
                @if ($href)
                    <a href="{{ $href }}" class="truncate font-semibold text-gold-700 transition hover:text-gold-800">{{ $v }} →</a>
                @else
                    <span class="truncate font-semibold text-ink">{{ $v }}</span>
                @endif
            </div>
        @endforeach

        @if ($selected->notes)
            <p class="rounded-md bg-page px-3 py-2 text-[11.5px] leading-relaxed text-muted">{{ $selected->notes }}</p>
        @endif

        <div class="rounded-md border border-line bg-page px-3 py-2.5">
            <div class="flex items-baseline justify-between gap-2">
                <span class="text-eyebrow font-bold uppercase tracking-[0.1em] text-muted">Offer</span>
                @if ($offer)
                    @php [$offerLabel, $offerHex] = \App\Models\Proposal::STATE_META[$offer->state()]; @endphp
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold text-white" style="background: {{ $offerHex }}">{{ $offerLabel }}</span>
                @endif
            </div>

            @if ($offer)
                <a href="{{ route('proposals.edit', $offer) }}" wire:navigate
                   class="mt-1.5 flex items-baseline justify-between gap-2 text-[12px] font-semibold text-gold-700 transition hover:text-gold-800">
                    <span class="truncate font-mono text-[11px]">{{ $offer->number }}</span>
                    <span class="shrink-0 tabular-nums">{{ $money($offer->totalCents(), $offer->currencyCode()) }} →</span>
                </a>
            @else
                <p class="mt-1 text-[11.5px] leading-relaxed text-muted">
                    Nothing sent yet. Winning now opens the event with an empty budget.
                </p>
            @endif
        </div>
    </div>
</div>
