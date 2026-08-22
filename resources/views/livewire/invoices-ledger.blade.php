@php
    use App\Models\Invoice;

    $may = auth()->user()?->can('manage-contract') ?? false;
    $states = ['draft', 'sent', 'overdue', 'partial', 'paid', 'void'];
    $sel = $selected;
    $selState = $sel?->state();
    $out = $sel ? $sel->outstandingCents() : 0;

    $toneMap = fn ($t) => match ($t) {
        'green' => 'ok', 'red' => 'risk', 'gold', 'amber' => 'warn', 'blue', 'violet' => 'live', default => null,
    };
@endphp

<div class="space-y-5">

    <x-cc.header eyebrow="Finance Command" title="Invoices" subtitle="Queue → Invoice → Collection Panel. What has been billed, what is owed, and what is still to raise.">
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-2.5 py-1 text-[10.5px] font-bold uppercase tracking-wide text-gold-700">
                <span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Collection
            </span>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted" />
                <input type="search" wire:model.live.debounce.300ms="q" placeholder="Number, client, event…"
                       class="h-10 w-52 rounded-full border border-line bg-white pl-9 pr-3 text-[12.5px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none xl:w-64">
            </div>
            @if ($may)
                <button type="button" wire:click="create" class="rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:-translate-y-0.5 hover:bg-gold-400">＋ New invoice</button>
            @endif
        </x-slot:actions>
    </x-cc.header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($figures as $f)
            <x-cc.kpi-tile :label="$f['label']" :value="$f['value']" :hint="$f['note'] ?? null" :tone="$toneMap($f['tone'] ?? '')" />
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-1.5">
        <button type="button" wire:click="setState('all')"
                @class([
                    'rounded-full px-3 py-1.5 text-[12px] font-bold transition',
                    'bg-navy-900 text-white' => $state === 'all',
                    'bg-white text-muted ring-1 ring-line hover:text-ink' => $state !== 'all',
                ])>All</button>
        @foreach ($states as $key)
            @php [$label, $hex] = Invoice::STATE_META[$key]; @endphp
            <button type="button" wire:click="setState('{{ $key }}')"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[12px] font-bold transition',
                        'bg-navy-900 text-white' => $state === $key,
                        'bg-white text-muted ring-1 ring-line hover:text-ink' => $state !== $key,
                    ])>
                <span class="h-1.5 w-1.5 rounded-full" style="background: {{ $hex }}"></span>{{ $label }}
            </button>
        @endforeach
        <p class="ms-auto text-[12px] text-muted">{{ $rows->count() }} in queue</p>
    </div>

    @if ($ready->isNotEmpty())
        <div class="rounded-lg border border-line bg-white p-4">
            <button type="button" wire:click="toggleReady" class="flex w-full items-center gap-3 text-start">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-gold-50 text-gold-700">
                    <x-icon name="sparkles" class="h-4 w-4" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[13px] font-bold text-ink">{{ $ready->count() }} ready to invoice</span>
                    <span class="block text-[11px] text-muted">Agreed installments — never billed</span>
                </span>
                <x-icon name="chevron" class="h-4 w-4 text-muted transition {{ $showReady ? 'rotate-180' : '' }}" />
            </button>
            @if ($showReady)
                <div class="mt-3 space-y-2 border-t border-line pt-3">
                    @foreach ($ready as $p)
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[12.5px] font-bold text-ink">{{ $p->label }}</span>
                                <span class="block truncate text-[11px] text-muted">{{ $p->event?->name }}</span>
                            </span>
                            <span class="text-[13px] font-bold tabular-nums text-ink">JD{{ number_format($p->amount_cents / 100) }}</span>
                            @if ($may)
                                <button type="button" wire:click="raise({{ $p->id }})" class="rounded-full bg-gold-500 px-3 py-1.5 text-[11.5px] font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">Raise</button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @if ($rows->isEmpty())
        <x-eo.empty-state title="No invoice in view" hint="Raise from an installment above, or clear the filters." icon="document" />
    @else
        <div class="grid gap-4 xl:grid-cols-12">
            <div class="xl:col-span-4">
                <x-billing.queue title="Invoice queue">
                    @foreach ($rows as $inv)
                        @php $active = $sel?->id === $inv->id; $s = $inv->state(); @endphp
                        <button type="button" wire:click="select({{ $inv->id }})" wire:key="inv-{{ $inv->id }}" class="w-full text-start">
                            <x-billing.queue-row
                                :active="$active"
                                :eyebrow="$inv->number"
                                :title="$inv->bill_to ?: ($inv->client?->name ?: 'No client')"
                                :subtitle="$active ? ($inv->event?->name ?? 'No event') : ($inv->due_on?->format('j M') ?? '—')"
                                :subtitle-danger="! $active && $s === 'overdue'"
                                :amount="($active ? 'JD' : '').number_format($inv->totalCents() / 100)"
                                :badge-label="$active ? $inv->stateLabel() : null"
                                :badge-tone="$s === 'overdue' ? 'risk' : ($s === 'paid' ? 'ok' : 'live')"
                                :dimmed="$s === 'void'"
                            />
                        </button>
                    @endforeach
                </x-billing.queue>
            </div>

            <div class="xl:col-span-5">
                @if ($sel)
                    <x-cc.briefing-panel title="{{ $sel->number }}" subtitle="{{ $sel->bill_to ?: ($sel->client?->name ?: 'No client') }} · {{ $sel->event?->name ?? 'No event' }}">
                        <x-slot:header>
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold {{ $selState === 'overdue' ? 'bg-danger-soft text-danger-ink' : ($selState === 'paid' ? 'bg-success-soft text-success-ink' : 'bg-info-soft text-info-ink') }}">{{ $sel->stateLabel() }}</span>
                        </x-slot:header>

                        <x-billing.stat-card
                            eyebrow="Collection"
                            title="Collection posture"
                            subtitle="Outstanding vs billed"
                            :value="'JD'.number_format($out / 100)"
                            :meta="'Received JD'.number_format($sel->paid_cents / 100).' of JD'.number_format($sel->totalCents() / 100)"
                            :tone="$selState === 'overdue' ? 'warn' : ($selState === 'paid' ? 'ok' : 'premium')"
                        />

                        <div class="mt-4 space-y-2 text-[13px]">
                            @foreach ([
                                ['Issued', $sel->issued_on?->format('j M Y') ?? '—'],
                                ['Due', $sel->due_on?->format('j M Y') ?? '—'],
                                ['Tax', $sel->tax_pct ? rtrim(rtrim(number_format($sel->tax_pct, 1), '0'), '.').'%' : '—'],
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
                <x-billing.action-panel title="Collection Panel">
                    @if ($sel && $may)
                        <a href="{{ route('invoices.edit', $sel) }}" class="flex w-full items-center justify-center rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">Open editor</a>
                        <a href="{{ route('invoices.pdf', $sel) }}" class="flex w-full items-center justify-center rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:border-navy-300">Download PDF</a>

                        @if ($selState === 'draft')
                            <button type="button" wire:click="markSent({{ $sel->id }})" class="flex w-full items-center justify-center rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">Mark sent</button>
                            <button type="button" wire:click="destroyDraft({{ $sel->id }})" class="flex w-full items-center justify-center rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-danger-ink transition hover:border-navy-300">Delete draft</button>
                        @elseif ($selState === 'paid')
                            <button type="button" wire:click="clearPaid({{ $sel->id }})" class="flex w-full items-center justify-center rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:border-navy-300">Undo payment</button>
                        @elseif ($selState !== 'void')
                            <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Record receipt</label>
                            <input type="text" inputmode="decimal" wire:model="amount.{{ $sel->id }}"
                                   placeholder="{{ number_format($out / 100) }}" class="h-9 rounded-lg border border-line bg-white px-3 text-end text-[12.5px] text-ink focus:border-navy-300 focus:outline-none">
                            <button type="button" wire:click="record({{ $sel->id }}, $wire.amount[{{ $sel->id }}])" class="flex w-full items-center justify-center rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">Record payment</button>
                            <button type="button" wire:click="void({{ $sel->id }})" class="flex w-full items-center justify-center rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-danger-ink transition hover:border-navy-300">Void</button>
                        @endif
                    @elseif ($sel)
                        <p class="text-[12px] text-muted">View only.</p>
                        <a href="{{ route('invoices.edit', $sel) }}" class="flex w-full items-center justify-center rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:border-navy-300">Open invoice</a>
                    @else
                        <p class="text-[12px] text-muted">Select an invoice from the queue.</p>
                    @endif
                </x-billing.action-panel>
            </div>
        </div>
    @endif
</div>
