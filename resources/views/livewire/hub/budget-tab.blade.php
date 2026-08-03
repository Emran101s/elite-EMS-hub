@php
    use App\Models\Event;
    use App\Models\EventBudgetItem;
    $fmt = fn ($cents) => $event->money($cents);
    // convert cents in the event currency into the other currency, formatted
    $conv = fn ($cents) => Event::moneyIn((int) round(($cents ?? 0) * $fxRate), $fxOther);
    $cap = $event->budget_cents ?? 0;
    // One definition, shared with the Overview tile and the alert feed — see
    // Event::budgetUsedPct(). Not clamped: an event at 153% should say so
    // rather than sit quietly at 100 while the ring looks full.
    $usedPct = $event->budgetUsedPct() ?? 0;
    $paidPct = $cap > 0 ? min(100, round($paidTotal / $cap * 100)) : 0;
    $remaining = $cap - $grandForecast;
    $track = $view === 'track';
    // Price mode answers the question the budget could not: this cost me X,
    // what am I charging for it?
    $price = $view === 'price';
    $feePct = (float) ($event->management_fee_pct ?? \App\Models\EventBudgetItem::DEFAULT_FEE_PCT);
    $money = 'w-24 shrink-0 text-right';
    // Per-category colour + icon, cycled by position — a distinct chip like the builder concept.
    $catPalette = [
        ['bg-sky-100', 'text-sky-600'], ['bg-blue-100', 'text-blue-600'], ['bg-rose-100', 'text-rose-600'],
        ['bg-violet-100', 'text-violet-600'], ['bg-teal-100', 'text-teal-600'], ['bg-emerald-100', 'text-emerald-600'],
        ['bg-amber-100', 'text-amber-600'], ['bg-indigo-100', 'text-indigo-600'], ['bg-fuchsia-100', 'text-fuchsia-600'],
    ];
    $catIcons = ['users', 'building', 'clipboard', 'grid', 'star', 'truck', 'archive', 'currency', 'chart'];
@endphp

