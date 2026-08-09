@php
    use App\Models\Invoice;

    $may = auth()->user()?->can('manage-contract') ?? false;
    $states = ['draft', 'sent', 'overdue', 'partial', 'paid', 'void'];
    $sel = $selected;
    $selState = $sel?->state();
    $out = $sel ? $sel->outstandingCents() : 0;
@endphp

<div class="eo-event-atmosphere space-y-5 rounded-[24px]">

    <x-eo.page-header
        eyebrow="Finance Command"
        title="Invoices"
        subtitle="Queue → Invoice → Collection Panel. What has been billed, what is owed, and what is still to raise."
    >
        <x-slot:actions>
            <span class="eo-journey-chip">Collection</span>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-eo-muted" />
                <input type="search" wire:model.live.debounce.300ms="q" placeholder="Number, client, event…"
                       class="eo-input h-10 w-52 !py-0 !ps-9 text-xs xl:w-64">
            </div>
            @if ($may)
                <x-eo.button size="sm" wire:click="create">＋ New invoice</x-eo.button>
            @endif
        </x-slot:actions>
    </x-eo.page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($figures as $f)
            @php
                $tone = match ($f['tone'] ?? '') {
                    'green' => 'ok', 'red' => 'risk', 'gold', 'amber' => 'warn', 'blue', 'violet' => 'live', default => null,
                };
            @endphp
            <x-eo.metric-pill :label="$f['label']" :value="$f['value']" :hint="$f['note'] ?? null" :tone="$tone" />
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-1">
        <button type="button" wire:click="setState('all')"
                @class(['eo-btn-ghost eo-btn-sm', '!bg-eo-navy !text-white' => $state === 'all'])>All</button>
        @foreach ($states as $key)
            @php [$label, $hex] = Invoice::STATE_META[$key]; @endphp
            <button type="button" wire:click="setState('{{ $key }}')"
                    @class(['eo-btn-ghost eo-btn-sm inline-flex items-center gap-1.5', '!bg-eo-navy !text-white' => $state === $key])>
                <span class="h-1.5 w-1.5 rounded-full" style="background: {{ $hex }}"></span>{{ $label }}
            </button>
        @endforeach
        <p class="ms-auto text-[12px] text-eo-muted">{{ $rows->count() }} in queue</p>
    </div>

    @if ($ready->isNotEmpty())
        <x-eo.soft-card>
            <button type="button" wire:click="toggleReady" class="flex w-full items-center gap-3 text-start">
                <span class="grid h-8 w-8 place-items-center rounded-xl bg-eo-teal-soft text-eo-teal">
                    <x-icon name="sparkles" class="h-4 w-4" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[13px] font-bold text-eo-text">{{ $ready->count() }} ready to invoice</span>
                    <span class="block text-[11px] text-eo-muted">Agreed installments — never billed</span>
                </span>
                <x-icon name="chevron" class="h-4 w-4 text-eo-muted transition {{ $showReady ? 'rotate-180' : '' }}" />
            </button>
            @if ($showReady)
                <div class="mt-3 space-y-2 border-t border-eo-line pt-3">
                    @foreach ($ready as $p)
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[12.5px] font-bold text-eo-text">{{ $p->label }}</span>
                                <span class="block truncate text-[11px] text-eo-muted">{{ $p->event?->name }}</span>
                            </span>
                            <span class="text-[13px] font-bold tabular-nums">JD{{ number_format($p->amount_cents / 100) }}</span>
                            @if ($may)
                                <x-eo.button size="sm" wire:click="raise({{ $p->id }})">Raise</x-eo.button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </x-eo.soft-card>
    @endif

    @if ($rows->isEmpty())
        <x-eo.empty-state title="No invoice in view" hint="Raise from an installment above, or clear the filters." icon="document" />
    @else
        <div class="grid gap-4 xl:grid-cols-12">
            <div class="xl:col-span-4">
                <x-eo.queue-list title="Invoice queue">
                    @foreach ($rows as $inv)
                        @php $active = $sel?->id === $inv->id; $s = $inv->state(); @endphp
                        <button type="button" wire:click="select({{ $inv->id }})" wire:key="inv-{{ $inv->id }}" class="w-full text-start">
                            @if ($active)
                                <x-eo.selected-dark-card>
                                    <p class="font-mono text-[11px] text-eo-teal-lit">{{ $inv->number }}</p>
                                    <p class="mt-1 truncate text-[14px] font-semibold text-white">
                                        {{ $inv->bill_to ?: ($inv->client?->name ?: 'No client') }}
                                    </p>
                                    <p class="mt-1 truncate text-[12px] text-white/50">{{ $inv->event?->name ?? 'No event' }}</p>
                                    <div class="mt-3 flex items-center justify-between gap-2">
                                        <span class="text-[15px] font-bold tabular-nums text-white">JD{{ number_format($inv->totalCents() / 100) }}</span>
                                        <x-eo.status-pill :tone="$s === 'overdue' ? 'risk' : ($s === 'paid' ? 'ok' : 'live')">{{ $inv->stateLabel() }}</x-eo.status-pill>
                                    </div>
                                </x-eo.selected-dark-card>
                            @else
                                <div class="rounded-2xl border border-eo-line bg-white px-4 py-3 transition hover:border-eo-teal/30 hover:shadow-eo {{ $s === 'void' ? 'opacity-55' : '' }}">
                                    <p class="font-mono text-[11px] text-eo-muted">{{ $inv->number }}</p>
                                    <p class="mt-0.5 truncate text-[13px] font-bold text-eo-text">{{ $inv->bill_to ?: ($inv->client?->name ?: 'No client') }}</p>
                                    <div class="mt-2 flex items-center justify-between gap-2">
                                        <span class="text-[11px] {{ $s === 'overdue' ? 'font-bold text-eo-risk' : 'text-eo-muted' }}">{{ $inv->due_on?->format('j M') ?? '—' }}</span>
                                        <span class="text-[12px] font-bold tabular-nums">{{ number_format($inv->totalCents() / 100) }}</span>
                                    </div>
                                </div>
                            @endif
                        </button>
                    @endforeach
                </x-eo.queue-list>
            </div>

            <div class="xl:col-span-5">
                @if ($sel)
                    <x-eo.detail-panel title="{{ $sel->number }}" subtitle="{{ $sel->bill_to ?: ($sel->client?->name ?: 'No client') }} · {{ $sel->event?->name ?? 'No event' }}">
                        <x-slot:header>
                            <x-eo.status-pill :tone="$selState === 'overdue' ? 'risk' : ($selState === 'paid' ? 'ok' : 'live')">{{ $sel->stateLabel() }}</x-eo.status-pill>
                        </x-slot:header>

                        <x-eo.commercial-card
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
                <x-eo.action-panel title="Collection Panel">
                    @if ($sel && $may)
                        <x-eo.button href="{{ route('invoices.edit', $sel) }}" class="w-full justify-center" size="sm">Open editor</x-eo.button>
                        <x-eo.button variant="ghost" href="{{ route('invoices.pdf', $sel) }}" class="w-full justify-center" size="sm">Download PDF</x-eo.button>

                        @if ($selState === 'draft')
                            <x-eo.button wire:click="markSent({{ $sel->id }})" class="w-full justify-center" size="sm">Mark sent</x-eo.button>
                            <button type="button" wire:click="destroyDraft({{ $sel->id }})" class="eo-btn-ghost eo-btn-sm w-full justify-center text-eo-risk">Delete draft</button>
                        @elseif ($selState === 'paid')
                            <button type="button" wire:click="clearPaid({{ $sel->id }})" class="eo-btn-ghost eo-btn-sm w-full justify-center">Undo payment</button>
                        @elseif ($selState !== 'void')
                            <label class="eo-label">Record receipt</label>
                            <input type="text" inputmode="decimal" wire:model="amount.{{ $sel->id }}"
                                   placeholder="{{ number_format($out / 100) }}" class="eo-input text-end text-xs">
                            <x-eo.button wire:click="record({{ $sel->id }}, $wire.amount[{{ $sel->id }}])" class="w-full justify-center" size="sm">Record payment</x-eo.button>
                            <button type="button" wire:click="void({{ $sel->id }})" class="eo-btn-ghost eo-btn-sm w-full justify-center text-eo-risk">Void</button>
                        @endif
                    @elseif ($sel)
                        <p class="text-[12px] text-eo-muted">View only.</p>
                        <x-eo.button variant="ghost" href="{{ route('invoices.edit', $sel) }}" class="w-full justify-center" size="sm">Open invoice</x-eo.button>
                    @else
                        <p class="text-[12px] text-eo-muted">Select an invoice from the queue.</p>
                    @endif
                </x-eo.action-panel>
            </div>
        </div>
    @endif
</div>
