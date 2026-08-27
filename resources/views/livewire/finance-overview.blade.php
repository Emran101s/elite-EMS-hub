@php
    $money = fn (int $cents, ?string $cur = null) => \App\Support\Money::abbreviated($cents, $cur ?? $totals['currency']);
    $t = $totals;
    $billedPct = $t['charged'] > 0 ? (int) round($t['clientIncome'] / $t['charged'] * 100) : 0;
    $sel = $selected;
    $selEvent = $sel['event'] ?? null;
@endphp

<div class="space-y-5">

    <x-cc.header eyebrow="Finance Command" title="Profit & loss" subtitle="The whole book — what missions earn, what they cost, and who owes whom.">
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-2.5 py-1 text-[10.5px] font-bold uppercase tracking-wide text-gold-700">
                <span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Portfolio money
            </span>
            <a href="{{ route('invoices.index') }}" class="rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:-translate-y-0.5 hover:border-navy-300">Invoices →</a>
            <a href="{{ route('payments.index') }}" class="rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:-translate-y-0.5 hover:bg-gold-400">Payments →</a>
        </x-slot:actions>
    </x-cc.header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <x-cc.kpi-tile label="Charged" :value="$money($t['charged'])" :hint="$t['unbilled'] > 0 ? $money($t['unbilled']).' not yet billed' : $t['events'].' active events'" />
        <x-cc.kpi-tile label="Billed to client" :value="$money($t['clientIncome'])" :hint="$billedPct.'% of what is priced'" tone="live" />
        <x-cc.kpi-tile label="Owed to you" :value="$money($t['receivable'])" :hint="$money($t['collected']).' collected'" :tone="$t['receivable'] ? 'warn' : 'ok'" />
        {{-- Distinct from "Owed to you": this is only the slice of it that is
             past its due date — including an instalment part-paid but still
             late, which "owed" alone would not tell you to chase. --}}
        <x-cc.kpi-tile label="Overdue" :value="$money($t['overdueReceivable'])" :hint="$t['overdueCount'].' '.str('instalment')->plural($t['overdueCount']).' past due'" :tone="$t['overdueReceivable'] ? 'risk' : 'ok'" />
        <x-cc.kpi-tile label="Cost" :value="$money($t['cost'])" :hint="$money($t['payable']).' unpaid'" />
        {{-- Priced net (charged − cost) is the "are these profitable" answer;
             realized net (income − cost) goes negative while contracts sit
             unpaid, so it's shown as context in the hint, not as the headline
             loss it used to read as. --}}
        <x-cc.kpi-tile label="Net · priced" :value="$money($t['pricedNet'])"
            :hint="($t['pricedMargin'] === null ? 'nothing priced yet' : $t['pricedMargin'].'% margin').' · '.$money($t['net']).' realized'"
            :tone="$t['pricedNet'] < 0 ? 'risk' : 'ok'" />
    </div>

    <div class="grid gap-4 xl:grid-cols-12">
        <div class="space-y-4 xl:col-span-8">
            <x-finance.pnl-queue :rows="$rows" :sort="$sort" :currency="$t['currency']" :selected-event-id="$selEvent?->id" />

            @if ($categories->isNotEmpty())
                <x-finance.category-breakdown :categories="$categories" :currency="$t['currency']" />
            @endif
        </div>

        <div class="space-y-4 xl:col-span-4">
            <x-finance.mission-panel :selected="$sel" :event="$selEvent" :currency="$t['currency']" />

            @if ($selEvent)
                <x-finance.control-panel :event="$selEvent" />
            @endif

            <x-finance.receivables-desk :open="$receivables->count()" :due="$t['overdueCount']" />

            <x-finance.due-now :receivables="$receivables" :currency="$t['currency']" />
        </div>
    </div>
</div>
