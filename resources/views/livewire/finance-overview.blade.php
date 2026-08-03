@php
    // The whole book is reported in the company's own currency; events run in
    // whatever the client pays in and are converted before they meet.
    $money = fn (int $cents, ?string $cur = null) => \App\Support\Money::abbreviated($cents, $cur ?? $totals['currency']);
@endphp

<div>
    {{-- ══════════ The book in one row ══════════ --}}
    @php
        $t = $totals;
        $collectedPct = $t['income'] > 0 ? (int) round($t['collected'] / $t['income'] * 100) : 0;
        $spentPct = $t['cost'] > 0 ? (int) round($t['paid'] / $t['cost'] * 100) : 0;
        // Against the client's own income, not the whole book — sponsorship
        // and exhibition money never came from a priced cost line.
        $billedPct = $t['charged'] > 0 ? (int) round($t['clientIncome'] / $t['charged'] * 100) : 0;
        $tiles = [
            // What the work is priced at, line by line — not the same question
            // as what has been billed, and the gap between them is an invoice.
            ['Charged', $money($t['charged']), 'currency', 100, 'bg-navy-400',
                $t['unbilled'] > 0 ? $money($t['unbilled']).' not yet billed' : $t['events'].' active '.str('event')->plural($t['events'])],
            ['Billed to client', $money($t['clientIncome']), 'clipboard', min(100, $billedPct), 'bg-navy-500', $billedPct.'% of what is priced'],
            ['Owed to you', $money($t['receivable']), 'bell', 100 - $collectedPct, $t['receivable'] ? 'bg-warn' : 'bg-navy-200', $money($t['collected']).' collected'],
            ['Cost', $money($t['cost']), 'archive', 100, 'bg-gold-500', $money($t['payable']).' unpaid'],
            ['Net', $money($t['net']), 'chart', max(0, (int) ($t['margin'] ?? 0)), $t['net'] >= 0 ? 'bg-track' : 'bg-risk',
                $t['pricedMargin'] === null ? 'nothing priced yet' : $t['pricedMargin'].'% margin on what is priced',
                $t['net'] < 0 ? 'text-risk' : 'text-navy-900'],
        ];
    @endphp
    <x-stat-strip :stats="$tiles" />

    <div class="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="min-w-0 space-y-4">
            {{-- ══════════ P&L by event ══════════ --}}
            <section class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3">
                    <div>
                        <p class="eyebrow">Profit &amp; loss</p>
                        <p class="text-[11px] text-muted">
                            Charged is what the budget is priced at, line by line; billed to client is what the client has been
                            contracted for. Sponsorship and exhibition income sit outside both — they never came from a cost line —
                            but they do count towards net.
                            Cost counts what you have committed — the real figure where one exists, the estimate until then.
                            @if ($totals['mixed'])
                                Events run in other currencies are converted to {{ $totals['currency'] }}.
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-0.5 rounded-xl border border-line bg-white p-0.5">
                        @foreach (['net' => 'Net', 'margin' => 'Margin', 'charged' => 'Charged', 'income' => 'Billed', 'cost' => 'Cost'] as $key => $label)
                            <button type="button" wire:click="sortBy('{{ $key }}')"
                                    @class(['rounded-lg px-2.5 py-1 text-[11px] font-bold transition',
                                            'bg-navy-900 text-white' => $sort === $key,
                                            'text-navy-500 hover:text-navy-900' => $sort !== $key])>{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="scrollbar-none overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left">
                        <thead>
                            <tr class="border-b border-line bg-page/60 text-[9px] font-bold uppercase tracking-[0.14em] text-navy-300">
                                <th class="px-4 py-2">Event</th>
                                <th class="px-3 py-2 text-right">Charged</th>
                                <th class="px-3 py-2 text-right">Billed to client</th>
                                <th class="px-3 py-2 text-right">Cost</th>
                                <th class="px-3 py-2 text-right">Net</th>
                                <th class="px-3 py-2 text-right">Margin</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                @php $e = $row['event']; @endphp
                                <tr class="border-b border-line/70 transition last:border-0 hover:bg-page/40">
                                    <td class="px-4 py-2.5">
                                        <a href="{{ route('events.hub', [$e, 'tab' => 'budget']) }}" class="block">
                                            <span class="block truncate text-[12.5px] font-bold text-navy-900">{{ $e->name }}</span>
                                            <span class="block truncate text-[10.5px] text-muted">{{ $e->client?->name ?? 'No client' }} · {{ $e->starts_at?->format('M Y') ?? 'no date' }}</span>
                                        </a>
                                    </td>
                                    <td class="px-3 py-2.5 text-right text-[12px] tabular-nums text-navy-700">
                                        {{ $money($row['charged']) }}
                                        @if ($row['currency'] !== $totals['currency'])
                                            <span class="block text-[10px] text-muted" title="Run in {{ $row['currency'] }}, converted">from {{ $row['currency'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-right text-[12px] tabular-nums">
                                        <span class="{{ $row['receivable'] > 0 ? 'text-warn' : 'text-track' }}">{{ $money($row['clientIncome']) }}</span>
                                        @if ($row['unbilled'] > 0)
                                            <span class="block text-[10px] text-muted">{{ $money($row['unbilled']) }} to bill</span>
                                        @elseif ($row['receivable'] > 0)
                                            <span class="block text-[10px] text-muted">{{ $money($row['receivable']) }} owed</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-right text-[12px] tabular-nums text-navy-700">
                                        {{ $money($row['cost']) }}
                                        @if ($row['overrun'])
                                            <span class="block text-[10px] font-bold text-risk">{{ $row['overrun'] }}% over estimate</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-right text-[12.5px] font-bold tabular-nums {{ $row['net'] < 0 ? 'text-risk' : 'text-navy-900' }}">{{ $money($row['net']) }}</td>
                                    <td class="px-3 py-2.5 text-right">
                                        @php $m = $row['pricedMargin'] ?? $row['margin']; @endphp
                                        @if ($m === null)
                                            <span class="text-[11px] text-navy-300">—</span>
                                        @else
                                            <span @class(['text-[12px] font-bold tabular-nums',
                                                'text-track' => $m >= 25,
                                                'text-warn' => $m >= 10 && $m < 25,
                                                'text-risk' => $m < 10])>{{ $m }}%</span>
                                            @if ($row['margin'] !== null && $row['margin'] !== $m)
                                                <span class="block text-[10px] text-muted" title="Margin on what has actually been billed">{{ $row['margin'] }}% billed</span>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-8 text-center text-[12px] text-muted">No active events to report on.</td></tr>
                            @endforelse
                        </tbody>
                        @if ($rows->isNotEmpty())
                            <tfoot>
                                <tr class="border-t-2 border-line bg-page/60 text-[12px] font-bold">
                                    <td class="px-4 py-2.5 text-navy-900">Portfolio</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-navy-900">{{ $money($t['income']) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-navy-900">{{ $money($t['collected']) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-navy-900">{{ $money($t['cost']) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums {{ $t['net'] < 0 ? 'text-risk' : 'text-navy-900' }}">{{ $money($t['net']) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-navy-900">{{ $t['margin'] === null ? '—' : $t['margin'].'%' }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </section>

            {{-- ══════════ Where the money goes ══════════ --}}
            @if ($categories->isNotEmpty())
                <section class="card p-4">
                    <p class="eyebrow mb-3">Where the money goes</p>
                    @php $top = (int) $categories->max('cost') ?: 1; @endphp
                    <div class="space-y-2">
                        @foreach ($categories->take(8) as $c)
                            <div>
                                <div class="flex items-baseline justify-between gap-2 text-[11.5px]">
                                    <span class="truncate text-navy-700">{{ $c['label'] }}</span>
                                    <span class="shrink-0 font-bold tabular-nums text-navy-900">{{ $money($c['cost']) }}</span>
                                </div>
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-navy-50">
                                    <div class="h-full rounded-full bg-gold-500" style="width: {{ round($c['cost'] / $top * 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- ══════════ Which work earns ══════════ --}}
            @if ($byType->count() > 1)
                <section class="card overflow-hidden">
                    <div class="border-b border-line px-4 py-3">
                        <p class="eyebrow">Which work earns</p>
                        <p class="text-[11px] text-muted">Net by event type — the answer to what is actually worth bidding for.</p>
                    </div>
                    <div class="divide-y divide-line">
                        @foreach ($byType as $t2)
                            <div class="flex items-center gap-3 px-4 py-2.5">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-[12.5px] font-semibold text-navy-900">{{ $t2['type'] }}</span>
                                    <span class="block text-[10.5px] text-muted">{{ $t2['events'] }} {{ str('event')->plural($t2['events']) }} · {{ $money($t2['income']) }} income</span>
                                </span>
                                <span class="shrink-0 text-right">
                                    <span class="block text-[12.5px] font-bold tabular-nums {{ $t2['net'] < 0 ? 'text-risk' : 'text-navy-900' }}">{{ $money($t2['net']) }}</span>
                                    <span class="block text-[10px] text-muted">{{ $t2['margin'] === null ? '—' : $t2['margin'].'% margin' }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        {{-- ══════════ Who owes whom ══════════ --}}
        <aside class="flex flex-col gap-4 self-start xl:sticky xl:top-4">
            <section class="card overflow-hidden">
                <div class="border-b border-line px-4 py-3">
                    <p class="eyebrow">Contract instalments due</p>
                    <p class="text-[11px] text-muted">
                        {{-- Said plainly, because the two figures are not the same thing. --}}
                        Contract payments only. The {{ $money($t['receivable']) }} owed to you also includes sponsor and exhibitor income.
                    </p>
                </div>
                <div class="divide-y divide-line">
                    @forelse ($receivables as $p)
                        @php $overdue = $p->status() === 'overdue'; @endphp
                        <a href="{{ route('events.hub', [$p->event, 'tab' => 'contract']) }}" class="flex items-center gap-3 px-4 py-2.5 transition hover:bg-page/50">
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $overdue ? 'bg-risk' : 'bg-warn' }}"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[12px] font-semibold text-navy-900">{{ $p->event?->name }}</span>
                                <span class="block truncate text-[10.5px] text-muted">{{ \Illuminate\Support\Str::limit($p->label, 34) }}</span>
                            </span>
                            <span class="shrink-0 text-right">
                                <span class="block text-[11.5px] font-bold tabular-nums text-navy-900">{{ $money($p->outstandingCents()) }}</span>
                                <span class="block text-[10px] tabular-nums {{ $overdue ? 'font-bold text-risk' : 'text-muted' }}">{{ $p->due_on?->format('j M') ?? '—' }}</span>
                            </span>
                        </a>
                    @empty
                        <p class="px-4 py-5 text-center text-[11.5px] text-muted">Every contract instalment is settled.</p>
                    @endforelse
                </div>
            </section>

            <section class="card overflow-hidden">
                <div class="border-b border-line px-4 py-3">
                    <p class="eyebrow">You owe</p>
                    <p class="text-[11px] text-muted">Budget lines with something still unpaid.</p>
                </div>
                <div class="divide-y divide-line">
                    @forelse ($payables as $item)
                        @php $late = $item->due_on && $item->due_on->isPast(); @endphp
                        <a href="{{ route('events.hub', [$item->event, 'tab' => 'budget']) }}" class="flex items-center gap-3 px-4 py-2.5 transition hover:bg-page/50">
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $late ? 'bg-risk' : 'bg-navy-200' }}"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[12px] font-semibold text-navy-900">{{ $item->description ?: $item->categoryLabel() }}</span>
                                <span class="block truncate text-[10.5px] text-muted">{{ $item->supplier?->name ?? $item->vendor ?? $item->event?->name }}</span>
                            </span>
                            <span class="shrink-0 text-right">
                                <span class="block text-[11.5px] font-bold tabular-nums text-navy-900">{{ $money($item->outstandingCents()) }}</span>
                                <span class="block text-[10px] tabular-nums {{ $late ? 'font-bold text-risk' : 'text-muted' }}">{{ $item->due_on?->format('j M') ?? 'no date' }}</span>
                            </span>
                        </a>
                    @empty
                        <p class="px-4 py-5 text-center text-[11.5px] text-muted">Nothing outstanding.</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
</div>
