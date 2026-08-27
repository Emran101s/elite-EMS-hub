@php
    use App\Models\EventContractPayment;

    $may = auth()->user()?->can('manage-contract') ?? false;
    $sel = $selected;
    $selState = $sel?->status();
    $out = $sel ? $sel->outstandingCents() : 0;
    $selInv = $sel?->invoice();

    $toneMap = fn ($t) => match ($t) {
        'green' => 'ok', 'red' => 'risk', 'gold', 'amber' => 'warn', 'blue' => 'live', default => null,
    };
@endphp

<div class="space-y-5">

    <x-cc.header eyebrow="Finance Command" title="Payments" subtitle="Queue → Payment → Reconciliation Panel. Every installment in the book, in the order money is due.">
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-2.5 py-1 text-[10.5px] font-bold uppercase tracking-wide text-gold-700">
                <span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Reconciliation
            </span>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted" />
                <input type="search" wire:model.live.debounce.300ms="q" placeholder="Installment, event, client…"
                       class="h-10 w-52 rounded-full border border-line bg-white pl-9 pr-3 text-[12.5px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none xl:w-64">
            </div>
        </x-slot:actions>
    </x-cc.header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($figures as $f)
            <x-cc.kpi-tile :label="$f['label']" :value="$f['value']" :hint="$f['note'] ?? null" :tone="$toneMap($f['tone'] ?? '')" />
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-1.5">
        <button type="button" wire:click="setStatus('all')"
                @class([
                    'rounded-full px-3 py-1.5 text-[12px] font-bold transition',
                    'bg-navy-900 text-white' => $status === 'all',
                    'bg-white text-muted ring-1 ring-line hover:text-ink' => $status !== 'all',
                ])>All</button>
        @foreach (EventContractPayment::STATUS_META as $key => [$label, $hex])
            <button type="button" wire:click="setStatus('{{ $key }}')"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[12px] font-bold transition',
                        'bg-navy-900 text-white' => $status === $key,
                        'bg-white text-muted ring-1 ring-line hover:text-ink' => $status !== $key,
                    ])>
                <span class="h-1.5 w-1.5 rounded-full" style="background: {{ $hex }}"></span>{{ $label }}
            </button>
        @endforeach
        <p class="ms-auto text-[12px] text-muted">{{ $rows->count() }} in queue</p>
    </div>

    @if ($rows->isEmpty())
        <x-eo.empty-state title="No installment matches" hint="Clear filters, or open an event Contract tab to build a schedule." icon="currency" />
    @else
        <div class="grid gap-4 xl:grid-cols-12">
            <div class="xl:col-span-4">
                <x-billing.queue title="Payment queue">
                    @foreach ($rows as $p)
                        @php
                            $active = $sel?->id === $p->id;
                            $state = $p->status();
                            [$label, $hex] = EventContractPayment::STATUS_META[$state];
                        @endphp
                        <button type="button" wire:click="select({{ $p->id }})" wire:key="pay-{{ $p->id }}" class="w-full text-start">
                            <x-billing.queue-row
                                :active="$active"
                                :eyebrow="$p->due_on?->format('j M Y') ?? 'No date'"
                                :eyebrow-danger="! $active && $state === 'overdue'"
                                :title="$p->label"
                                :subtitle="$p->event?->name ?? '—'"
                                :amount="($active ? 'JD' : '').number_format(($active ? $p->amount_cents : $p->outstandingCents()) / 100)"
                                :badge-label="$active ? $label : null"
                                :badge-tone="$state === 'overdue' ? 'risk' : ($state === 'paid' ? 'ok' : 'warn')"
                            />
                        </button>
                    @endforeach
                </x-billing.queue>
            </div>

            <div class="xl:col-span-5">
                @if ($sel)
                    <x-cc.briefing-panel :title="$sel->label" :subtitle="($sel->event?->name ?? '—').' · '.($sel->event?->client?->name ?? 'No client')">
                        <x-slot:header>
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold {{ $selState === 'overdue' ? 'bg-danger-soft text-danger-ink' : ($selState === 'paid' ? 'bg-success-soft text-success-ink' : 'bg-warning-soft text-warning-ink') }}">{{ EventContractPayment::STATUS_META[$selState][0] }}</span>
                        </x-slot:header>

                        <x-billing.stat-card
                            eyebrow="Reconciliation"
                            title="Still due"
                            :subtitle="$sel->contract?->reference ?: ($sel->contract?->displayTitle() ?? 'Contract')"
                            :value="'JD'.number_format($out / 100)"
                            :meta="'Received JD'.number_format($sel->paid_cents / 100).' of JD'.number_format($sel->amount_cents / 100)"
                            :tone="$selState === 'overdue' ? 'warn' : ($selState === 'paid' ? 'ok' : 'premium')"
                        />

                        <div class="mt-4 space-y-2 text-[13px]">
                            @foreach ([
                                ['Due', $sel->due_on?->format('j M Y') ?? '—'],
                                ['Share', $sel->pct ? rtrim(rtrim(number_format($sel->pct, 1), '0'), '.').'%' : '—'],
                                ['Invoice', $selInv?->number ?? 'Not raised'],
                            ] as [$k, $v])
                                <div class="flex justify-between gap-3 border-b border-line/70 pb-2">
                                    <span class="text-muted">{{ $k }}</span>
                                    <span class="font-semibold text-ink">{{ $v }}</span>
                                </div>
                            @endforeach
                        </div>
                    </x-cc.briefing-panel>
                @endif
            </div>

            <div class="xl:col-span-3">
                <x-billing.action-panel title="Reconciliation Panel">
                    @if ($sel)
                        @if ($sel->event)
                            <a href="{{ route('events.hub', [$sel->event, 'tab' => 'contract']) }}" class="flex w-full items-center justify-center rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">Open contract →</a>
                        @endif

                        @if ($selInv)
                            <a href="{{ route('invoices.index', ['q' => $selInv->number]) }}" class="flex w-full items-center justify-center rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:border-navy-300">Via {{ $selInv->number }}</a>
                            <p class="text-[11px] text-muted">Recorded on the invoice — not here — to avoid double counting.</p>
                        @elseif ($may && $selState === 'paid')
                            <button type="button" wire:click="clear({{ $sel->id }})" class="flex w-full items-center justify-center rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:border-navy-300">Undo receipt</button>
                        @elseif ($may)
                            <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Record receipt</label>
                            <input type="text" inputmode="decimal" wire:model="amount.{{ $sel->id }}"
                                   placeholder="{{ number_format($out / 100) }}" class="h-9 rounded-lg border border-line bg-white px-3 text-end text-[12.5px] text-ink focus:border-navy-300 focus:outline-none">
                            <button type="button" wire:click="record({{ $sel->id }}, $wire.amount[{{ $sel->id }}])" class="flex w-full items-center justify-center rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">Reconcile</button>
                        @else
                            <p class="text-[12px] text-muted">View only.</p>
                        @endif
                    @else
                        <p class="text-[12px] text-muted">Select an installment from the queue.</p>
                    @endif
                </x-billing.action-panel>
            </div>
        </div>
    @endif
</div>
