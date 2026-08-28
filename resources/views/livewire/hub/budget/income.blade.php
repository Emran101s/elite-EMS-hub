            {{-- ══════════ INCOME — where the money comes from ══════════

                 The old table was one grid doing three unrelated jobs: a
                 rolled-up figure the contract and invoices own, two streams
                 another tab owns, and hand-logged lines this tab owns. They
                 looked identical, so the only way to know which number you
                 could edit — or where a figure came from — was to click it.

                 Every row now says where it is recorded. That is the question
                 the old run-on subtitle ("X via contract · Y via invoices · Z
                 logged here") was answering badly, and it is the difference
                 between a number you trust and a number you query. --}}
            @php
                $tTarget = 'w-24 shrink-0 text-right';
                $tActual = 'w-24 shrink-0 text-right';
                $tHome   = 'hidden w-36 shrink-0 sm:block';
                $pct = fn ($actual, $target) => $target > 0 ? min(100, (int) round($actual / $target * 100)) : null;
            @endphp
            <div class="cx-lcard">
                <div class="cx-lhero">
                    <div class="cx-lhero-row">
                        <div>
                            <span class="cx-lhero-k"><span class="cx-hexdot"></span>Income</span>
                            <p class="cx-lhero-v">{{ $fmt($totalIncome) }}</p>
                            <p class="cx-lhero-sub">received of {{ $fmt($totalTargetIncome) }} expected</p>
                        </div>
                    </div>
                </div>

                <div class="cx-lcolhead">
                    <span class="flex-1">Source</span>
                    <span class="{{ $tHome }}">Recorded in</span>
                    <span class="{{ $tTarget }}">Expected</span>
                    <span class="{{ $tActual }}">Received</span>
                </div>

                {{-- ── Client / Main Fund ──
                     Its money lands in up to three places. Rather than a
                     sentence, the breakdown is a line of its own under the
                     row — present only when there is something to break down. --}}
                @php $clientPct = $pct($clientActual, (int) $clientTarget * 100); @endphp
                <div class="border-b border-line px-3.5 py-2.5">
                    <div class="flex items-center gap-2 text-xs">
                        <div class="min-w-0 flex-1">
                            <p class="cx-lname">Client / Main Fund</p>
                            <p class="cx-lsub">The primary fee for delivering this event</p>
                        </div>
                        <span class="{{ $tHome }} text-[10.5px] text-muted">Contract &amp; invoices</span>
                        <div class="{{ $tTarget }}">
                            <span class="inline-flex items-center gap-1 rounded-lg border border-line bg-white px-1.5 py-0.5">
                                <span class="text-[10px] text-muted">{{ $event->currencySymbol() }}</span>
                                <input type="number" min="0" step="1000" wire:model.live.debounce.600ms="clientTarget"
                                       placeholder="0" aria-label="Expected client income"
                                       class="w-16 bg-transparent text-right text-[11.5px] font-semibold tabular-nums text-ink focus:outline-none">
                            </span>
                        </div>
                        <span class="{{ $tActual }} font-bold tabular-nums {{ $clientActual ? 'text-success-ink' : 'text-muted' }}">{{ $clientActual ? $fmt($clientActual) : '—' }}</span>
                    </div>

                    @if ($clientPct !== null)
                        <div class="cx-bar mt-1.5">
                            <span class="{{ $clientPct >= 100 ? 'tone-ok' : '' }}" style="width: {{ $clientPct }}%; {{ $clientPct < 100 ? 'background: var(--cx-accent)' : '' }}"></span>
                        </div>
                    @endif

                    {{-- The gap, where the number is set rather than only in the
                         control panel afterwards. If the budget charges more
                         than income expects to collect, this is the field that
                         decides it — so it says so here, at the moment you type
                         the figure, not three columns away once you are done. --}}
                    @php $chargeGap = $grandForecast - $totalTargetIncome; @endphp
                    @if ($chargeGap > 0 && $grandForecast > 0)
                        <p class="mt-1.5 rounded-lg px-2 py-1.5 text-[10.5px] leading-snug"
                           style="background: var(--cx-warn-wash); color: var(--cx-warn-ink)">
                            The budget charges {{ $fmt($grandForecast) }} — expected income is
                            {{ $fmt(abs($chargeGap)) }} short of that. Either this target is low,
                            or the management fee is not being billed.
                        </p>
                    @endif

                    {{-- Where it actually came from, each part linked to the place that recorded it. --}}
                    <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[10.5px]">
                        @if ($contractCollected > 0)
                            <a href="{{ route('events.hub', [$event, 'tab' => 'contract']) }}" class="font-semibold" style="color: var(--cx-accent-ink)">{{ $fmt($contractCollected) }} via contract{{ $contractRef ? ' '.$contractRef : '' }}</a>
                        @endif
                        @if ($clientMoney['invoices'] > 0)
                            <a href="{{ route('invoices.index') }}" class="font-semibold" style="color: var(--cx-accent-ink)">{{ $fmt($clientMoney['invoices']) }} via invoices</a>
                        @endif
                        @if ($clientMoney['manual'] > 0)
                            <span class="text-muted">{{ $fmt($clientMoney['manual']) }} logged here</span>
                        @endif
                        @if ($clientMoney['collected'] <= 0)
                            <span class="text-muted">Nothing received yet — it appears here when the contract is paid or an invoice settles.</span>
                        @endif
                        <button type="button" wire:click="newIncome('client')" class="font-semibold text-muted transition hover:text-ink">＋ Log a payment</button>
                    </div>
                </div>

                {{-- ── Streams another tab owns ──
                     Not "extra income": for most events these are the second
                     and third largest lines on the page. What actually sets
                     them apart is that you do not edit the received figure
                     here — you sell a package or a booth, and it lands. --}}
                @foreach ([
                    ['label' => 'Sponsorships', 'tab' => 'sponsors', 'model' => 'sponsorshipTarget',
                     'count' => $sponsorsCount, 'noun' => 'package', 'actual' => $sponsorsIncome, 'target' => $sponsorshipTarget],
                    ['label' => 'Exhibition / Booths', 'tab' => 'exhibition', 'model' => 'exhibitionTarget',
                     'count' => $exhibitorsCount, 'noun' => 'booth', 'actual' => $exhibitorsIncome, 'target' => $exhibitionTarget],
                ] as $stream)
                    @php $sPct = $pct($stream['actual'], (int) $stream['target'] * 100); @endphp
                    <div class="border-b border-line px-3.5 py-2.5">
                        <div class="flex items-center gap-2 text-xs">
                            <a href="{{ route('events.hub', [$event, 'tab' => $stream['tab']]) }}" class="min-w-0 flex-1">
                                <p class="cx-lname hover:text-gold-800">{{ $stream['label'] }} →</p>
                                <p class="cx-lsub">{{ $stream['count'] }} {{ str($stream['noun'])->plural($stream['count']) }} sold</p>
                            </a>
                            <span class="{{ $tHome }} text-[10.5px] text-muted">{{ \App\Models\Event::moduleLabel($stream['tab']) }} tab</span>
                            <div class="{{ $tTarget }}">
                                <span class="inline-flex items-center gap-1 rounded-lg border border-line bg-white px-1.5 py-0.5">
                                    <span class="text-[10px] text-muted">{{ $event->currencySymbol() }}</span>
                                    <input type="number" min="0" step="1000" wire:model.live.debounce.600ms="{{ $stream['model'] }}"
                                           placeholder="0" aria-label="Expected {{ $stream['label'] }} income"
                                           class="w-16 bg-transparent text-right text-[11.5px] font-semibold tabular-nums text-ink focus:outline-none">
                                </span>
                            </div>
                            <span class="{{ $tActual }} font-bold tabular-nums {{ $stream['actual'] ? 'text-success-ink' : 'text-muted' }}">{{ $stream['actual'] ? $fmt($stream['actual']) : '—' }}</span>
                        </div>

                        @if ($sPct !== null)
                            <div class="cx-bar mt-1.5">
                                <span class="{{ $sPct >= 100 ? 'tone-ok' : '' }}" style="width: {{ $sPct }}%; {{ $sPct < 100 ? 'background: var(--cx-accent)' : '' }}"></span>
                            </div>
                        @endif
                    </div>
                @endforeach

                {{-- ── Lines logged here ──
                     These have no target because nobody forecasts a one-off
                     grant; they are recorded when they arrive. The old table
                     showed them an empty Target cell, which read as a figure
                     somebody had forgotten to fill in. --}}
                @if ($otherIncomeItems->isNotEmpty())
                    <div class="flex items-center gap-2 border-b border-line px-3.5 py-1" style="background: var(--cx-surface-2)">
                        <span class="flex-1 text-[10px] font-bold uppercase tracking-[0.16em] text-muted">Logged on this page</span>
                    </div>
                    @foreach ($otherIncomeItems as $inc)
                        <div wire:key="inc-{{ $inc->id }}" class="group/inc relative flex items-center gap-2 border-b border-line px-3.5 py-2 text-xs hover:bg-page/30">
                            <span class="min-w-0 flex-1 truncate text-ink">
                                <span class="font-semibold">{{ $inc->sourceLabel() }}</span>@if ($inc->description) <span class="text-muted">· {{ $inc->description }}</span>@endif
                                <span class="ms-1 rounded-full bg-page px-1.5 text-eyebrow font-bold uppercase text-muted">{{ $inc->status }}</span>
                            </span>
                            <span class="{{ $tHome }} text-[10.5px] text-muted">This page</span>
                            <span class="{{ $tTarget }} text-[11px] text-muted">not forecast</span>
                            <span class="{{ $tActual }} font-semibold tabular-nums text-success-ink">{{ $fmt($inc->amount_cents) }}</span>
                            <div class="absolute right-2 top-1/2 hidden -translate-y-1/2 items-center gap-1 rounded-lg border border-line bg-white px-1 py-0.5 shadow-sm group-hover/inc:flex">
                                <button type="button" wire:click="editIncome({{ $inc->id }})" class="rounded bg-page px-1.5 py-0.5 text-eyebrow font-bold text-muted hover:bg-line">✎</button>
                                <x-confirm title="Delete this income line?"
                                           confirm="Delete"
                                           run="$wire.deleteIncome({{ $inc->id }})"
                                           class="rounded bg-danger-soft px-1.5 py-0.5 text-eyebrow font-bold text-danger hover:bg-danger/20">✕</x-confirm>
                            </div>
                        </div>
                    @endforeach
                @endif

                <div class="flex flex-wrap items-center gap-1.5 border-t border-line px-3.5 py-2" style="background: var(--cx-surface-2)">
                    <span class="text-eyebrow font-semibold uppercase tracking-wide text-muted">Log income:</span>
                    @foreach (\App\Support\Taxonomy::options('income_source') as $key => $label)
                        @continue ($key === 'client')
                        <button type="button" wire:click="newIncome('{{ $key }}')" class="cx-chip">＋ {{ $label }}</button>
                    @endforeach
                </div>

                <div class="flex items-center gap-2 border-t border-line px-3.5 py-2.5 text-xs font-bold" style="background: var(--cx-ok-wash)">
                    <span class="flex-1 text-ink">Total income</span>
                    <span class="{{ $tHome }}"></span>
                    <span class="{{ $tTarget }} tabular-nums text-muted">{{ $fmt($totalTargetIncome) }}</span>
                    <span class="{{ $tActual }} tabular-nums text-success-ink">{{ $fmt($totalIncome) }}</span>
                </div>
            </div>
