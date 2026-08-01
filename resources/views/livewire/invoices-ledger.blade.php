@php
    use App\Models\Invoice;

    $may = auth()->user()?->can('manage-contract') ?? false;
    $states = ['draft', 'sent', 'overdue', 'partial', 'paid', 'void'];
@endphp

<div class="space-y-4">

    {{-- ══ the bar ══ --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-navy-300" />
            <input type="search" wire:model.live.debounce.300ms="q" placeholder="Number, client, event, agreement…"
                   class="input h-10 w-60 !rounded-2xl !py-0 !ps-9 text-xs xl:w-80">
        </div>

        <p class="text-[11.5px] text-muted">{{ $rows->count() }} {{ str('invoice')->plural($rows->count()) }} in view</p>

        @if ($may)
            {{-- Raising from a schedule covers the contracted work; a one-off,
                 a rebilled expense or a retainer had nowhere to start. --}}
            <button type="button" wire:click="create"
                    class="flex h-10 items-center gap-1.5 rounded-2xl bg-navy-950 px-4 text-[12px] font-bold text-white shadow-[0_10px_24px_-14px_rgba(11,31,58,0.9)] transition hover:bg-navy-800">
                ＋ New invoice
            </button>
        @endif

        <div class="ms-auto flex flex-wrap items-center gap-1">
            <button type="button" wire:click="setState('all')"
                    @class(['rounded-full px-2.5 py-1 text-[11px] font-bold transition',
                        'bg-navy-950 text-white' => $state === 'all',
                        'text-navy-500 hover:bg-white hover:text-navy-900' => $state !== 'all'])>All</button>

            @foreach ($states as $key)
                @php [$label, $hex] = Invoice::STATE_META[$key]; @endphp
                <button type="button" wire:click="setState('{{ $key }}')"
                        @class(['flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold transition',
                            'bg-navy-950 text-white' => $state === $key,
                            'text-navy-500 hover:bg-white hover:text-navy-900' => $state !== $key])>
                    <span class="h-1.5 w-1.5 rounded-full" style="background: {{ $hex }}"></span>{{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <x-figure-strip :figures="$figures" dense />

    {{-- ══ WHAT HAS NOT BEEN RAISED ══
         The page opens with this rather than with an empty table and a New
         button: every one of these is money the book has agreed to ask for and
         has not asked for yet. ══ --}}
    @if ($ready->isNotEmpty())
        <div class="overflow-hidden rounded-2xl border border-violet-200 bg-violet-50/40 shadow-sm">
            <button type="button" wire:click="toggleReady"
                    class="flex w-full items-center gap-3 px-4 py-2.5 text-start transition hover:bg-violet-50">
                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-violet-500 text-white">
                    <x-icon name="sparkles" class="h-4 w-4" />
                </span>
                <span class="min-w-0">
                    <span class="block text-[13px] font-bold text-navy-900">
                        {{ $ready->count() }} {{ str('installment')->plural($ready->count()) }} ready to invoice
                    </span>
                    <span class="block text-[11px] text-muted">Agreed in a contract, never billed</span>
                </span>
                <x-icon name="chevron" class="ms-auto h-4 w-4 shrink-0 text-navy-400 transition {{ $showReady ? 'rotate-180' : '' }}" />
            </button>

            @if ($showReady)
                <div class="border-t border-violet-200/70 bg-white">
                    @foreach ($ready as $p)
                        <div wire:key="ready-{{ $p->id }}"
                             class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-line/50 px-4 py-2 last:border-0">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[12.5px] font-bold text-navy-900">{{ $p->label }}</span>
                                <span class="block truncate text-[11px] text-muted">
                                    {{ $p->event?->name }} · {{ $p->contract?->reference ?: 'No reference' }}
                                </span>
                            </span>

                            <span @class(['shrink-0 text-[11.5px] font-semibold tabular-nums',
                                'text-red-600' => $p->due_on?->isPast(), 'text-navy-500' => ! $p->due_on?->isPast()])>
                                {{ $p->due_on?->format('j M Y') ?? 'No date' }}
                            </span>

                            <span class="pf shrink-0 text-[13px] font-black tabular-nums text-navy-900">
                                JD{{ number_format($p->amount_cents / 100) }}
                            </span>

                            @if ($may)
                                <button type="button" wire:click="raise({{ $p->id }})"
                                        class="shrink-0 rounded-lg bg-violet-600 px-3 py-1.5 text-[11px] font-bold text-white transition hover:bg-violet-700">
                                    Raise invoice
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- ══ THE LEDGER ══ --}}
    @if ($rows->isEmpty())
        <x-empty icon="document" title="No invoice yet"
                 hint="Raise one from an installment above, or clear the filters if you were expecting to see something." />
    @else
        <div class="overflow-hidden rounded-2xl border border-line bg-white shadow-sm">
            <div class="overflow-x-auto">
                <div class="min-w-[1040px]">
                    @php $cols = 'grid-cols-[150px_1fr_100px_100px_110px_110px_106px_180px]'; @endphp

                    <div class="grid {{ $cols }} gap-3 border-b border-line bg-navy-50/50 px-4 py-2 text-eyebrow font-bold uppercase tracking-wide text-navy-400">
                        <span>Number</span><span>Billed to</span><span>Issued</span><span>Due</span>
                        <span class="text-end">Total</span><span class="text-end">Received</span>
                        <span>State</span><span class="text-end">Action</span>
                    </div>

                    @foreach ($rows as $inv)
                        @php
                            $s = $inv->state();
                            $out = $inv->outstandingCents();
                        @endphp

                        <div wire:key="inv-{{ $inv->id }}"
                             class="grid {{ $cols }} items-center gap-3 border-b border-line/50 px-4 py-2 transition last:border-0 hover:bg-navy-50/30 {{ $s === 'void' ? 'opacity-55' : '' }}">

                            <span class="flex min-w-0 items-center gap-1.5">
                                <a href="{{ route('invoices.edit', $inv) }}" wire:navigate
                                   class="truncate font-mono text-[11px] font-bold text-navy-700 transition hover:text-indigo-600"
                                   title="Open the invoice">{{ $inv->number }}</a>
                                <a href="{{ route('invoices.pdf', $inv) }}" title="Download the PDF"
                                   class="shrink-0 text-navy-300 transition hover:text-indigo-600">
                                    <x-icon name="document" class="h-3.5 w-3.5" />
                                </a>
                            </span>

                            <span class="min-w-0">
                                <span class="block truncate text-[12.5px] font-bold text-navy-900">
                                    {{ $inv->bill_to ?: ($inv->client?->name ?: $inv->event?->client?->name ?: 'No client') }}
                                </span>
                                <span class="block truncate text-[10.5px] text-muted">
                                    {{ $inv->event?->name ?? 'No event' }}
                                    @if ($inv->tax_pct) · {{ rtrim(rtrim(number_format($inv->tax_pct, 1), '0'), '.') }}% tax @endif
                                </span>
                            </span>

                            <span class="text-[11.5px] tabular-nums text-navy-500">{{ $inv->issued_on?->format('j M y') ?? '—' }}</span>

                            <span @class(['text-[11.5px] font-semibold tabular-nums',
                                'text-red-600' => $s === 'overdue', 'text-navy-500' => $s !== 'overdue'])>
                                {{ $inv->due_on?->format('j M y') ?? '—' }}
                            </span>

                            <span class="pf text-end text-[13px] font-black tabular-nums text-navy-900">
                                {{ number_format($inv->totalCents() / 100) }}
                            </span>

                            <span @class(['text-end text-[12px] font-bold tabular-nums',
                                'text-emerald-700' => $inv->paid_cents > 0, 'text-navy-300' => $inv->paid_cents === 0])>
                                {{ number_format($inv->paid_cents / 100) }}
                            </span>

                            <span>
                                <span class="rounded-full px-2 py-0.5 text-[10.5px] font-bold"
                                      style="color: {{ $inv->stateHex() }}; background: {{ $inv->stateHex() }}1a"
                                      @if ($out > 0) title="JD{{ number_format($out / 100) }} outstanding" @endif>{{ $inv->stateLabel() }}</span>
                            </span>

                            {{-- What the document needs next, and nothing else:
                                 a draft needs sending, a sent one needs paying,
                                 a paid one needs leaving alone. --}}
                            <span class="flex items-center justify-end gap-1.5">
                                @if (! $may)
                                    <span class="text-[10.5px] italic text-navy-300">View only</span>
                                @elseif ($s === 'void')
                                    <span class="text-[10.5px] italic text-navy-300">Voided</span>
                                @elseif ($s === 'draft')
                                    <a href="{{ route('invoices.edit', $inv) }}" wire:navigate
                                       class="rounded-lg px-2 py-1 text-[10.5px] font-bold text-navy-500 transition hover:bg-navy-50 hover:text-navy-900">
                                        Edit
                                    </a>
                                    <button type="button" wire:click="markSent({{ $inv->id }})"
                                            class="rounded-lg bg-navy-950 px-2.5 py-1 text-[10.5px] font-bold text-white transition hover:bg-navy-800">
                                        Mark sent
                                    </button>
                                    <button type="button" wire:click="destroyDraft({{ $inv->id }})"
                                            class="rounded-lg px-2 py-1 text-[10.5px] font-bold text-navy-400 transition hover:bg-red-50 hover:text-red-600">
                                        Delete
                                    </button>
                                @elseif ($s === 'paid')
                                    <button type="button" wire:click="clearPaid({{ $inv->id }})"
                                            class="rounded-lg px-2 py-1 text-[10.5px] font-bold text-navy-400 transition hover:bg-navy-50 hover:text-navy-700">
                                        Undo
                                    </button>
                                @else
                                    <input type="text" inputmode="decimal" wire:model="amount.{{ $inv->id }}"
                                           placeholder="{{ number_format($out / 100) }}"
                                           class="input h-7 w-[68px] !rounded-lg !px-2 !py-0 text-end text-[11px]">
                                    <button type="button" wire:click="record({{ $inv->id }}, $wire.amount[{{ $inv->id }}])"
                                            class="rounded-lg bg-navy-950 px-2.5 py-1 text-[10.5px] font-bold text-white transition hover:bg-navy-800">
                                        Record
                                    </button>
                                    <button type="button" wire:click="void({{ $inv->id }})" title="Void this invoice"
                                            class="rounded-lg px-1.5 py-1 text-[10.5px] font-bold text-navy-300 transition hover:bg-navy-50 hover:text-navy-600">
                                        ⊘
                                    </button>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
