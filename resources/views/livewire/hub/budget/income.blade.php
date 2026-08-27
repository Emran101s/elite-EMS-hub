            {{-- income (money in) — three streams, target vs actual --}}
            @php $tin = 'w-20 shrink-0 text-right'; @endphp
            <div class="rounded-lg border border-line bg-white mb-3 overflow-hidden">
                <div class="flex items-center justify-between border-b border-line bg-success-soft/50 px-3 py-1.5">
                    <span class="flex items-center gap-1.5 text-xs font-bold text-ink"><span class="text-success-ink">▲</span> Income</span>
                    <div class="text-right leading-tight">
                        <span class="text-sm font-bold text-success-ink">{{ $fmt($totalIncome) }}</span>
                        <span class="block text-eyebrow text-muted">actual · target {{ $fmt($totalTargetIncome) }}</span>
                    </div>
                </div>

                {{-- column header --}}
                <div class="flex items-center gap-2 border-b border-line bg-page/30 px-3 py-1 text-eyebrow font-bold uppercase tracking-wide text-muted">
                    <span class="flex-1">Source</span>
                    <span class="{{ $tin }}">Target</span>
                    <span class="{{ $tin }}">Actual</span>
                </div>

                {{-- Client / Main Fund (primary) --}}
                <div class="flex items-center gap-2 border-b border-line px-3 py-1.5 text-xs">
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-ink">Client / Main Fund</p>
                        {{-- Where the money was recorded, each part linked to the
                             place that recorded it. One figure with three possible
                             homes is a figure people query; this answers it. --}}
                        @if ($clientMoney['collected'] > 0)
                            <p class="text-eyebrow text-muted">
                                @if ($contractCollected > 0)
                                    <a href="{{ route('events.hub', [$event, 'tab' => 'contract']) }}" class="font-bold text-gold-700 hover:text-gold-800">{{ $fmt($contractCollected) }} via contract{{ $contractRef ? ' '.$contractRef : '' }}</a>
                                @endif
                                @if ($clientMoney['invoices'] > 0)
                                    @if ($contractCollected > 0) · @endif
                                    <a href="{{ route('invoices.index') }}" class="font-bold text-gold-700 hover:text-gold-800">{{ $fmt($clientMoney['invoices']) }} via invoices</a>
                                @endif
                                @if ($clientMoney['manual'] > 0)
                                    @if ($contractCollected > 0 || $clientMoney['invoices'] > 0) · @endif
                                    {{ $fmt($clientMoney['manual']) }} logged here
                                @endif
                                · <button type="button" wire:click="newIncome('client')" class="font-semibold text-muted hover:text-ink">＋ extra</button>
                            </p>
                        @else
                            <p class="text-eyebrow text-muted">Primary income · <button type="button" wire:click="newIncome('client')" class="font-bold text-gold-700 hover:text-gold-800">＋ log payment</button></p>
                        @endif
                    </div>
                    <div class="{{ $tin }}">
                        <span class="inline-flex items-center gap-0.5 rounded-md border border-line bg-white px-1">
                            <span class="text-eyebrow text-muted">{{ $event->currencySymbol() }}</span>
                            <input type="number" min="0" step="1000" wire:model.live.debounce.600ms="clientTarget" placeholder="0" class="w-12 bg-transparent text-right text-micro font-semibold text-ink focus:outline-none">
                        </span>
                    </div>
                    <span class="{{ $tin }} font-bold text-success-ink">{{ $clientActual ? $fmt($clientActual) : '—' }}</span>
                </div>

                {{-- Extra income --}}
                <div class="bg-ink/[0.03] px-3 py-1 text-eyebrow font-bold uppercase tracking-[0.16em] text-muted">Extra income</div>

                {{-- Sponsorships --}}
                <div class="flex items-center gap-2 border-b border-line px-3 py-1.5 text-xs">
                    <a href="{{ route('events.hub', [$event, 'tab' => 'sponsors']) }}" class="min-w-0 flex-1">
                        <p class="font-bold text-ink hover:text-gold-800">Sponsorships →</p>
                        <p class="text-eyebrow text-muted">{{ $sponsorsCount }} sold · {{ $sponsorsReceived ? $fmt($sponsorsReceived).' received' : 'sell packages' }}</p>
                    </a>
                    <div class="{{ $tin }}">
                        <span class="inline-flex items-center gap-0.5 rounded-md border border-line bg-white px-1">
                            <span class="text-eyebrow text-muted">{{ $event->currencySymbol() }}</span>
                            <input type="number" min="0" step="1000" wire:model.live.debounce.600ms="sponsorshipTarget" placeholder="0" class="w-12 bg-transparent text-right text-micro font-semibold text-ink focus:outline-none">
                        </span>
                    </div>
                    <span class="{{ $tin }} font-bold text-success-ink">{{ $sponsorsIncome ? $fmt($sponsorsIncome) : '—' }}</span>
                </div>

                {{-- Exhibition / Booths --}}
                <div class="flex items-center gap-2 border-b border-line px-3 py-1.5 text-xs">
                    <a href="{{ route('events.hub', [$event, 'tab' => 'exhibition']) }}" class="min-w-0 flex-1">
                        <p class="font-bold text-ink hover:text-gold-800">Exhibition / Booths →</p>
                        <p class="text-eyebrow text-muted">{{ $exhibitorsCount }} sold · {{ $exhibitorsReceived ? $fmt($exhibitorsReceived).' received' : 'sell booths' }}</p>
                    </a>
                    <div class="{{ $tin }}">
                        <span class="inline-flex items-center gap-0.5 rounded-md border border-line bg-white px-1">
                            <span class="text-eyebrow text-muted">{{ $event->currencySymbol() }}</span>
                            <input type="number" min="0" step="1000" wire:model.live.debounce.600ms="exhibitionTarget" placeholder="0" class="w-12 bg-transparent text-right text-micro font-semibold text-ink focus:outline-none">
                        </span>
                    </div>
                    <span class="{{ $tin }} font-bold text-success-ink">{{ $exhibitorsIncome ? $fmt($exhibitorsIncome) : '—' }}</span>
                </div>

                {{-- Other income items --}}
                @foreach ($otherIncomeItems as $inc)
                    <div wire:key="inc-{{ $inc->id }}" class="group/inc relative flex items-center gap-2 border-b border-line px-3 py-1.5 text-xs hover:bg-page/30">
                        <span class="min-w-0 flex-1 truncate text-ink"><span class="font-semibold">{{ $inc->sourceLabel() }}</span>@if ($inc->description) <span class="text-muted">· {{ $inc->description }}</span>@endif <span class="rounded-full bg-page px-1.5 text-eyebrow font-bold uppercase text-muted">{{ $inc->status }}</span></span>
                        <span class="{{ $tin }} text-muted">—</span>
                        <span class="{{ $tin }} font-semibold text-success-ink">{{ $fmt($inc->amount_cents) }}</span>
                        <div class="absolute right-2 top-1/2 hidden -translate-y-1/2 items-center gap-1 rounded-lg border border-line bg-white px-1 py-0.5 shadow-sm group-hover/inc:flex">
                            <button type="button" wire:click="editIncome({{ $inc->id }})" class="rounded bg-page px-1.5 py-0.5 text-eyebrow font-bold text-muted hover:bg-line">✎</button>
                            <x-confirm title="Delete this income line?"
                                       confirm="Delete"
                                       run="$wire.deleteIncome({{ $inc->id }})"
                                       class="rounded bg-danger-soft px-1.5 py-0.5 text-eyebrow font-bold text-danger hover:bg-danger/20">✕</x-confirm>
                        </div>
                    </div>
                @endforeach

                {{-- quick-add other income sources (client fund is its own stream above) --}}
                <div class="flex flex-wrap items-center gap-1.5 border-t border-line bg-page/20 px-3 py-1.5">
                    <span class="text-eyebrow font-semibold uppercase tracking-wide text-muted">Add:</span>
                    @foreach (\App\Support\Taxonomy::options('income_source') as $key => $label)
                        @continue ($key === 'client')
                        <button type="button" wire:click="newIncome('{{ $key }}')" class="rounded-full border border-line bg-white px-2 py-0.5 text-eyebrow font-semibold text-ink transition hover:border-gold-300 hover:text-gold-800">＋ {{ $label }}</button>
                    @endforeach
                </div>

                {{-- total --}}
                <div class="flex items-center gap-2 border-t border-line bg-success-soft/40 px-3 py-1.5 text-xs font-bold">
                    <span class="flex-1 text-ink">Total income</span>
                    <span class="{{ $tin }} text-muted">{{ $fmt($totalTargetIncome) }}</span>
                    <span class="{{ $tin }} text-success-ink">{{ $fmt($totalIncome) }}</span>
                </div>
            </div>