<div>
    {{-- ══ Budget headline strip ══ --}}
    @php
        // Price mode is asking a different question, so the strip answers that
        // one instead of repeating the budget's.
        $sellSummary = $price ? $event->sellSummary() : null;
        $figures = $price
            ? [
                ['Cost to us', $fmt($sellSummary['cost']), 'text-white'],
                ['Charged to client', $fmt($sellSummary['sell']), 'text-white'],
                ['Gross margin', ($sellSummary['margin'] >= 0 ? '' : '−').$fmt(abs($sellSummary['margin'])), $sellSummary['margin'] < 0 ? 'text-red-300' : 'text-emerald-300'],
            ]
            : [
                ['Total budget', $fmt($cap), 'text-white'],
                ['Forecast', $fmt($grandForecast), $grandForecast > $cap && $cap > 0 ? 'text-red-300' : 'text-white'],
                ['Paid', $paidTotal ? $fmt($paidTotal) : '—', $paidTotal ? 'text-emerald-300' : 'text-white/30'],
                [$remaining < 0 ? 'Over budget' : 'Remaining', $fmt(abs($remaining)), $remaining < 0 ? 'text-red-300' : 'text-gold-300'],
            ];
    @endphp

    <x-module-head :ring="$price ? max(0, (int) ($sellSummary['marginPct'] ?? 0)) : min(100, $usedPct)"
                   :ring-label="$price ? (($sellSummary['marginPct'] ?? null) === null ? '—' : $sellSummary['marginPct'].'%') : $usedPct.'%'"
                   :eyebrow="$price ? 'Margin' : 'Budget used'"
                   :figures="$figures"
                   class="mb-5 px-6 py-5">
        @if ($price)
            <x-slot:meter>
                {{-- How much of this is a decision and how much is the default. --}}
                <p class="text-eyebrow font-bold uppercase tracking-[0.16em] text-gold-300/80">How it was priced</p>
                <p class="mt-1.5 text-xs font-semibold text-white/80">
                    {{ $sellSummary['priced'] }} of {{ $sellSummary['lines'] }} {{ str('line')->plural($sellSummary['lines']) }} priced by hand
                </p>
                <p class="mt-0.5 text-eyebrow text-white/45">
                    The rest charge cost plus the {{ rtrim(rtrim(number_format($sellSummary['fee'], 2), '0'), '.') }}% management fee.
                    @if ($sellSummary['absorbed'] > 0)
                        {{ $fmt($sellSummary['absorbed']) }} absorbed.
                    @endif
                </p>
            </x-slot:meter>
        @else
            <x-slot:meter>
                <div class="mb-1.5 flex items-baseline justify-between">
                    <span class="text-eyebrow font-bold uppercase tracking-[0.16em] text-gold-300/80">Spend</span>
                    <span class="text-xs font-bold {{ $usedPct >= 100 ? 'text-red-300' : 'text-white' }}">{{ $usedPct }}%</span>
                </div>
                <div class="flex h-2 overflow-hidden rounded-full bg-white/15">
                    <div class="bg-emerald-400" style="width: {{ $paidPct }}%"></div>
                    <div class="bg-gold-400" style="width: {{ max(0, min(100, $usedPct) - $paidPct) }}%"></div>
                </div>
                <p class="mt-1.5 flex items-center gap-3 text-eyebrow text-white/40">
                    <span class="flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Paid</span>
                    <span class="flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-gold-400"></span> Committed</span>
                </p>
            </x-slot:meter>
        @endif
    </x-module-head>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
        {{-- ══════════ MAIN · ledger ══════════ --}}
        <div class="min-w-0">
            @if ($event->budgetLocked())
                <div class="mb-4 flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/60 px-4 py-3">
                    <span class="text-lg">🔒</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold text-emerald-800">Approved budget — locked baseline</p>
                        <p class="text-eyebrow text-emerald-700/80">This budget is the approved baseline and can't be edited. Actual costs still track; create a revision to change the plan.</p>
                    </div>
                    <button type="button" wire:click="reviseBudget" class="shrink-0 rounded-xl border border-emerald-300 bg-white px-3 py-1.5 text-eyebrow font-bold text-emerald-700 transition hover:bg-emerald-100">Create revision</button>
                </div>
            @elseif (($event->budget_status ?? 'draft') === 'pending')
                <div class="mb-4 flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50/60 px-4 py-3">
                    <span class="text-lg">⏳</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold text-amber-800">Pending approval</p>
                        <p class="text-eyebrow text-amber-700/80">Submitted for sign-off. Approve or reject from the Approval panel.</p>
                    </div>
                </div>
            @endif
            {{-- income (money in) — three streams, target vs actual --}}
            @php $tin = 'w-20 shrink-0 text-right'; @endphp
            <div class="card mb-4 overflow-hidden">
                <div class="flex items-center justify-between border-b border-line bg-emerald-50/50 px-3 py-2">
                    <span class="flex items-center gap-1.5 text-xs font-bold text-navy-900"><span class="text-emerald-600">▲</span> Income (money in)</span>
                    <div class="text-right leading-tight">
                        <span class="text-sm font-bold text-emerald-700">{{ $fmt($totalIncome) }}</span>
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
                <div class="flex items-center gap-2 border-b border-line px-3 py-2 text-xs">
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-navy-900">Client / Main Fund</p>
                        {{-- Where the money was recorded, each part linked to the
                             place that recorded it. One figure with three possible
                             homes is a figure people query; this answers it. --}}
                        @if ($clientMoney['collected'] > 0)
                            <p class="text-eyebrow text-muted">
                                @if ($contractCollected > 0)
                                    <a href="{{ route('events.hub', [$event, 'tab' => 'contract']) }}" class="font-bold text-gold-600 hover:text-gold-700">{{ $fmt($contractCollected) }} via contract{{ $contractRef ? ' '.$contractRef : '' }}</a>
                                @endif
                                @if ($clientMoney['invoices'] > 0)
                                    @if ($contractCollected > 0) · @endif
                                    <a href="{{ route('invoices.index') }}" class="font-bold text-gold-600 hover:text-gold-700">{{ $fmt($clientMoney['invoices']) }} via invoices</a>
                                @endif
                                @if ($clientMoney['manual'] > 0)
                                    @if ($contractCollected > 0 || $clientMoney['invoices'] > 0) · @endif
                                    {{ $fmt($clientMoney['manual']) }} logged here
                                @endif
                                · <button type="button" wire:click="newIncome('client')" class="font-semibold text-navy-400 hover:text-navy-700">＋ extra</button>
                            </p>
                        @else
                            <p class="text-eyebrow text-muted">Primary income · <button type="button" wire:click="newIncome('client')" class="font-bold text-gold-600 hover:text-gold-700">＋ log payment</button></p>
                        @endif
                    </div>
                    <div class="{{ $tin }}">
                        <span class="inline-flex items-center gap-0.5 rounded-md border border-line bg-white px-1">
                            <span class="text-eyebrow text-navy-300">{{ $event->currencySymbol() }}</span>
                            <input type="number" min="0" step="1000" wire:model.live.debounce.600ms="clientTarget" placeholder="0" class="w-12 bg-transparent text-right text-micro font-semibold text-navy-700 focus:outline-none">
                        </span>
                    </div>
                    <span class="{{ $tin }} font-bold text-emerald-700">{{ $clientActual ? $fmt($clientActual) : '—' }}</span>
                </div>

                {{-- Extra income --}}
                <div class="bg-navy-900/[0.03] px-3 py-1 text-eyebrow font-bold uppercase tracking-[0.16em] text-navy-400">Extra income</div>

                {{-- Sponsorships --}}
                <div class="flex items-center gap-2 border-b border-line px-3 py-2 text-xs">
                    <a href="{{ route('events.hub', [$event, 'tab' => 'sponsors']) }}" class="min-w-0 flex-1">
                        <p class="font-bold text-navy-900 hover:text-gold-700">Sponsorships →</p>
                        <p class="text-eyebrow text-muted">{{ $sponsorsCount }} sold · {{ $sponsorsReceived ? $fmt($sponsorsReceived).' received' : 'sell packages' }}</p>
                    </a>
                    <div class="{{ $tin }}">
                        <span class="inline-flex items-center gap-0.5 rounded-md border border-line bg-white px-1">
                            <span class="text-eyebrow text-navy-300">{{ $event->currencySymbol() }}</span>
                            <input type="number" min="0" step="1000" wire:model.live.debounce.600ms="sponsorshipTarget" placeholder="0" class="w-12 bg-transparent text-right text-micro font-semibold text-navy-700 focus:outline-none">
                        </span>
                    </div>
                    <span class="{{ $tin }} font-bold text-emerald-700">{{ $sponsorsIncome ? $fmt($sponsorsIncome) : '—' }}</span>
                </div>

                {{-- Exhibition / Booths --}}
                <div class="flex items-center gap-2 border-b border-line px-3 py-2 text-xs">
                    <a href="{{ route('events.hub', [$event, 'tab' => 'exhibition']) }}" class="min-w-0 flex-1">
                        <p class="font-bold text-navy-900 hover:text-gold-700">Exhibition / Booths →</p>
                        <p class="text-eyebrow text-muted">{{ $exhibitorsCount }} sold · {{ $exhibitorsReceived ? $fmt($exhibitorsReceived).' received' : 'sell booths' }}</p>
                    </a>
                    <div class="{{ $tin }}">
                        <span class="inline-flex items-center gap-0.5 rounded-md border border-line bg-white px-1">
                            <span class="text-eyebrow text-navy-300">{{ $event->currencySymbol() }}</span>
                            <input type="number" min="0" step="1000" wire:model.live.debounce.600ms="exhibitionTarget" placeholder="0" class="w-12 bg-transparent text-right text-micro font-semibold text-navy-700 focus:outline-none">
                        </span>
                    </div>
                    <span class="{{ $tin }} font-bold text-emerald-700">{{ $exhibitorsIncome ? $fmt($exhibitorsIncome) : '—' }}</span>
                </div>

                {{-- Other income items --}}
                @foreach ($otherIncomeItems as $inc)
                    <div wire:key="inc-{{ $inc->id }}" class="group/inc relative flex items-center gap-2 border-b border-line px-3 py-1.5 text-xs hover:bg-page/30">
                        <span class="min-w-0 flex-1 truncate text-navy-900"><span class="font-semibold">{{ $inc->sourceLabel() }}</span>@if ($inc->description) <span class="text-muted">· {{ $inc->description }}</span>@endif <span class="rounded-full bg-navy-100 px-1.5 text-eyebrow font-bold uppercase text-navy-500">{{ $inc->status }}</span></span>
                        <span class="{{ $tin }} text-navy-300">—</span>
                        <span class="{{ $tin }} font-semibold text-emerald-700">{{ $fmt($inc->amount_cents) }}</span>
                        <div class="absolute right-2 top-1/2 hidden -translate-y-1/2 items-center gap-1 rounded-lg border border-line bg-white px-1 py-0.5 shadow-sm group-hover/inc:flex">
                            <button type="button" wire:click="editIncome({{ $inc->id }})" class="rounded bg-navy-50 px-1.5 py-0.5 text-eyebrow font-bold text-navy-600 hover:bg-navy-100">✎</button>
                            <button type="button" wire:click="deleteIncome({{ $inc->id }})" wire:confirm="Delete this income line?" class="rounded bg-risk/10 px-1.5 py-0.5 text-eyebrow font-bold text-red-700 hover:bg-risk/20">✕</button>
                        </div>
                    </div>
                @endforeach

                {{-- quick-add other income sources (client fund is its own stream above) --}}
                <div class="flex flex-wrap items-center gap-1.5 border-t border-line bg-page/20 px-3 py-2">
                    <span class="text-eyebrow font-semibold uppercase tracking-wide text-muted">Add income:</span>
                    @foreach (\App\Support\Taxonomy::options('income_source') as $key => $label)
                        @continue ($key === 'client')
                        <button type="button" wire:click="newIncome('{{ $key }}')" class="rounded-full border border-line bg-white px-2.5 py-0.5 text-eyebrow font-semibold text-navy-700 transition hover:border-gold-400 hover:text-gold-700">＋ {{ $label }}</button>
                    @endforeach
                </div>

                {{-- total --}}
                <div class="flex items-center gap-2 border-t border-line bg-emerald-50/40 px-3 py-2 text-xs font-bold">
                    <span class="flex-1 text-navy-900">Total income</span>
                    <span class="{{ $tin }} text-navy-500">{{ $fmt($totalTargetIncome) }}</span>
                    <span class="{{ $tin }} text-emerald-700">{{ $fmt($totalIncome) }}</span>
                </div>
            </div>

                {{-- ══ builder toolbar ══ --}}
                @unless ($event->budgetLocked())
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <div class="flex items-center gap-1.5">
                            <input type="text" data-newcat wire:model="newCategoryName" wire:keydown.enter="addCategory" maxlength="60" class="input h-10 w-44 text-sm" placeholder="New category name…">
                            <button type="button" wire:click="addCategory" class="btn-navy h-10 px-4 text-xs"><span class="text-gold-400">＋</span> Add Category</button>
                        </div>
                        <button type="button" wire:click="newLine" class="h-10 rounded-xl border border-line bg-white px-4 text-xs font-semibold text-navy-700 transition hover:border-gold-300">＋ Add Line Item</button>
                        <div class="ml-auto flex items-center gap-2 text-eyebrow font-bold uppercase tracking-wide text-muted">
                            <button type="button" wire:click="expandAll" class="rounded-lg px-2 py-1 hover:bg-navy-50 hover:text-navy-700">Expand all</button>
                            <button type="button" wire:click="collapseAll" class="rounded-lg px-2 py-1 hover:bg-navy-50 hover:text-navy-700">Collapse all</button>
                        </div>
                    </div>
                    @error('newCategoryName') <p class="mb-2 text-micro font-semibold text-risk">{{ $message }}</p> @enderror
                @endunless

                <x-bulk-bar :count="$this->selectedCount()" noun="line" />

                <div class="card overflow-hidden">
                    <div class="overflow-x-auto">
                        <div class="{{ $track || $price ? 'min-w-[680px]' : 'min-w-[380px]' }}">
                            {{-- column labels --}}
                            <div class="flex items-center gap-2 border-b border-line bg-page/40 px-3 py-2.5 text-eyebrow font-bold uppercase tracking-wide text-muted">
                                <span class="flex-1">Category / Line item</span>
                                <span class="{{ $money }}">{{ match ($view) { 'track' => 'Budget', 'price' => 'Cost', default => 'Estimated cost' } }}</span>
                                @if ($track)
                                    <span class="{{ $money }}">Actual</span>
                                    <span class="{{ $money }}">Paid</span>
                                    <span class="{{ $money }}">Saved / Over</span>
                                @elseif ($price)
                                    <span class="{{ $money }}">Charged</span>
                                    <span class="{{ $money }}">Margin</span>
                                    <span class="w-12 shrink-0 text-right">%</span>
                                @endif
                            </div>

                            <div data-cat-sort>
                            @foreach ($sections as $section)
                                @php
                                    $secItems = $section['items'];
                                    $catEst = $secItems->sum('estimated_cents');
                                    $catAct = $secItems->sum('actual_cents');
                                    $catPaid = $secItems->sum('paid_cents');
                                    $isOpen = ! in_array($section['key'], $collapsed, true);
                                    $catFlagged = $secItems->contains(fn ($i) => $i->flagged);
                                    $spendPct = $catEst > 0 ? min(100, round($catAct / $catEst * 100)) : 0;
                                    $paidPctCat = $catEst > 0 ? min(100, round($catPaid / $catEst * 100)) : 0;
                                    $isRenaming = $section['id'] && $editingCategoryId === $section['id'];
                                    $catArg = addslashes($section['name']);
                                    [$cbg, $cfg] = $catPalette[$loop->index % count($catPalette)];
                                    $cicon = $catIcons[$loop->index % count($catIcons)];
                                @endphp

                                <div wire:key="catblock-{{ $section['key'] }}" @if ($section['id']) data-cat="{{ $section['id'] }}" @endif class="cat-block">
                                {{-- ── category header ── --}}
                                <div class="group/cat border-b border-line bg-white">
                                    <div class="flex items-center gap-2 px-3 py-2">
                                        @if ($isRenaming)
                                            <span class="shrink-0 text-eyebrow text-navy-300">▶</span>
                                            <input type="text" wire:model="categoryEditName" wire:keydown.enter="saveCategoryName" maxlength="60" class="input h-8 min-w-0 flex-1 text-sm font-bold">
                                            <button type="button" wire:click="saveCategoryName" class="shrink-0 rounded-lg bg-navy-900 px-2.5 py-1 text-eyebrow font-bold text-white">Save</button>
                                            <button type="button" wire:click="cancelRenameCategory" class="shrink-0 text-eyebrow font-semibold text-navy-500 hover:text-navy-900">Cancel</button>
                                        @else
                                            @if ($section['id'] && ! $event->budgetLocked())
                                                <span class="cat-drag hidden shrink-0 cursor-grab select-none text-micro leading-none text-navy-300 hover:text-navy-500 active:cursor-grabbing sm:inline" title="Drag to reorder">⠿</span>
                                            @endif
                                            <button type="button" wire:click="toggleCollapse('{{ $section['key'] }}')" class="flex min-w-0 flex-1 items-center gap-2.5 text-left">
                                                <span class="shrink-0 text-eyebrow text-navy-400 transition-transform {{ $isOpen ? 'rotate-90' : '' }}">▶</span>
                                                <span class="w-4 shrink-0 text-center text-eyebrow font-bold text-navy-300">{{ $loop->iteration }}</span>
                                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ $cbg }} {{ $cfg }}"><x-icon :name="$cicon" class="h-3.5 w-3.5" /></span>
                                                <span class="truncate text-sm font-bold text-navy-900">{{ $section['name'] }}</span>
                                                <span class="shrink-0 rounded-full bg-navy-100 px-2 py-0.5 text-eyebrow font-bold text-navy-500">{{ $secItems->count() }} {{ str('item')->plural($secItems->count()) }}</span>
                                                @if ($catFlagged)<span class="shrink-0 text-micro text-risk">⚑</span>@endif
                                            </button>
                                            @unless ($event->budgetLocked())
                                                <span class="hidden shrink-0 items-center gap-1 group-hover/cat:flex">
                                                    @if ($price)
                                                        {{-- Pricing line by line is right when a line has its
                                                             own deal; most of the time a category is one
                                                             decision. Lines already quoted are left alone. --}}
                                                        @foreach ([10, 15, 20, 25] as $pct)
                                                            <button type="button" wire:click="markupCategory('{{ $catArg }}', {{ $pct }})"
                                                                    class="rounded bg-navy-50 px-1.5 py-0.5 text-eyebrow font-bold text-navy-600 hover:bg-gold-100 hover:text-gold-700"
                                                                    title="Charge every unquoted line in {{ $section['name'] }} at cost plus {{ $pct }}%">+{{ $pct }}%</button>
                                                        @endforeach
                                                        <button type="button" wire:click="clearCategoryPricing('{{ $catArg }}')"
                                                                wire:confirm="Put every line in “{{ $section['name'] }}” back on the {{ rtrim(rtrim(number_format($feePct, 2), '0'), '.') }}% management fee? Any prices typed by hand are cleared."
                                                                class="rounded bg-navy-50 px-1.5 py-0.5 text-eyebrow font-bold text-navy-400 hover:bg-navy-100" title="Back to the management fee">↺</button>
                                                    @endif
                                                    <button type="button" wire:click="newLine('{{ $catArg }}')" class="rounded bg-gold-50 px-1.5 py-0.5 text-eyebrow font-bold text-gold-700 hover:bg-gold-100" title="Add line">＋ line</button>
                                                    @if ($section['id'])
                                                        <button type="button" wire:click="startRenameCategory({{ $section['id'] }})" class="rounded bg-navy-50 px-1.5 py-0.5 text-eyebrow font-bold text-navy-600 hover:bg-navy-100" title="Rename category">✎</button>
                                                        <button type="button" wire:click="deleteCategory({{ $section['id'] }})" wire:confirm="Delete “{{ $section['name'] }}”? Any lines move to another category." class="rounded bg-risk/10 px-1.5 py-0.5 text-eyebrow font-bold text-red-700 hover:bg-risk/20" title="Delete category">✕</button>
                                                    @endif
                                                </span>
                                            @endunless
                                            <button type="button" wire:click="newLine('{{ $catArg }}')" class="shrink-0 text-xs font-bold text-gold-600 hover:text-gold-700 group-hover/cat:hidden" title="Add line to this category">＋</button>
                                            @php $catShown = $price ? $secItems->sum(fn ($i) => $i->costCents()) : $catEst; @endphp
                                            <span class="{{ $money }} text-xs font-bold text-navy-900">{{ $catShown ? $fmt($catShown) : '—' }}</span>
                                            @if ($price)
                                                @php
                                                    $catCost = $secItems->sum(fn ($i) => $i->costCents());
                                                    $catSell = $secItems->sum(fn ($i) => $i->sellCents($feePct));
                                                    $catMargin = $catSell - $catCost;
                                                    $catMarginPct = $catSell > 0 ? (int) round($catMargin / $catSell * 100) : null;
                                                @endphp
                                                <span class="{{ $money }} text-xs font-bold text-navy-700">{{ $catSell ? $fmt($catSell) : '—' }}</span>
                                                <span class="{{ $money }} text-xs font-bold {{ $catMargin < 0 ? 'text-risk' : 'text-emerald-700' }}">{{ $catCost || $catSell ? ($catMargin >= 0 ? '+' : '−').$fmt(abs($catMargin)) : '—' }}</span>
                                                <span class="w-12 shrink-0 text-right text-xs font-bold {{ $catMarginPct === null ? 'text-navy-300' : ($catMarginPct < 0 ? 'text-risk' : 'text-navy-500') }}">{{ $catMarginPct === null ? '—' : $catMarginPct.'%' }}</span>
                                            @elseif ($track)
                                                @php $secCosted = $secItems->filter->hasActual(); $secSaved = $secCosted->sum(fn ($i) => $i->varianceCents()); @endphp
                                                <span class="{{ $money }} text-xs font-bold {{ $catAct > $catEst && $catEst > 0 ? 'text-risk' : 'text-navy-700' }}">{{ $catAct ? $fmt($catAct) : '—' }}</span>
                                                <span class="{{ $money }} text-xs font-bold text-emerald-700">{{ $catPaid ? $fmt($catPaid) : '—' }}</span>
                                                <span class="{{ $money }} text-xs font-bold {{ $secCosted->isEmpty() ? 'text-navy-300' : ($secSaved < 0 ? 'text-risk' : 'text-emerald-600') }}">{{ $secCosted->isEmpty() ? '—' : ($secSaved >= 0 ? '+' : '−').$fmt(abs($secSaved)) }}</span>
                                            @endif
                                        @endif
                                    </div>
                                    {{-- spend bar (track only) --}}
                                    @if ($track && $catEst > 0 && ! $isRenaming)
                                        <div class="px-3 pb-2">
                                            <div class="flex h-1 overflow-hidden rounded-full bg-navy-100">
                                                <div class="bg-emerald-500" style="width: {{ $paidPctCat }}%"></div>
                                                <div class="bg-gold-400" style="width: {{ max(0, $spendPct - $paidPctCat) }}%"></div>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                {{-- ── line items (collapsible) ── --}}
                                @if ($isOpen)
                                    @forelse ($secItems as $item)
                                        <div wire:key="bi-{{ $item->id }}" class="group/line relative flex items-center gap-2 border-b border-line px-3 py-1.5 pl-9 last:border-0 hover:bg-page/40 {{ $this->isSelected($item->id) ? 'bg-navy-50/60' : 'bg-page/[0.15]' }}">
                                            @unless ($item->isLinked())
                                                <button type="button" wire:click.stop="toggleSelect({{ $item->id }})" class="flex h-4 w-4 shrink-0 items-center justify-center rounded border text-eyebrow {{ $this->isSelected($item->id) ? 'border-navy-900 bg-navy-900 text-white' : 'border-navy-200 text-transparent hover:border-navy-400' }}" title="Select">✓</button>
                                            @endunless
                                            <button type="button" wire:click="toggleFlag({{ $item->id }})" class="shrink-0 text-micro {{ $item->flagged ? 'text-risk' : 'text-navy-200 hover:text-navy-400' }}" title="{{ $item->flagged ? 'Flagged — click to unflag' : 'Flag as priority' }}">{{ $item->flagged ? '⚑' : '⚐' }}</button>
                                            @php
                                                $bits = [];
                                                if ($item->quantity > 1 || $item->unit_cents) $bits[] = $item->quantity.' × '.$fmt($item->unit_cents);
                                                if ($item->vendor) $bits[] = $item->vendor;
                                                if ($item->invoice_number) $bits[] = $item->invoice_number;
                                                if ($item->due_on) $bits[] = 'due '.$item->due_on->format('j M');
                                            @endphp
                                            <button type="button" wire:click="editLine({{ $item->id }})" @disabled($event->budgetLocked() || $item->isLinked()) class="group/edit min-w-0 flex-1 text-left" title="{{ $item->isLinked() ? 'Synced — edit in the '.\Illuminate\Support\Str::headline($item->linkedTab()).' tab' : 'Click to edit this line' }}">
                                                <p class="flex items-center gap-1.5 truncate text-xs font-medium text-navy-900 group-hover/edit:text-gold-700">
                                                    @if ($item->isLinked())<span class="shrink-0 rounded bg-navy-100 px-1 text-eyebrow font-bold uppercase tracking-wide text-navy-500" title="Synced from module">🔗 {{ $item->linkedTab() === 'transportation' ? 'transport' : $item->linkedTab() }}</span>@endif
                                                    <span class="truncate">{{ $item->description ?? $item->categoryLabel() }}</span>
                                                </p>
                                                @if ($item->isLinked())
                                                    <p class="truncate text-eyebrow text-navy-400">Synced from {{ \Illuminate\Support\Str::headline($item->linkedTab()) }}@if ($item->vendor) · {{ $item->vendor }}@endif</p>
                                                @elseif ($bits)
                                                    <p class="truncate text-eyebrow text-muted">{{ implode(' · ', $bits) }}</p>
                                                @else
                                                    <p class="truncate text-eyebrow text-gold-600/70 group-hover/edit:text-gold-700">Click to set quantity &amp; unit cost →</p>
                                                @endif
                                            </button>
                                            @php $lineCostShown = $price ? $item->costCents() : $item->estimated_cents; @endphp
                                            <button type="button" wire:click="editLine({{ $item->id }})" @disabled($event->budgetLocked() || $item->isLinked()) class="{{ $money }} text-micro font-semibold {{ $lineCostShown ? 'text-navy-900' : 'text-gold-600 hover:text-gold-700' }}">{{ $lineCostShown ? $fmt($lineCostShown) : '＋ set' }}</button>
                                            @if ($price)
                                                @php
                                                    $lineSell = $item->sellCents($feePct);
                                                    $lineMargin = $item->marginCents($feePct);
                                                    $linePct = $item->marginPct($feePct);
                                                    // How this line got its price — the difference between a
                                                    // quote and a default is worth seeing at a glance.
                                                    $how = $item->sell_cents !== null ? 'quoted' : ($item->markup_pct !== null ? '+'.rtrim(rtrim(number_format($item->markup_pct, 1), '0'), '.').'%' : null);
                                                @endphp
                                                <button type="button" wire:click="editLine({{ $item->id }})" @disabled($event->budgetLocked() || $item->isLinked())
                                                        class="{{ $money }} text-micro font-semibold {{ $item->billable === false ? 'text-navy-300 line-through' : 'text-navy-900' }}"
                                                        title="{{ $how ? 'Priced by hand ('.$how.')' : 'Charged at the '.$feePct.'% management fee' }}">
                                                    {{ $item->billable === false ? '—' : $fmt($lineSell) }}
                                                    @if ($how)<span class="ms-0.5 text-[9px] font-bold text-gold-600">{{ $how }}</span>@endif
                                                </button>
                                                <span class="{{ $money }} text-micro font-semibold {{ $lineMargin < 0 ? 'text-risk' : 'text-emerald-700' }}">{{ ($lineMargin >= 0 ? '+' : '−').$fmt(abs($lineMargin)) }}</span>
                                                <span class="w-12 shrink-0 text-right text-micro font-semibold {{ $linePct === null ? 'text-navy-300' : ($linePct < 0 ? 'text-risk' : 'text-navy-500') }}">{{ $linePct === null ? '—' : $linePct.'%' }}</span>
                                            @elseif ($track)
                                                @php $lineSaved = $item->actual_cents > 0 ? $item->estimated_cents - $item->actual_cents : null; @endphp
                                                <span class="{{ $money }} text-micro font-semibold {{ $item->actual_cents > $item->estimated_cents && $item->estimated_cents > 0 ? 'text-risk' : 'text-navy-900' }}">{{ $item->actual_cents ? $fmt($item->actual_cents) : '—' }}</span>
                                                <span class="{{ $money }} text-micro font-semibold text-emerald-700">{{ $item->paid_cents ? $fmt($item->paid_cents) : '—' }}</span>
                                                <span class="{{ $money }} text-micro font-semibold {{ $lineSaved === null ? 'text-navy-300' : ($lineSaved < 0 ? 'text-risk' : 'text-emerald-600') }}">{{ $lineSaved === null ? '—' : ($lineSaved >= 0 ? '+' : '−').$fmt(abs($lineSaved)) }}</span>
                                            @endif
                                            {{-- hover actions --}}
                                            <div class="absolute right-2 top-1/2 hidden -translate-y-1/2 items-center gap-1 rounded-lg border border-line bg-white px-1 py-0.5 shadow-sm group-hover/line:flex">
                                                @if ($track && $item->payment_status !== 'paid')
                                                    <button type="button" wire:click="markPaid({{ $item->id }})" class="rounded bg-emerald-50 px-1.5 py-0.5 text-eyebrow font-bold text-emerald-700 hover:bg-emerald-100" title="Mark fully paid">✓</button>
                                                @endif
                                                @if ($item->isLinked())
                                                    <a href="{{ route('events.hub', [$event, 'tab' => $item->linkedTab()]) }}" class="rounded bg-navy-50 px-1.5 py-0.5 text-eyebrow font-bold text-navy-600 hover:bg-navy-100" title="Edit in the {{ \Illuminate\Support\Str::headline($item->linkedTab()) }} tab">↗ source</a>
                                                @else
                                                    <button type="button" wire:click="duplicateLine({{ $item->id }})" class="rounded bg-navy-50 px-1.5 py-0.5 text-eyebrow font-bold text-navy-600 hover:bg-navy-100" title="Duplicate">⧉</button>
                                                    <button type="button" wire:click="editLine({{ $item->id }})" class="rounded bg-navy-50 px-1.5 py-0.5 text-eyebrow font-bold text-navy-600 hover:bg-navy-100" title="Edit">✎</button>
                                                    <button type="button" wire:click="deleteLine({{ $item->id }})" wire:confirm="Delete this line?" class="rounded bg-risk/10 px-1.5 py-0.5 text-eyebrow font-bold text-red-700 hover:bg-risk/20" title="Delete">✕</button>
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <button type="button" wire:click="newLine('{{ $catArg }}')" @disabled($event->budgetLocked()) class="flex w-full items-center gap-2 border-b border-line bg-page/[0.15] px-3 py-2 pl-9 text-left text-micro text-muted transition hover:bg-page/40 hover:text-gold-700 disabled:opacity-50">
                                            <span class="text-gold-700">＋</span> Add a line to {{ $section['name'] }}
                                        </button>
                                    @endforelse
                                    @if ($secItems->isNotEmpty() && ! $event->budgetLocked())
                                        <button type="button" wire:click="newLine('{{ $catArg }}')" class="flex w-full items-center gap-1.5 border-b border-line bg-page/[0.15] px-3 py-1.5 pl-9 text-left text-micro font-semibold text-gold-600 transition hover:bg-gold-50/60 hover:text-gold-700">
                                            <span>＋</span> Add Line Item
                                        </button>
                                    @endif
                                @endif
                                </div>{{-- /cat-block --}}
                            @endforeach
                            </div>{{-- /data-cat-sort --}}

                            {{-- ── add a category ── --}}
                            @unless ($event->budgetLocked())
                                <button type="button" x-on:click="document.querySelector('[data-newcat]')?.focus()" class="flex w-full items-center gap-1.5 border-b border-line bg-page/30 px-3 py-2.5 text-left text-micro font-bold text-navy-500 transition hover:bg-navy-50/60 hover:text-navy-700">
                                    <span class="text-gold-700">＋</span> Add New Category
                                </button>
                            @endunless

                            {{-- ── totals ── --}}
                            <div class="flex items-center gap-2 border-t border-line bg-white px-3 py-2">
                                <span class="flex-1 text-eyebrow font-bold uppercase tracking-wide text-navy-600">Subtotal</span>
                                <span class="{{ $money }} text-xs font-bold text-navy-900">{{ $fmt($estimatedTotal) }}</span>
                                @if ($track)
                                    <span class="{{ $money }} text-xs font-bold text-navy-900">{{ $actualTotal ? $fmt($actualTotal) : '—' }}</span>
                                    <span class="{{ $money }} text-xs font-bold text-emerald-700">{{ $paidTotal ? $fmt($paidTotal) : '—' }}</span>
                                    <span class="{{ $money }} text-xs font-bold {{ ! $hasActuals ? 'text-navy-300' : ($savedTotal < 0 ? 'text-risk' : 'text-emerald-600') }}">{{ ! $hasActuals ? '—' : ($savedTotal >= 0 ? '+' : '−').$fmt(abs($savedTotal)) }}</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 border-t border-line bg-gold-50/40 px-3 py-2">
                                <span class="flex min-w-0 flex-1 items-center gap-1.5">
                                    <span class="shrink-0 text-micro text-gold-700">⚑</span>
                                    <span class="truncate text-xs font-bold text-navy-900">Management fee</span>
                                    <span class="inline-flex shrink-0 items-center rounded-md border border-gold-300 bg-white px-1 text-eyebrow font-bold text-gold-700">
                                        <input type="number" min="0" max="100" step="0.5" wire:model.live.debounce.500ms="feePct" class="w-7 bg-transparent text-center focus:outline-none">%
                                    </span>
                                </span>
                                <span class="{{ $money }} text-xs font-bold text-gold-700">{{ $fmt($feeEst) }}</span>
                                @if ($track)
                                    <span class="{{ $money }} text-xs font-bold text-gold-700">{{ $feeAct ? $fmt($feeAct) : '—' }}</span>
                                    <span class="{{ $money }}"></span>
                                    <span class="{{ $money }}"></span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 border-t-2 border-navy-900/10 bg-page/50 px-3 py-2.5">
                                <span class="flex-1 text-xs font-bold uppercase tracking-wide text-navy-900">Grand total <span class="hidden font-normal normal-case text-muted sm:inline">(incl. {{ rtrim(rtrim(number_format($feePct, 2), '0'), '.') }}%)</span></span>
                                <span class="{{ $money }} text-sm font-bold text-navy-900">{{ $fmt($grandEst) }}</span>
                                @if ($track)
                                    <span class="{{ $money }} text-sm font-bold {{ $grandAct > $grandEst && $grandEst > 0 ? 'text-risk' : 'text-navy-900' }}">{{ $grandAct ? $fmt($grandAct) : '—' }}</span>
                                    <span class="{{ $money }} text-sm font-bold text-emerald-700">{{ $paidTotal ? $fmt($paidTotal) : '—' }}</span>
                                    <span class="{{ $money }} text-sm font-bold {{ ! $hasActuals ? 'text-navy-300' : ($savedTotal < 0 ? 'text-risk' : 'text-emerald-600') }}">{{ ! $hasActuals ? '—' : ($savedTotal >= 0 ? '+' : '−').$fmt(abs($savedTotal)) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
        </div>

        {{-- ══════════ RIGHT · Budget Control Center ══════════ --}}
        <div class="xl:sticky xl:top-4 xl:h-fit">
            <div class="cc-panel">
                {{-- control-center header --}}
                <div class="cc-head">
                    <x-icon name="sparkles" class="relative h-4 w-4 text-gold-600" />
                    <span class="relative text-2xs font-bold uppercase tracking-[0.18em] text-navy-900">Budget Control Center</span>
                    <a href="{{ route('events.budget.pdf', $event) }}" class="relative ml-auto flex items-center gap-1 rounded-lg border border-line bg-white px-2 py-1 text-3xs font-bold text-navy-600 transition hover:border-gold-300 hover:text-gold-700 {{ $items->isEmpty() ? 'pointer-events-none opacity-40' : '' }}">↧ PDF</a>
                </div>

                {{-- mode --}}
                <div class="border-b border-line p-4">
                    <p class="field-label !mb-2 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Mode</p>
                    <div class="flex rounded-xl border border-line bg-page/40 p-1">
                        <button type="button" wire:click="$set('view', 'build')" class="flex-1 rounded-lg py-1.5 text-xs font-bold transition {{ $view === 'build' ? 'bg-navy-900 text-white' : 'text-navy-600 hover:text-navy-900' }}">🧱 Build</button>
                        <button type="button" wire:click="$set('view', 'track')" class="flex-1 rounded-lg py-1.5 text-xs font-bold transition {{ $view === 'track' ? 'bg-navy-900 text-white' : 'text-navy-600 hover:text-navy-900' }}">📊 Track</button>
                        <button type="button" wire:click="$set('view', 'price')" class="flex-1 rounded-lg py-1.5 text-xs font-bold transition {{ $view === 'price' ? 'bg-navy-900 text-white' : 'text-navy-600 hover:text-navy-900' }}">💰 Price</button>
                    </div>
                    <p class="mt-2 text-eyebrow leading-snug text-muted">{{ match ($view) {
                        'build' => 'Plan budgeted amounts — quantity × unit from your own estimates.',
                        'track' => 'Track budget vs actual & paid — see where you saved or overspent.',
                        'price' => 'What each line costs you against what the client is charged for it.',
                    } }}</p>
                </div>

                {{-- total budget + fee + currency --}}
                <div class="border-b border-line p-4">
                    <p class="field-label !mb-2 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Total budget</p>
                    <div class="flex items-center gap-1.5">
                        <span class="text-lg font-bold text-navy-400">{{ $event->currencySymbol() }}</span>
                        <input type="number" min="0" step="1000" wire:model.live.debounce.500ms="budgetCap" class="input h-10 flex-1 text-base font-bold" placeholder="0">
                        <span class="text-eyebrow font-semibold text-muted">{{ $event->currency }}</span>
                    </div>
                    <div class="mt-2.5 flex items-center justify-between rounded-xl bg-gold-50/50 px-3 py-2">
                        <span class="text-eyebrow font-semibold text-muted">Management fee</span>
                        <span class="inline-flex items-center rounded-md border border-gold-300 bg-white px-1.5 text-xs font-bold text-gold-700">
                            <input type="number" min="0" max="100" step="0.5" wire:model.live.debounce.500ms="feePct" class="w-8 bg-transparent text-center focus:outline-none">%
                        </span>
                    </div>
                    <div class="mt-2 flex items-center justify-between rounded-xl bg-navy-50/60 px-3 py-2 text-eyebrow">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-navy-700">≈ {{ $conv($grandEst) }}</p>
                            <p class="truncate text-muted">1 {{ $event->currency }} = {{ rtrim(rtrim(number_format($fxRate, 4), '0'), '.') }} {{ $fxOther }} <span class="rounded-full px-1 text-eyebrow font-bold uppercase {{ $fxLive ? 'bg-emerald-100 text-emerald-700' : 'bg-navy-100 text-navy-500' }}">{{ $fxLive ? 'live' : 'pegged' }}</span></p>
                        </div>
                        <button type="button" wire:click="refreshRate" class="shrink-0 text-gold-600 hover:text-gold-700" title="Refresh rate">↻</button>
                    </div>
                </div>

                {{-- summary readout --}}
                <div class="border-b border-line p-4">
                    <p class="field-label !mb-2 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-navy-300"></span> Summary</p>
                    <div class="mb-1 flex justify-between text-eyebrow font-semibold text-muted">
                        <span>{{ $fmt($grandForecast) }} of {{ $fmt($cap) }}</span>
                        <span class="{{ $usedPct >= 100 ? 'text-risk' : 'text-navy-700' }}">{{ $usedPct }}%</span>
                    </div>
                    <div class="flex h-2 overflow-hidden rounded-full bg-navy-100">
                        <div class="bg-emerald-500" style="width: {{ $paidPct }}%"></div>
                        <div class="bg-gold-500" style="width: {{ max(0, $usedPct - $paidPct) }}%"></div>
                    </div>
                    <div class="mt-3 space-y-1.5 text-xs">
                        <div class="flex justify-between"><span class="text-muted">Grand budget</span><span class="font-bold text-navy-900">{{ $fmt($grandEst) }}</span></div>
                        @if ($track)
                            <div class="flex justify-between"><span class="text-muted">Actual</span><span class="font-bold {{ $grandAct > $grandEst && $grandEst > 0 ? 'text-risk' : 'text-navy-900' }}">{{ $grandAct ? $fmt($grandAct) : '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-muted">Paid</span><span class="font-bold text-emerald-700">{{ $paidTotal ? $fmt($paidTotal) : '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-muted">{{ $savedTotal < 0 ? 'Over budget' : 'Saved' }}</span><span class="font-bold {{ ! $hasActuals ? 'text-navy-300' : ($savedTotal < 0 ? 'text-risk' : 'text-emerald-600') }}">{{ ! $hasActuals ? '—' : ($savedTotal >= 0 ? '+' : '−').$fmt(abs($savedTotal)) }}</span></div>
                        @endif
                        <div class="flex justify-between border-t border-line pt-1.5"><span class="text-muted">{{ $remaining < 0 ? 'Over budget' : 'Remaining' }}</span><span class="font-bold {{ $remaining < 0 ? 'text-risk' : 'text-navy-900' }}">{{ $fmt($remaining) }}</span></div>
                        @if ($costPerHead !== null)
                            <div class="flex justify-between"><span class="text-muted">Cost / attendee <span class="text-navy-300">· {{ number_format($heads) }} pax</span></span><span class="font-bold text-navy-900">{{ $fmt($costPerHead) }}</span></div>
                        @endif
                    </div>
                </div>

                {{-- profit & loss --}}
                <div class="border-b border-line p-4">
                    <p class="field-label !mb-2 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Profit &amp; loss</p>
                    <div class="space-y-1.5 text-xs">
                        <div class="flex justify-between"><span class="text-muted">Income (actual)</span><span class="font-bold text-emerald-700">{{ $fmt($totalIncome) }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Cost to deliver</span><span class="font-bold text-navy-900">{{ $fmt($costToDeliver) }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Charged to client</span><span class="font-bold text-navy-900">{{ $fmt($grandForecast) }}</span></div>
                        <div class="flex items-center justify-between rounded-xl px-2 py-1.5 {{ $netResult < 0 ? 'bg-red-50' : 'bg-emerald-50' }}">
                            <span class="text-eyebrow font-bold uppercase tracking-wide {{ $netResult < 0 ? 'text-risk' : 'text-emerald-700' }}">{{ $netResult < 0 ? 'Net loss' : 'Net profit' }}</span>
                            <span class="text-sm font-bold {{ $netResult < 0 ? 'text-risk' : 'text-emerald-700' }}">{{ $netResult >= 0 ? '+' : '−' }}{{ $fmt(abs($netResult)) }}</span>
                        </div>
                        @if ($totalTargetIncome !== $totalIncome)
                            <div class="flex justify-between border-t border-line pt-1.5 text-micro"><span class="text-muted">Projected (at target)</span><span class="font-bold {{ $projectedNet < 0 ? 'text-risk' : 'text-navy-900' }}">{{ $projectedNet >= 0 ? '+' : '−' }}{{ $fmt(abs($projectedNet)) }}</span></div>
                        @endif
                    </div>
                </div>

                {{-- approval & versioning --}}
                @php
                    $bs = $event->budget_status ?? 'draft';
                    $bsMeta = ['draft' => ['Draft', 'bg-navy-100 text-navy-600'], 'pending' => ['Pending approval', 'bg-amber-100 text-amber-700'], 'approved' => ['Approved · locked', 'bg-emerald-100 text-emerald-700']];
                    [$bsLabel, $bsClass] = $bsMeta[$bs] ?? $bsMeta['draft'];
                    $approvedV = $versions->firstWhere('status', 'approved');
                @endphp
                <div class="border-b border-line p-4">
                    <p class="field-label !mb-2 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Approval</p>
                    <div class="mb-2 flex items-center gap-2">
                        <span class="rounded-full px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide {{ $bsClass }}">{{ $bsLabel }}</span>
                        @if ($bs === 'approved' && $approvedV)<span class="text-eyebrow text-muted">baseline v{{ $approvedV->version }}</span>@endif
                    </div>

                    @if ($bs === 'approved')
                        <p class="text-eyebrow leading-snug text-muted">🔒 Locked {{ $event->budget_locked_at?->format('j M') }}@if ($approvedV?->decider) · by {{ $approvedV->decider->name }}@endif.</p>
                        @if ($approvedTotal)
                            <div class="mt-2 flex justify-between text-xs"><span class="text-muted">Variance vs approved</span><span class="font-bold {{ $varianceVsApproved < 0 ? 'text-risk' : 'text-emerald-600' }}">{{ $varianceVsApproved >= 0 ? '+' : '−' }}{{ $fmt(abs($varianceVsApproved)) }}</span></div>
                        @endif
                        <button type="button" wire:click="reviseBudget" class="mt-2 h-9 w-full rounded-xl border border-gold-300 bg-gold-50/60 text-xs font-bold text-gold-700 transition hover:bg-gold-100">✎ Create revision</button>
                    @elseif ($bs === 'pending')
                        <p class="mb-2 text-eyebrow text-muted">Submitted — awaiting sign-off.</p>
                        @can('manage-budget')
                            <div class="flex gap-2">
                                <button type="button" wire:click="approveBudget" class="btn-navy h-9 flex-1 text-xs">✓ Approve</button>
                                <button type="button" wire:click="rejectBudget" class="h-9 flex-1 rounded-xl border border-line bg-white text-xs font-semibold text-navy-700 transition hover:border-risk/40 hover:text-risk">Reject</button>
                            </div>
                        @else
                            <p class="text-eyebrow text-muted">A manager signs this off.</p>
                        @endcan
                    @else
                        <input type="text" wire:model="approvalNote" maxlength="120" class="input mb-2 h-9 w-full text-sm" placeholder="Version note (optional)">
                        <button type="button" wire:click="submitForApproval" @disabled($items->isEmpty()) class="btn-gold h-9 w-full text-xs disabled:opacity-40">Submit for approval</button>
                    @endif

                    @if ($versions->isNotEmpty())
                        @php $vm = ['pending' => 'text-amber-600', 'approved' => 'text-emerald-600', 'rejected' => 'text-risk', 'superseded' => 'text-navy-400']; @endphp
                        <div class="mt-3 space-y-1 border-t border-line pt-2">
                            @foreach ($versions->take(4) as $v)
                                <div class="flex items-center justify-between text-eyebrow">
                                    <span class="text-muted">v{{ $v->version }} · <span class="font-bold {{ $vm[$v->status] ?? '' }}">{{ ucfirst($v->status) }}</span></span>
                                    <span class="text-navy-500">{{ $fmt($v->totals['grand'] ?? 0) }} · {{ $v->created_at->format('j M') }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- ══ what the modules put here, and what they could not ══ --}}
                @if ($linkedByModule->isNotEmpty() || $pendingFromModules)
                    <div class="border-t border-line p-4">
                        <p class="text-eyebrow font-bold uppercase tracking-[0.16em] text-navy-400">From the modules</p>

                        @foreach ($linkedByModule as $src => $m)
                            @php $meta = \App\Models\EventBudgetItem::SOURCES[$src] ?? ['A module', 'budget']; @endphp
                            <a href="{{ route('events.hub', [$event, 'tab' => $meta[1]]) }}" wire:navigate
                               class="mt-1.5 flex items-baseline gap-2 text-[11.5px] transition hover:text-indigo-600">
                                <span class="font-bold text-navy-700">{{ $meta[0] }}</span>
                                <span class="text-muted">{{ $m['n'] }} {{ str('line')->plural($m['n']) }}</span>
                                <span class="pf ms-auto font-black tabular-nums text-navy-900">{{ number_format($m['cents'] / 100, 2) }}</span>
                            </a>
                        @endforeach

                        {{-- The answer to "but I booked those rooms". Rooms held
                             with no rate are real and cannot be costed, so the
                             budget says so instead of quietly omitting them. --}}
                        @if ($pendingFromModules)
                            <div class="mt-2.5 rounded-xl bg-amber-50 p-2.5">
                                <p class="text-[11px] font-bold text-amber-800">
                                    {{ count($pendingFromModules) }} {{ str('commitment')->plural(count($pendingFromModules)) }} not in the budget
                                </p>
                                @foreach (collect($pendingFromModules)->take(4) as $p)
                                    <a href="{{ route('events.hub', [$event, 'tab' => $p['tab']]) }}" wire:navigate
                                       class="mt-1 block text-[11px] leading-snug text-amber-900/80 hover:underline">
                                        <span class="font-semibold">{{ $p['module'] }}</span> · {{ $p['what'] }}
                                    </a>
                                @endforeach
                                @if (count($pendingFromModules) > 4)
                                    <p class="mt-1 text-[10.5px] text-amber-900/60">…and {{ count($pendingFromModules) - 4 }} more.</p>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif

                {{-- actions --}}
                <div class="space-y-2 p-4">
                    @unless ($event->budgetLocked())
                        <button type="button" wire:click="newLine" class="btn-gold h-10 w-full text-xs">＋ Add Line</button>
                        <button type="button" wire:click="syncModules" class="h-9 w-full rounded-xl border border-gold-300 bg-gold-50/60 text-xs font-bold text-gold-700 transition hover:bg-gold-100" title="Re-read the modules. They also sync themselves whenever a booking changes.">↺ Sync modules @if ($syncedCount)· {{ $syncedCount }} linked @endif</button>
                        @if ($items->isEmpty())
                            <button type="button" wire:click="insertStarter" class="h-9 w-full rounded-xl border border-line bg-white text-xs font-semibold text-navy-700 transition hover:border-gold-300">✨ Prefill common lines</button>
                        @endif
                    @endunless
                    <a href="{{ route('events.budget.pdf', $event) }}" class="flex h-9 w-full items-center justify-center gap-1.5 rounded-xl border border-line bg-white text-xs font-semibold text-navy-700 transition hover:border-gold-300 {{ $items->isEmpty() ? 'pointer-events-none opacity-40' : '' }}">↧ Export PDF</a>
                    @if (! $event->budgetLocked() && ! $items->isEmpty())
                        <button type="button" wire:click="clearAllLines" wire:confirm="Delete ALL budget lines? This cannot be undone." class="h-9 w-full rounded-xl border border-line bg-white text-xs font-semibold text-navy-700 transition hover:border-risk/40 hover:text-risk">Clear all lines</button>
                    @endif
                    <p class="pt-0.5 text-center text-eyebrow text-muted">{{ $items->count() }} {{ str('line')->plural($items->count()) }} · {{ $sections->count() }} {{ str('section')->plural($sections->count()) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Add / Edit modal ══ --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit line' : 'New budget line'" max="2xl"
                 close="set('showForm', false)" wire:key="budget-modal">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="pf text-base font-bold text-navy-900">{{ $editingId ? 'Edit budget line' : 'New budget line' }}</h3>
                    <button type="button" wire:click="$set('showForm', false)" class="text-navy-400 hover:text-navy-900">✕</button>
                </div>

                <form wire:submit="save" class="grid gap-3.5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="field-label !mb-1 !text-eyebrow">Category</label>
                        <select wire:model="category" class="input h-10 text-sm">
                            @foreach ($categories as $c)<option value="{{ $c->name }}">{{ $c->name }}</option>@endforeach
                        </select>
                        @error('category') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                        <p class="mt-1 text-eyebrow text-muted">Add or rename categories directly on the ledger.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label !mb-1 !text-eyebrow">Description</label>
                        <input type="text" wire:model="description" class="input h-10 text-sm" placeholder="e.g. Main stage build">
                        @error('description') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Quantity</label>
                        <input type="number" min="1" wire:model="quantity" class="input h-10 text-sm">
                        @error('quantity') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Unit cost ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="unit" class="input h-10 text-sm" placeholder="0">
                        @error('unit') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Actual cost ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="actual" class="input h-10 text-sm" placeholder="0">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Paid to date ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="paid" class="input h-10 text-sm" placeholder="0">
                    </div>

                    {{-- ── What the client is charged ──
                         Leaving both blank is the normal case: the line falls
                         back to the event's management fee, which is what
                         every line did before this existed. --}}
                    <div class="sm:col-span-2 rounded-xl border border-line bg-page/40 p-3">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <p class="text-eyebrow font-bold uppercase tracking-wide text-navy-500">What the client is charged</p>
                            <label class="flex cursor-pointer items-center gap-1.5 text-eyebrow font-semibold text-navy-500">
                                <input type="checkbox" wire:model.live="billable" class="h-3.5 w-3.5 rounded border-navy-300 text-navy-900 focus:ring-gold-400">
                                Billable
                            </label>
                        </div>

                        @if ($billable)
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="field-label !mb-1 !text-eyebrow">Charge ({{ $event->currency }})</label>
                                    <input type="number" step="0.01" min="0" wire:model="sell" class="input h-10 text-sm" placeholder="quoted price">
                                    @error('sell') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="field-label !mb-1 !text-eyebrow">…or markup %</label>
                                    <input type="number" step="0.5" wire:model="markup" class="input h-10 text-sm" placeholder="{{ $event->management_fee_pct ?? 15 }}">
                                    @error('markup') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <p class="mt-1.5 text-eyebrow leading-snug text-muted">
                                Both blank charges cost plus the event's {{ rtrim(rtrim(number_format((float) ($event->management_fee_pct ?? 15), 2), '0'), '.') }}% management fee.
                                A charge typed here wins over a markup.
                            </p>
                        @else
                            <p class="text-eyebrow leading-snug text-muted">
                                This line is not on the client's invoice. It still costs — it comes out of your margin.
                            </p>
                        @endif
                    </div>

                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Vendor / supplier</label>
                        {{-- A name from the directory links the line to that
                             supplier; anything else is still allowed. --}}
                        <input type="text" wire:model="vendor" list="budget-vendors" class="input h-10 text-sm" placeholder="e.g. Prime AV">
                        <datalist id="budget-vendors">
                            @foreach ($vendorNames as $name)<option value="{{ $name }}">@endforeach
                        </datalist>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="field-label !mb-1 !text-eyebrow">Invoice #</label>
                            <input type="text" wire:model="invoice_number" class="input h-10 text-sm" placeholder="—">
                        </div>
                        <div>
                            <label class="field-label !mb-1 !text-eyebrow">Due date</label>
                            <input type="date" wire:model="due_on" class="input h-10 text-sm">
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label !mb-1 !text-eyebrow">Notes</label>
                        <input type="text" wire:model="notes" class="input h-10 text-sm" placeholder="Optional notes">
                    </div>

                    <div class="mt-1 flex items-center justify-between sm:col-span-2">
                        <p class="text-xs text-muted">Budgeted total: <span class="font-bold text-navy-900">{{ $event->currencySymbol() }}{{ number_format((float) ($unit ?: 0) * max(1, (int) $quantity), 0) }}</span></p>
                        <div class="flex gap-2">
                            <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                            <button type="submit" class="btn-navy h-10 px-6 text-xs">{{ $editingId ? 'Update line' : 'Add line' }}</button>
                        </div>
                    </div>
                </form>
        </x-modal>
    @endif

    {{-- ══ Income modal ══ --}}
    @if ($showIncomeForm)
        <x-modal title="Income line" max="md"
                 close="set('showIncomeForm', false)" wire:key="income-modal">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="pf text-base font-bold text-navy-900">{{ $editingIncomeId ? 'Edit income' : 'New income' }}</h3>
                    <button type="button" wire:click="$set('showIncomeForm', false)" class="text-navy-400 hover:text-navy-900">✕</button>
                </div>
                <form wire:submit="saveIncome" class="grid gap-3.5">
                    <p class="text-eyebrow text-muted">Sponsorship &amp; exhibition income are pulled automatically from those modules — add tickets, grants and other income here.</p>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Source</label>
                        <select wire:model="incomeSource" class="input h-10 text-sm">
                            @foreach (\App\Support\Taxonomy::options('income_source') as $key => $lbl)<option value="{{ $key }}">{{ $lbl }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Description</label>
                        <input type="text" wire:model="incomeDesc" class="input h-10 text-sm" placeholder="e.g. 300 delegate tickets">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="field-label !mb-1 !text-eyebrow">Amount ({{ $event->currency }})</label>
                            <input type="number" step="0.01" min="0" wire:model="incomeAmount" class="input h-10 text-sm" placeholder="0">
                            @error('incomeAmount') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="field-label !mb-1 !text-eyebrow">Status</label>
                            <select wire:model="incomeStatus" class="input h-10 text-sm">
                                @foreach (\App\Models\EventIncomeItem::STATUSES as $s)<option value="{{ $s }}">{{ ucfirst($s) }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showIncomeForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                        <button type="submit" class="btn-navy h-10 px-6 text-xs">{{ $editingIncomeId ? 'Update' : 'Add income' }}</button>
                    </div>
                </form>
        </x-modal>
    @endif

    @script
    <script>
        const initCatSort = () => {
            const el = document.querySelector('[data-cat-sort]');
            if (! el || el._sortable) return;
            el._sortable = window.Sortable.create(el, {
                handle: '.cat-drag',
                draggable: '[data-cat]',
                animation: 160,
                ghostClass: 'opacity-40',
                onEnd: () => {
                    const ids = [...el.querySelectorAll('[data-cat]')].map((n) => n.dataset.cat);
                    $wire.reorderCategories(ids);
                },
            });
        };
        initCatSort();
        Livewire.hook('morph.updated', () => initCatSort());
    </script>
    @endscript
</div>
