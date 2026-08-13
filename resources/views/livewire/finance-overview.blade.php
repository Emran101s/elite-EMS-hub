@php
    $money = fn (int $cents, ?string $cur = null) => \App\Support\Money::abbreviated($cents, $cur ?? $totals['currency']);
    $t = $totals;
    $collectedPct = $t['income'] > 0 ? (int) round($t['collected'] / $t['income'] * 100) : 0;
    $billedPct = $t['charged'] > 0 ? (int) round($t['clientIncome'] / $t['charged'] * 100) : 0;
    $sel = $selected;
    $selEvent = $sel['event'] ?? null;
@endphp

<div class="eo-event-atmosphere space-y-5 rounded-[24px]">

    <x-eo.page-header
        eyebrow="Finance Command"
        title="Profit & loss"
        subtitle="The whole book — what missions earn, what they cost, and who owes whom."
    >
        <x-slot:actions>
            <span class="eo-journey-chip">Portfolio money</span>
            <x-eo.button variant="ghost" size="sm" href="{{ route('invoices.index') }}">Invoices →</x-eo.button>
            <x-eo.button size="sm" href="{{ route('payments.index') }}">Payments →</x-eo.button>
        </x-slot:actions>
    </x-eo.page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <x-eo.metric-pill label="Charged" :value="$money($t['charged'])" :hint="$t['unbilled'] > 0 ? $money($t['unbilled']).' not yet billed' : $t['events'].' active events'" />
        <x-eo.metric-pill label="Billed to client" :value="$money($t['clientIncome'])" :hint="$billedPct.'% of what is priced'" tone="live" />
        <x-eo.metric-pill label="Owed to you" :value="$money($t['receivable'])" :hint="$money($t['collected']).' collected'" :tone="$t['receivable'] ? 'warn' : 'ok'" />
        {{-- Distinct from "Owed to you": this is only the slice of it that is
             past its due date — including an instalment part-paid but still
             late, which "owed" alone would not tell you to chase. --}}
        <x-eo.metric-pill label="Overdue" :value="$money($t['overdueReceivable'])" :hint="$t['overdueCount'].' '.str('instalment')->plural($t['overdueCount']).' past due'" :tone="$t['overdueReceivable'] ? 'risk' : 'ok'" />
        <x-eo.metric-pill label="Cost" :value="$money($t['cost'])" :hint="$money($t['payable']).' unpaid'" />
        <x-eo.metric-pill label="Net" :value="$money($t['net'])" :hint="$t['pricedMargin'] === null ? 'nothing priced yet' : $t['pricedMargin'].'% margin'" :tone="$t['net'] < 0 ? 'risk' : 'ok'" />
    </div>

    <div class="grid gap-4 xl:grid-cols-12">
        <div class="space-y-4 xl:col-span-8">
            <x-eo.soft-card class="overflow-hidden !p-0">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-eo-line px-5 py-4">
                    <div>
                        <p class="eo-label">Event P&amp;L queue</p>
                        <p class="mt-1 text-[12px] text-eo-muted">Select a mission for commercial control</p>
                    </div>
                    <div class="flex items-center gap-0.5 rounded-xl border border-eo-line bg-white p-0.5">
                        @foreach (['net' => 'Net', 'margin' => 'Margin', 'charged' => 'Charged', 'income' => 'Billed', 'cost' => 'Cost'] as $key => $label)
                            <button type="button" wire:click="sortBy('{{ $key }}')"
                                    @class(['rounded-lg px-2.5 py-1 text-[11px] font-bold transition',
                                            'bg-eo-navy text-white' => $sort === $key,
                                            'text-eo-muted hover:text-eo-text' => $sort !== $key])>{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="divide-y divide-eo-line">
                    @forelse ($rows as $row)
                        @php
                            $e = $row['event'];
                            $active = $selEvent?->id === $e->id;
                            $m = $row['pricedMargin'] ?? $row['margin'];
                        @endphp
                        <button type="button" wire:click="select({{ $e->id }})" wire:key="fin-{{ $e->id }}"
                                @class([
                                    'flex w-full items-center gap-3 px-5 py-3 text-start transition',
                                    'bg-eo-teal-soft/40' => $active,
                                    'hover:bg-eo-workspace' => ! $active,
                                ])>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[13px] font-bold text-eo-text">{{ $e->name }}</span>
                                <span class="block truncate text-[11px] text-eo-muted">{{ $e->client?->name ?? 'No client' }} · {{ $e->starts_at?->format('M Y') ?? 'no date' }}</span>
                            </span>
                            <span class="shrink-0 text-end">
                                <span @class(['block text-[13px] font-bold tabular-nums', 'text-eo-risk' => $row['net'] < 0, 'text-eo-text' => $row['net'] >= 0])>{{ $money($row['net']) }}</span>
                                <span class="block text-[10px] text-eo-muted">{{ $m === null ? '—' : $m.'% margin' }}</span>
                            </span>
                        </button>
                    @empty
                        <p class="px-5 py-8 text-center text-[12px] text-eo-muted">No active events to report on.</p>
                    @endforelse
                </div>
            </x-eo.soft-card>

            @if ($categories->isNotEmpty())
                <x-eo.soft-card>
                    <p class="eo-label mb-3">Where the money goes</p>
                    @php $top = (int) $categories->max('cost') ?: 1; @endphp
                    <div class="space-y-2">
                        @foreach ($categories->take(8) as $c)
                            <div>
                                <div class="flex items-baseline justify-between gap-2 text-[11.5px]">
                                    <span class="truncate text-eo-text">{{ $c['label'] }}</span>
                                    <span class="shrink-0 font-bold tabular-nums">{{ $money($c['cost']) }}</span>
                                </div>
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-eo-bg">
                                    <div class="h-full rounded-full bg-eo-teal" style="width: {{ round($c['cost'] / $top * 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-eo.soft-card>
            @endif
        </div>

        <div class="space-y-4 xl:col-span-4">
            @if ($selEvent)
                <x-eo.detail-panel title="{{ $selEvent->name }}" subtitle="{{ $selEvent->client?->name ?? 'No client' }}">
                    <x-eo.commercial-card
                        title="Mission net"
                        :value="$money($sel['net'])"
                        :meta="'Charged '.$money($sel['charged']).' · Cost '.$money($sel['cost'])"
                        :tone="$sel['net'] < 0 ? 'warn' : 'ok'"
                    />
                    <div class="mt-4 space-y-2 text-[13px]">
                        @foreach ([
                            ['Billed', $money($sel['clientIncome'])],
                            ['Receivable', $money($sel['receivable'])],
                            ['Unbilled', $money($sel['unbilled'] ?? 0)],
                            ['Margin', ($sel['pricedMargin'] ?? $sel['margin']) === null ? '—' : ($sel['pricedMargin'] ?? $sel['margin']).'%'],
                        ] as [$k, $v])
                            <div class="flex justify-between gap-3 border-b border-eo-line/70 pb-2">
                                <span class="text-eo-muted">{{ $k }}</span>
                                <span class="font-semibold text-eo-text">{{ $v }}</span>
                            </div>
                        @endforeach
                    </div>
                </x-eo.detail-panel>

                <x-eo.action-panel title="Finance Control">
                    <x-eo.button href="{{ route('events.hub', [$selEvent, 'tab' => 'budget']) }}" class="w-full justify-center" size="sm">Budget desk →</x-eo.button>
                    <x-eo.button variant="ghost" href="{{ route('invoices.index', ['q' => $selEvent->name]) }}" class="w-full justify-center" size="sm">Invoices</x-eo.button>
                    <x-eo.button variant="ghost" href="{{ route('payments.index', ['q' => $selEvent->name]) }}" class="w-full justify-center" size="sm">Payments</x-eo.button>
                </x-eo.action-panel>
            @else
                <x-eo.detail-panel title="Finance Control" subtitle="Select a mission from the P&L queue">
                    <p class="text-[13px] text-eo-muted">Pick an event to open commercial control for budget, invoices, and payments.</p>
                </x-eo.detail-panel>
            @endif

            <x-eo.operations-card
                title="Receivables desk"
                subtitle="Contract instalments due"
                :open="$receivables->count()"
                :due="$t['overdueCount']"
                :blocked="0"
            />

            <x-eo.soft-card class="!p-0 overflow-hidden">
                <div class="border-b border-eo-line px-4 py-3">
                    <p class="eo-label">Due now</p>
                </div>
                <div class="divide-y divide-eo-line">
                    @forelse ($receivables->take(6) as $p)
                        {{-- Same "past due and still short" test as the Overdue KPI —
                             not status(), which calls a part-paid late instalment
                             merely "partial" and would light the wrong dot here. --}}
                        @php $overdue = $p->due_on?->isPast() && $p->outstandingCents() > 0; @endphp
                        <a href="{{ route('events.hub', [$p->event, 'tab' => 'contract']) }}" class="flex items-center gap-3 px-4 py-2.5 transition hover:bg-eo-workspace">
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $overdue ? 'bg-eo-risk' : 'bg-eo-warn' }}"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[12px] font-semibold text-eo-text">{{ $p->event?->name }}</span>
                                <span class="block truncate text-[10.5px] text-eo-muted">{{ \Illuminate\Support\Str::limit($p->label, 34) }}</span>
                            </span>
                            <span class="shrink-0 text-end text-[11.5px] font-bold tabular-nums">{{ $money($p->outstandingCents()) }}</span>
                        </a>
                    @empty
                        <p class="px-4 py-5 text-center text-[11.5px] text-eo-muted">Every instalment is settled.</p>
                    @endforelse
                </div>
            </x-eo.soft-card>
        </div>
    </div>
</div>
