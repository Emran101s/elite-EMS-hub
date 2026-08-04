@php
    use App\Models\Proposal;

    $may = auth()->user()?->can('manage-contract') ?? false;
    $states = ['draft', 'sent', 'expired', 'accepted', 'declined'];
@endphp

<div class="space-y-4">

    {{-- ══ the bar ══ --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-navy-300" />
            <input type="search" wire:model.live.debounce.300ms="q" placeholder="Number, title, client, contact…"
                   class="input h-10 w-60 !rounded-2xl !py-0 !ps-9 text-xs xl:w-80">
        </div>

        <p class="text-[11.5px] text-muted">{{ $rows->count() }} {{ str('offer')->plural($rows->count()) }} in view</p>

        @if ($may)
            {{-- Most offers come from the pipeline; not all of them do. --}}
            <button type="button" wire:click="create"
                    class="flex h-10 items-center rounded-2xl bg-navy-950 px-4 text-[12px] font-bold text-white shadow-[0_10px_24px_-14px_rgba(11,31,58,0.9)] transition hover:bg-navy-800">
                ＋ New proposal
            </button>
        @endif

        <div class="ms-auto flex flex-wrap items-center gap-1">
            <button type="button" wire:click="setState('all')"
                    @class(['rounded-full px-2.5 py-1 text-[11px] font-bold transition',
                        'bg-navy-950 text-white' => $state === 'all',
                        'text-navy-500 hover:bg-white hover:text-navy-900' => $state !== 'all'])>All</button>

            @foreach ($states as $key)
                @php [$label, $hex] = Proposal::STATE_META[$key]; @endphp
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

    {{-- ══ DEALS WAITING FOR AN OFFER ══
         The pipeline said an offer had been made; no offer existed. These are
         the deals that reached that stage with nothing behind it. ══ --}}
    @if ($ready->isNotEmpty())
        <div class="overflow-hidden rounded-2xl border border-gold-300 bg-gold-50/40 shadow-sm">
            <button type="button" wire:click="toggleReady"
                    class="flex w-full items-center gap-3 px-4 py-2.5 text-start transition hover:bg-gold-50">
                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-gold-500 text-navy-950">
                    <x-icon name="document" class="h-4 w-4" />
                </span>
                <span class="min-w-0">
                    <span class="block text-[13px] font-bold text-navy-900">
                        {{ $ready->count() }} {{ str('deal')->plural($ready->count()) }} waiting for an offer
                    </span>
                    <span class="block text-[11px] text-muted">At proposal or negotiation, with nothing sent</span>
                </span>
                <x-icon name="chevron" class="ms-auto h-4 w-4 shrink-0 text-navy-400 transition {{ $showReady ? 'rotate-180' : '' }}" />
            </button>

            @if ($showReady)
                <div class="border-t border-gold-200/70 bg-white">
                    @foreach ($ready as $deal)
                        <div wire:key="deal-{{ $deal->id }}"
                             class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-line/50 px-4 py-2 last:border-0">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[12.5px] font-bold text-navy-900">{{ $deal->title }}</span>
                                <span class="block truncate text-[11px] text-muted">
                                    {{ $deal->client?->name ?? 'No client' }} · {{ \App\Models\Deal::STAGES[$deal->stage][0] ?? $deal->stage }}
                                </span>
                            </span>

                            @if ($deal->expected_close_on)
                                <span class="shrink-0 text-[11.5px] font-semibold tabular-nums {{ $deal->expected_close_on->isPast() ? 'text-red-600' : 'text-navy-500' }}">
                                    {{ $deal->expected_close_on->format('j M Y') }}
                                </span>
                            @endif

                            <span class="pf shrink-0 text-[13px] font-black tabular-nums text-navy-900">
                                JD{{ number_format($deal->value_cents / 100) }}
                            </span>

                            @if ($may)
                                <button type="button" wire:click="draftFor({{ $deal->id }})"
                                        class="shrink-0 rounded-lg bg-gold-500 px-3 py-1.5 text-[11px] font-bold text-navy-950 transition hover:bg-gold-400">
                                    Draft an offer
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- ══ THE OFFERS ══ --}}
    @if ($rows->isEmpty())
        <x-empty icon="document" title="No offer yet"
                 hint="Start one with New proposal, draft one from a deal above, or clear the filters if you were expecting to see something." />
    @else
        <div class="overflow-hidden rounded-2xl border border-line bg-white shadow-sm">
            <div class="overflow-x-auto">
                <div class="min-w-[1060px]">
                    @php $cols = 'grid-cols-[152px_1fr_150px_104px_118px_100px_200px]'; @endphp

                    <div class="grid {{ $cols }} gap-3 border-b border-line bg-navy-50/50 px-4 py-2 text-eyebrow font-bold uppercase tracking-wide text-navy-400">
                        <span>Number</span><span>Offer</span><span>Client</span><span>Valid</span>
                        <span class="text-end">Total</span><span>State</span><span class="text-end">Action</span>
                    </div>

                    @foreach ($rows as $p)
                        @php
                            $s = $p->state();
                            $days = $p->daysLeft();
                        @endphp

                        <div wire:key="prop-{{ $p->id }}"
                             class="grid {{ $cols }} items-center gap-3 border-b border-line/50 px-4 py-2 transition last:border-0 hover:bg-navy-50/30 {{ $s === 'declined' ? 'opacity-60' : '' }}">

                            <span class="flex min-w-0 items-center gap-1.5">
                                <a href="{{ route('proposals.edit', $p) }}" wire:navigate title="Open the offer"
                                   class="truncate font-mono text-[11px] font-bold text-navy-700 transition hover:text-gold-700">{{ $p->number }}</a>
                                <a href="{{ route('proposals.pdf', $p) }}" title="Download the PDF"
                                   class="shrink-0 text-navy-300 transition hover:text-gold-700">
                                    <x-icon name="document" class="h-3.5 w-3.5" />
                                </a>
                            </span>

                            <span class="min-w-0">
                                <span class="block truncate text-[12.5px] font-bold text-navy-900">{{ $p->title }}</span>
                                <span class="block truncate text-[10.5px] text-muted">
                                    {{ $p->lines->count() }} {{ str('line')->plural($p->lines->count()) }}
                                    @if ($p->optionalCents()) · JD{{ number_format($p->optionalCents() / 100) }} optional @endif
                                    @if ($p->tax_pct) · {{ rtrim(rtrim(number_format($p->tax_pct, 1), '0'), '.') }}% tax @endif
                                </span>
                            </span>

                            <span class="min-w-0">
                                <span class="block truncate text-[12px] font-semibold text-navy-700">{{ $p->client?->name ?? '—' }}</span>
                                <span class="block truncate text-[10.5px] text-muted">{{ $p->contact?->name ?? ($p->owner?->name ?? '') }}</span>
                            </span>

                            {{-- The date matters less than what is left of it. --}}
                            <span class="text-[11.5px] tabular-nums">
                                @if ($p->valid_until)
                                    <span @class(['font-semibold',
                                        'text-red-600' => $days !== null && $days < 0,
                                        'text-amber-700' => $days !== null && $days >= 0 && $days <= 7,
                                        'text-navy-600' => $days !== null && $days > 7])>
                                        {{ $days < 0 ? abs($days).'d ago' : $days.'d left' }}
                                    </span>
                                @else
                                    <span class="italic text-navy-300">No date</span>
                                @endif
                            </span>

                            <span class="pf text-end text-[13px] font-black tabular-nums text-navy-900">
                                {{ number_format($p->totalCents() / 100) }}
                            </span>

                            <span>
                                <span class="rounded-full px-2 py-0.5 text-[10.5px] font-bold"
                                      style="color: {{ $p->stateHex() }}; background: {{ $p->stateHex() }}1a">{{ $p->stateLabel() }}</span>
                            </span>

                            {{-- What the offer needs next, and nothing else. --}}
                            <span class="flex items-center justify-end gap-1.5">
                                @if (! $may)
                                    <span class="text-[10.5px] italic text-navy-300">View only</span>

                                @elseif ($s === 'accepted')
                                    @if ($p->event)
                                        <a href="{{ route('events.hub', $p->event) }}"
                                           class="rounded-lg bg-emerald-50 px-2.5 py-1 text-[10.5px] font-bold text-emerald-700 transition hover:bg-emerald-100">
                                            Open the event →
                                        </a>
                                    @else
                                        <span class="text-[10.5px] italic text-navy-300">Accepted</span>
                                    @endif

                                @elseif ($s === 'declined')
                                    <span class="truncate text-[10.5px] italic text-navy-400"
                                          title="{{ $p->decline_reason }}">{{ $p->decline_reason ?: 'Declined' }}</span>

                                @elseif ($s === 'draft')
                                    <a href="{{ route('proposals.edit', $p) }}" wire:navigate
                                       class="rounded-lg px-2 py-1 text-[10.5px] font-bold text-navy-500 transition hover:bg-navy-50 hover:text-navy-900">
                                        Edit
                                    </a>
                                    <button type="button" wire:click="send({{ $p->id }})"
                                            class="rounded-lg bg-navy-950 px-2.5 py-1 text-[10.5px] font-bold text-white transition hover:bg-navy-800">
                                        Send
                                    </button>
                                    <button type="button" wire:click="destroyDraft({{ $p->id }})"
                                            class="rounded-lg px-2 py-1 text-[10.5px] font-bold text-navy-400 transition hover:bg-red-50 hover:text-red-600">
                                        Delete
                                    </button>

                                @elseif ($s === 'expired')
                                    <button type="button" wire:click="extend({{ $p->id }})"
                                            class="rounded-lg bg-amber-500 px-2.5 py-1 text-[10.5px] font-bold text-white transition hover:bg-amber-600">
                                        Extend 30d
                                    </button>
                                    <button type="button" wire:click="decline({{ $p->id }})"
                                            class="rounded-lg px-2 py-1 text-[10.5px] font-bold text-navy-400 transition hover:bg-navy-50 hover:text-navy-700">
                                        Close
                                    </button>

                                @else
                                    <button type="button" wire:click="accept({{ $p->id }})"
                                            title="Wins the deal and opens the event"
                                            class="rounded-lg bg-emerald-600 px-2.5 py-1 text-[10.5px] font-bold text-white transition hover:bg-emerald-700">
                                        Accepted
                                    </button>
                                    <input type="text" wire:model="reason.{{ $p->id }}" placeholder="Reason…"
                                           class="input h-7 w-[76px] !rounded-lg !px-2 !py-0 text-[10.5px]">
                                    <button type="button" wire:click="decline({{ $p->id }})"
                                            class="rounded-lg px-2 py-1 text-[10.5px] font-bold text-navy-400 transition hover:bg-red-50 hover:text-red-600">
                                        Lost
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
