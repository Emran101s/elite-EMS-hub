@php
    use App\Models\EventContractPayment;

    $may = auth()->user()?->can('manage-contract') ?? false;
@endphp

<div class="space-y-4">

    {{-- ══ the bar ══ --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-navy-300" />
            <input type="search" wire:model.live.debounce.300ms="q" placeholder="Installment, event, client, reference…"
                   class="input h-10 w-60 !rounded-2xl !py-0 !ps-9 text-xs xl:w-80">
        </div>

        <p class="text-[11.5px] text-muted">{{ $rows->count() }} {{ str('installment')->plural($rows->count()) }} in view</p>

        {{-- The status vocabulary is the model's, and so are the colours: an
             installment's state is derived from money and dates, never stored,
             so there is exactly one place it can be named. --}}
        <div class="ms-auto flex flex-wrap items-center gap-1">
            <button type="button" wire:click="setStatus('all')"
                    @class(['rounded-full px-2.5 py-1 text-[11px] font-bold transition',
                        'bg-navy-950 text-white' => $status === 'all',
                        'text-navy-500 hover:bg-white hover:text-navy-900' => $status !== 'all'])>All</button>

            @foreach (EventContractPayment::STATUS_META as $key => [$label, $hex])
                <button type="button" wire:click="setStatus('{{ $key }}')"
                        @class(['flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold transition',
                            'bg-navy-950 text-white' => $status === $key,
                            'text-navy-500 hover:bg-white hover:text-navy-900' => $status !== $key])>
                    <span class="h-1.5 w-1.5 rounded-full" style="background: {{ $hex }}"></span>{{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <x-figure-strip :figures="$figures" dense />

    @if ($rows->isEmpty())
        <x-empty icon="currency" title="No installment matches"
                 hint="Clear the filters, or open an event's Contract tab to build a payment schedule." />
    @else
        <div class="space-y-3">
            @foreach ($months as $month)
                <div class="overflow-hidden rounded-2xl border border-line bg-white shadow-sm">

                    {{-- The month is the unit collection actually happens in,
                         so it carries its own subtotal. --}}
                    <div class="flex items-center gap-3 border-b border-line bg-navy-50/50 px-4 py-2">
                        <span class="text-[12px] font-black uppercase tracking-[0.14em] text-navy-700">{{ $month['label'] }}</span>
                        <span class="text-[11px] font-semibold text-navy-300">{{ $month['rows']->count() }}</span>
                        @if ($month['settled'])
                            <span class="ms-auto text-[11px] font-bold text-emerald-700">Settled</span>
                        @else
                            <span class="ms-auto pf text-[13px] font-black tabular-nums text-navy-900">
                                JD{{ number_format($month['due'] / 100) }}
                                <span class="text-eyebrow font-bold uppercase tracking-wide text-navy-400">still due</span>
                            </span>
                        @endif
                    </div>

                    <div class="overflow-x-auto">
                        <div class="min-w-[900px]">
                            @php $cols = 'grid-cols-[104px_1fr_190px_112px_112px_100px_150px]'; @endphp

                            <div class="grid {{ $cols }} gap-3 border-b border-line/70 px-4 py-1.5 text-eyebrow font-bold uppercase tracking-wide text-navy-400">
                                <span>Due</span><span>Installment</span><span>Event</span>
                                <span class="text-end">Amount</span><span class="text-end">Received</span>
                                <span>Status</span><span class="text-end">Record</span>
                            </div>

                            @foreach ($month['rows'] as $p)
                                @php
                                    $state = $p->status();
                                    [$label, $hex] = EventContractPayment::STATUS_META[$state];
                                    $out = $p->outstandingCents();
                                @endphp

                                <div wire:key="pay-{{ $p->id }}"
                                     class="grid {{ $cols }} items-center gap-3 border-b border-line/50 px-4 py-2 transition last:border-0 hover:bg-navy-50/30">

                                    <span @class(['text-[11.5px] font-semibold tabular-nums',
                                        'text-red-600' => $state === 'overdue',
                                        'text-navy-600' => $state !== 'overdue' && $p->due_on,
                                        'italic text-navy-300' => ! $p->due_on])>
                                        {{ $p->due_on?->format('j M Y') ?? 'No date' }}
                                    </span>

                                    <span class="min-w-0">
                                        <span class="block truncate text-[12.5px] font-bold text-navy-900">{{ $p->label }}</span>
                                        <span class="block truncate text-[10.5px] text-muted">
                                            {{ $p->contract?->reference ?: $p->contract?->displayTitle() }}
                                            @if ($p->pct) · {{ rtrim(rtrim(number_format($p->pct, 1), '0'), '.') }}% @endif
                                        </span>
                                    </span>

                                    <a href="{{ $p->event ? route('events.hub', [$p->event, 'tab' => 'contract']) : '#' }}"
                                       class="min-w-0 transition hover:text-indigo-600">
                                        <span class="block truncate text-[12px] font-semibold text-navy-700">{{ $p->event?->name ?? '—' }}</span>
                                        <span class="block truncate text-[10.5px] text-muted">{{ $p->event?->client?->name ?? 'No client' }}</span>
                                    </a>

                                    <span class="pf text-end text-[13px] font-black tabular-nums text-navy-900">
                                        {{ number_format($p->amount_cents / 100) }}
                                    </span>

                                    <span @class(['text-end text-[12px] font-bold tabular-nums',
                                        'text-emerald-700' => $p->paid_cents > 0, 'text-navy-300' => $p->paid_cents === 0])>
                                        {{ number_format($p->paid_cents / 100) }}
                                    </span>

                                    <span>
                                        <span class="rounded-full px-2 py-0.5 text-[10.5px] font-bold"
                                              style="color: {{ $hex }}; background: {{ $hex }}1a"
                                              @if ($out > 0) title="JD{{ number_format($out / 100) }} outstanding" @endif>{{ $label }}</span>
                                    </span>

                                    {{-- Blank settles in full, which is what the
                                         Contract tab does and what recording a
                                         payment usually means. --}}
                                    <span class="flex items-center justify-end gap-1.5">
                                        @php $inv = $p->invoice(); @endphp

                                        @if ($inv)
                                            {{-- An invoice is asking for this one, so the
                                                 invoice is where the money is recorded and
                                                 this row only mirrors it. Offering a second
                                                 Record button here is how a ledger comes to
                                                 count one payment twice. --}}
                                            <a href="{{ route('invoices.index', ['q' => $inv->number]) }}"
                                               title="Recorded against {{ $inv->number }}"
                                               class="flex items-center gap-1.5 rounded-lg bg-navy-50 px-2 py-1 font-mono text-[10px] font-bold text-navy-600 transition hover:bg-navy-100 hover:text-indigo-600">
                                                <span class="h-1.5 w-1.5 rounded-full" style="background: {{ $inv->stateHex() }}"></span>
                                                {{ $inv->number }}
                                            </a>
                                        @elseif (! $may)
                                            <span class="text-[10.5px] italic text-navy-300">View only</span>
                                        @elseif ($state === 'paid')
                                            <button type="button" wire:click="clear({{ $p->id }})"
                                                    class="rounded-lg px-2 py-1 text-[10.5px] font-bold text-navy-400 transition hover:bg-navy-50 hover:text-navy-700">
                                                Undo
                                            </button>
                                        @else
                                            <input type="text" inputmode="decimal"
                                                   wire:model="amount.{{ $p->id }}"
                                                   placeholder="{{ number_format($out / 100) }}"
                                                   class="input h-7 w-[70px] !rounded-lg !px-2 !py-0 text-end text-[11px]">
                                            <button type="button" wire:click="record({{ $p->id }}, $wire.amount[{{ $p->id }}])"
                                                    class="rounded-lg bg-navy-950 px-2.5 py-1 text-[10.5px] font-bold text-white transition hover:bg-navy-800">
                                                Record
                                            </button>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
