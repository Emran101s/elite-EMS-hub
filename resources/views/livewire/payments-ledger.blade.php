@php
    use App\Models\EventContractPayment;

    $may = auth()->user()?->can('manage-contract') ?? false;
    $sel = $selected;
    $selState = $sel?->status();
    $out = $sel ? $sel->outstandingCents() : 0;
    $selInv = $sel?->invoice();
@endphp

<div class="eo-event-atmosphere space-y-5 rounded-[24px]">

    <x-eo.page-header
        eyebrow="Finance Command"
        title="Payments"
        subtitle="Queue → Payment → Reconciliation Panel. Every installment in the book, in the order money is due."
    >
        <x-slot:actions>
            <span class="eo-journey-chip">Reconciliation</span>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-eo-muted" />
                <input type="search" wire:model.live.debounce.300ms="q" placeholder="Installment, event, client…"
                       class="eo-input h-10 w-52 !py-0 !ps-9 text-xs xl:w-64">
            </div>
        </x-slot:actions>
    </x-eo.page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($figures as $f)
            @php
                $tone = match ($f['tone'] ?? '') {
                    'green' => 'ok', 'red' => 'risk', 'gold', 'amber' => 'warn', 'blue' => 'live', default => null,
                };
            @endphp
            <x-eo.metric-pill :label="$f['label']" :value="$f['value']" :hint="$f['note'] ?? null" :tone="$tone" />
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-1">
        <button type="button" wire:click="setStatus('all')"
                @class(['eo-btn-ghost eo-btn-sm', '!bg-eo-navy !text-white' => $status === 'all'])>All</button>
        @foreach (EventContractPayment::STATUS_META as $key => [$label, $hex])
            <button type="button" wire:click="setStatus('{{ $key }}')"
                    @class(['eo-btn-ghost eo-btn-sm inline-flex items-center gap-1.5', '!bg-eo-navy !text-white' => $status === $key])>
                <span class="h-1.5 w-1.5 rounded-full" style="background: {{ $hex }}"></span>{{ $label }}
            </button>
        @endforeach
        <p class="ms-auto text-[12px] text-eo-muted">{{ $rows->count() }} in queue</p>
    </div>

    @if ($rows->isEmpty())
        <x-eo.empty-state title="No installment matches" hint="Clear filters, or open an event Contract tab to build a schedule." icon="currency" />
    @else
        <div class="grid gap-4 xl:grid-cols-12">
            <div class="xl:col-span-4">
                <x-eo.queue-list title="Payment queue">
                    @foreach ($rows as $p)
                        @php
                            $active = $sel?->id === $p->id;
                            $state = $p->status();
                            [$label, $hex] = EventContractPayment::STATUS_META[$state];
                        @endphp
                        <button type="button" wire:click="select({{ $p->id }})" wire:key="pay-{{ $p->id }}" class="w-full text-start">
                            @if ($active)
                                <x-eo.selected-dark-card>
                                    <p class="text-[11px] font-bold uppercase tracking-wide text-eo-teal-lit">{{ $p->due_on?->format('j M Y') ?? 'No date' }}</p>
                                    <p class="mt-1 truncate text-[14px] font-semibold text-white">{{ $p->label }}</p>
                                    <p class="mt-1 truncate text-[12px] text-white/50">{{ $p->event?->name ?? '—' }}</p>
                                    <div class="mt-3 flex items-center justify-between gap-2">
                                        <span class="text-[15px] font-bold tabular-nums text-white">JD{{ number_format($p->amount_cents / 100) }}</span>
                                        <x-eo.status-pill :tone="$state === 'overdue' ? 'risk' : ($state === 'paid' ? 'ok' : 'warn')">{{ $label }}</x-eo.status-pill>
                                    </div>
                                </x-eo.selected-dark-card>
                            @else
                                <div class="rounded-2xl border border-eo-line bg-white px-4 py-3 transition hover:border-eo-teal/30 hover:shadow-eo">
                                    <p class="text-[11px] font-semibold {{ $state === 'overdue' ? 'text-eo-risk-ink' : 'text-eo-muted' }}">{{ $p->due_on?->format('j M Y') ?? 'No date' }}</p>
                                    <p class="mt-0.5 truncate text-[13px] font-bold text-eo-text">{{ $p->label }}</p>
                                    <div class="mt-2 flex items-center justify-between gap-2">
                                        <span class="truncate text-[11px] text-eo-muted">{{ $p->event?->name ?? '—' }}</span>
                                        <span class="text-[12px] font-bold tabular-nums">{{ number_format($p->outstandingCents() / 100) }}</span>
                                    </div>
                                </div>
                            @endif
                        </button>
                    @endforeach
                </x-eo.queue-list>
            </div>

            <div class="xl:col-span-5">
                @if ($sel)
                    <x-eo.detail-panel title="{{ $sel->label }}" subtitle="{{ $sel->event?->name ?? '—' }} · {{ $sel->event?->client?->name ?? 'No client' }}">
                        <x-slot:header>
                            <x-eo.status-pill :tone="$selState === 'overdue' ? 'risk' : ($selState === 'paid' ? 'ok' : 'warn')">
                                {{ EventContractPayment::STATUS_META[$selState][0] }}
                            </x-eo.status-pill>
                        </x-slot:header>

                        <x-eo.commercial-card
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
                                <div class="flex justify-between gap-3 border-b border-eo-line/70 pb-2">
                                    <span class="text-eo-muted">{{ $k }}</span>
                                    <span class="font-semibold text-eo-text">{{ $v }}</span>
                                </div>
                            @endforeach
                        </div>
                    </x-eo.detail-panel>
                @endif
            </div>

            <div class="xl:col-span-3">
                <x-eo.action-panel title="Reconciliation Panel">
                    @if ($sel)
                        @if ($sel->event)
                            <x-eo.button href="{{ route('events.hub', [$sel->event, 'tab' => 'contract']) }}" class="w-full justify-center" size="sm">Open contract →</x-eo.button>
                        @endif

                        @if ($selInv)
                            <x-eo.button variant="ghost" href="{{ route('invoices.index', ['q' => $selInv->number]) }}" class="w-full justify-center" size="sm">
                                Via {{ $selInv->number }}
                            </x-eo.button>
                            <p class="text-[11px] text-eo-muted">Recorded on the invoice — not here — to avoid double counting.</p>
                        @elseif ($may && $selState === 'paid')
                            <button type="button" wire:click="clear({{ $sel->id }})" class="eo-btn-ghost eo-btn-sm w-full justify-center">Undo receipt</button>
                        @elseif ($may)
                            <label class="eo-label">Record receipt</label>
                            <input type="text" inputmode="decimal" wire:model="amount.{{ $sel->id }}"
                                   placeholder="{{ number_format($out / 100) }}" class="eo-input text-end text-xs">
                            <x-eo.button wire:click="record({{ $sel->id }}, $wire.amount[{{ $sel->id }}])" class="w-full justify-center" size="sm">Reconcile</x-eo.button>
                        @else
                            <p class="text-[12px] text-eo-muted">View only.</p>
                        @endif
                    @else
                        <p class="text-[12px] text-eo-muted">Select an installment from the queue.</p>
                    @endif
                </x-eo.action-panel>
            </div>
        </div>
    @endif
</div>
