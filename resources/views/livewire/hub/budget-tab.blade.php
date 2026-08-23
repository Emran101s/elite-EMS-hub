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
        ['bg-navy-900/10', 'text-navy-900'], ['bg-gold-200/40', 'text-gold-800'], ['bg-success-soft', 'text-success-ink'],
        ['bg-warning-soft', 'text-warning-ink'], ['bg-danger-soft', 'text-danger-ink'], ['bg-info-soft', 'text-info-ink'],
        ['bg-gold-50', 'text-gold-700'], ['bg-page', 'text-muted'], ['bg-gold-50/50', 'text-gold-800'],
    ];
    $catIcons = ['users', 'building', 'clipboard', 'grid', 'star', 'truck', 'archive', 'currency', 'chart'];
@endphp

<div>
    {{-- The old Budget headline strip (ring + total/forecast/paid/remaining
         figures, in a dark x-module-head) is retired here — the Event Hub's
         new Universal Module Header shows the equivalent forecast/committed/
         cap numbers above this component now, so showing both was two
         headers for one module. $usedPct/$paidPct/$remaining (computed
         above) stay: the sidebar's own "Summary" + "Profit & loss" panels
         further down this page (a fuller version of what Overview's old
         Budget Summary card showed — spend bar, P&L, cost/attendee) read
         them, so that content never needed relocating here, only trusting
         to already exist.

         Price mode is the one exception the Universal Module Header can't
         cover — it's a sell-side question ("what is this charged at")
         the header's forecast/committed/cap figures don't answer, and
         nothing else on this page shows it. Kept, in the new glass style
         instead of the old dark strip, only while Price mode is active. --}}
    @if ($price)
        @php $sellSummary = $event->sellSummary(); @endphp
        <div class="ehc-price-banner">
            <div class="ehc-price-banner-stat">
                <span class="ehc-price-banner-label">Cost to us</span>
                <span class="ehc-price-banner-value">{{ $fmt($sellSummary['cost']) }}</span>
            </div>
            <div class="ehc-price-banner-stat">
                <span class="ehc-price-banner-label">Charged to client</span>
                <span class="ehc-price-banner-value">{{ $fmt($sellSummary['sell']) }}</span>
            </div>
            <div class="ehc-price-banner-stat">
                <span class="ehc-price-banner-label">Gross margin</span>
                <span class="ehc-price-banner-value" style="color: {{ $sellSummary['margin'] < 0 ? 'var(--color-danger)' : 'var(--color-success)' }}">
                    {{ $sellSummary['margin'] >= 0 ? '' : '−' }}{{ $fmt(abs($sellSummary['margin'])) }}
                </span>
            </div>
            <div class="ehc-price-banner-stat">
                <span class="ehc-price-banner-label">Margin</span>
                <span class="ehc-price-banner-value">{{ $sellSummary['marginPct'] ?? '—' }}{{ $sellSummary['marginPct'] !== null ? '%' : '' }}</span>
            </div>
            <p class="ehc-price-banner-note">
                <strong>How it was priced:</strong>
                {{ $sellSummary['priced'] }} of {{ $sellSummary['lines'] }} {{ str('line')->plural($sellSummary['lines']) }} priced by hand.
                The rest charge cost plus the {{ rtrim(rtrim(number_format($sellSummary['fee'], 2), '0'), '.') }}% management fee.
                @if ($sellSummary['absorbed'] > 0)
                    {{ $fmt($sellSummary['absorbed']) }} absorbed.
                @endif
            </p>
        </div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
        {{-- ══════════ MAIN · ledger ══════════ --}}
        <div class="min-w-0">
            @if ($event->budgetLocked())
                <div class="mb-3 flex items-center gap-2.5 rounded-xl border border-success/30 bg-success-soft/60 px-3 py-2">
                    <x-icon name="check" class="h-4 w-4 shrink-0 text-success-ink" />
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold text-success-ink">Approved — locked baseline</p>
                        <p class="text-eyebrow text-success-ink/80">Actuals still track; revise to change the plan.</p>
                    </div>
                    <button type="button" wire:click="reviseBudget" class="shrink-0 rounded-lg border border-success/40 bg-white px-2.5 py-1 text-eyebrow font-bold text-success-ink transition hover:bg-success-soft">Create revision</button>
                </div>
            @elseif (($event->budget_status ?? 'draft') === 'pending')
                <div class="mb-3 flex items-center gap-2.5 rounded-xl border border-warning/30 bg-warning-soft/60 px-3 py-2">
                    <x-icon name="clock" class="h-4 w-4 shrink-0 text-warning-ink" />
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold text-warning-ink">Pending approval</p>
                        <p class="text-eyebrow text-warning-ink/80">Approve or reject from the Approval panel.</p>
                    </div>
                </div>
            @endif
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

                {{-- ══ builder toolbar ══ --}}
                @unless ($event->budgetLocked())
                    <div class="mb-2.5 flex flex-wrap items-center gap-1.5">
                        <div class="flex items-center gap-1.5">
                            <input type="text" data-newcat wire:model="newCategoryName" wire:keydown.enter="addCategory" maxlength="60" class="h-9 w-40 rounded-lg border border-line bg-white px-2.5 text-xs text-ink focus:border-navy-300 focus:outline-none" placeholder="New category…">
                            <button type="button" wire:click="addCategory" class="btn-navy h-9 px-3 text-xs"><span class="text-gold-200">＋</span> Category</button>
                        </div>
                        <button type="button" wire:click="newLine" class="h-9 rounded-xl border border-line bg-white px-3 text-xs font-semibold text-ink transition hover:border-gold-300">＋ Line</button>
                        <div class="ml-auto flex items-center gap-1 text-eyebrow font-bold uppercase tracking-wide text-muted">
                            <button type="button" wire:click="expandAll" class="rounded-lg px-2 py-1 hover:bg-page hover:text-ink">Expand</button>
                            <button type="button" wire:click="collapseAll" class="rounded-lg px-2 py-1 hover:bg-page hover:text-ink">Collapse</button>
                        </div>
                    </div>
                    @error('newCategoryName') <p class="mb-2 text-micro font-semibold text-danger-ink">{{ $message }}</p> @enderror
                @endunless

                <x-bulk-bar :count="$this->selectedCount()" noun="line" />

                <div class="rounded-lg border border-line bg-white overflow-hidden">
                    <div class="overflow-x-auto">
                        <div class="{{ $track || $price ? 'min-w-[680px]' : 'min-w-[380px]' }}">
                            {{-- column labels --}}
                            <div class="flex items-center gap-2 border-b border-line bg-page/40 px-3 py-1.5 text-eyebrow font-bold uppercase tracking-wide text-muted">
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
                                    <div class="flex items-center gap-2 px-3 py-1.5">
                                        @if ($isRenaming)
                                            <span class="shrink-0 text-eyebrow text-muted">▶</span>
                                            <input type="text" wire:model="categoryEditName" wire:keydown.enter="saveCategoryName" maxlength="60" class="h-8 min-w-0 flex-1 rounded-lg border border-line bg-white px-2.5 text-sm font-bold text-ink focus:border-navy-300 focus:outline-none">
                                            <button type="button" wire:click="saveCategoryName" class="shrink-0 rounded-lg bg-navy-900 px-2.5 py-1 text-eyebrow font-bold text-white">Save</button>
                                            <button type="button" wire:click="cancelRenameCategory" class="shrink-0 text-eyebrow font-semibold text-muted hover:text-ink">Cancel</button>
                                        @else
                                            @if ($section['id'] && ! $event->budgetLocked())
                                                <span class="cat-drag hidden shrink-0 cursor-grab select-none text-micro leading-none text-muted hover:text-muted active:cursor-grabbing sm:inline" title="Drag to reorder">⠿</span>
                                            @endif
                                            <button type="button" wire:click="toggleCollapse('{{ $section['key'] }}')" class="flex min-w-0 flex-1 items-center gap-2.5 text-left">
                                                <span class="shrink-0 text-eyebrow text-muted transition-transform {{ $isOpen ? 'rotate-90' : '' }}">▶</span>
                                                <span class="w-4 shrink-0 text-center text-eyebrow font-bold text-muted">{{ $loop->iteration }}</span>
                                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ $cbg }} {{ $cfg }}"><x-icon :name="$cicon" class="h-3.5 w-3.5" /></span>
                                                <span class="truncate text-sm font-bold text-ink">{{ $section['name'] }}</span>
                                                <span class="shrink-0 rounded-full bg-page px-2 py-0.5 text-eyebrow font-bold text-muted">{{ $secItems->count() }} {{ str('item')->plural($secItems->count()) }}</span>
                                                @if ($catFlagged)<span class="shrink-0 text-micro text-danger">⚑</span>@endif
                                            </button>
                                            @unless ($event->budgetLocked())
                                                <span class="hidden shrink-0 items-center gap-1 group-hover/cat:flex">
                                                    @if ($price)
                                                        {{-- Pricing line by line is right when a line has its
                                                             own deal; most of the time a category is one
                                                             decision. Lines already quoted are left alone. --}}
                                                        @foreach ([10, 15, 20, 25] as $pct)
                                                            <button type="button" wire:click="markupCategory('{{ $catArg }}', {{ $pct }})"
                                                                    class="rounded bg-page px-1.5 py-0.5 text-eyebrow font-bold text-muted hover:bg-gold-50 hover:text-gold-800"
                                                                    title="Charge every unquoted line in {{ $section['name'] }} at cost plus {{ $pct }}%">+{{ $pct }}%</button>
                                                        @endforeach
                                                        <x-confirm title="Put every line in “{{ $section['name'] }}” back on the {{ rtrim(rtrim(number_format($feePct, 2), '0'), '.') }}% management fee?"
                                                                body="Any prices typed by hand are cleared."
                                                                confirm="Clear" tone="warn"
                                                                run="$wire.clearCategoryPricing('{{ $catArg }}')"
                                                                class="rounded bg-page px-1.5 py-0.5 text-eyebrow font-bold text-muted hover:bg-line">↺</x-confirm>
                                                    @endif
                                                    <button type="button" wire:click="newLine('{{ $catArg }}')" class="rounded bg-gold-50 px-1.5 py-0.5 text-eyebrow font-bold text-gold-800 hover:bg-gold-100" title="Add line">＋ line</button>
                                                    @if ($section['id'])
                                                        <button type="button" wire:click="startRenameCategory({{ $section['id'] }})" class="rounded bg-page px-1.5 py-0.5 text-eyebrow font-bold text-muted hover:bg-line" title="Rename category">✎</button>
                                                        <x-confirm title="Delete “{{ $section['name'] }}”?"
                                                                   body="Any lines move to another category."
                                                                   confirm="Delete"
                                                                   run="$wire.deleteCategory({{ $section['id'] }})"
                                                                   class="rounded bg-danger-soft px-1.5 py-0.5 text-eyebrow font-bold text-danger hover:bg-danger/20">✕</x-confirm>
                                                    @endif
                                                </span>
                                            @endunless
                                            <button type="button" wire:click="newLine('{{ $catArg }}')" class="shrink-0 text-xs font-bold text-gold-700 hover:text-gold-800 group-hover/cat:hidden" title="Add line to this category">＋</button>
                                            @php $catShown = $price ? $secItems->sum(fn ($i) => $i->costCents()) : $catEst; @endphp
                                            <span class="{{ $money }} text-xs font-bold text-ink">{{ $catShown ? $fmt($catShown) : '—' }}</span>
                                            @if ($price)
                                                @php
                                                    $catCost = $secItems->sum(fn ($i) => $i->costCents());
                                                    $catSell = $secItems->sum(fn ($i) => $i->sellCents($feePct));
                                                    $catMargin = $catSell - $catCost;
                                                    $catMarginPct = $catSell > 0 ? (int) round($catMargin / $catSell * 100) : null;
                                                @endphp
                                                <span class="{{ $money }} text-xs font-bold text-ink">{{ $catSell ? $fmt($catSell) : '—' }}</span>
                                                <span class="{{ $money }} text-xs font-bold {{ $catMargin < 0 ? 'text-danger-ink' : 'text-success-ink' }}">{{ $catCost || $catSell ? ($catMargin >= 0 ? '+' : '−').$fmt(abs($catMargin)) : '—' }}</span>
                                                <span class="w-12 shrink-0 text-right text-xs font-bold {{ $catMarginPct === null ? 'text-muted' : ($catMarginPct < 0 ? 'text-danger-ink' : 'text-muted') }}">{{ $catMarginPct === null ? '—' : $catMarginPct.'%' }}</span>
                                            @elseif ($track)
                                                @php $secCosted = $secItems->filter->hasActual(); $secSaved = $secCosted->sum(fn ($i) => $i->varianceCents()); @endphp
                                                <span class="{{ $money }} text-xs font-bold {{ $catAct > $catEst && $catEst > 0 ? 'text-danger-ink' : 'text-ink' }}">{{ $catAct ? $fmt($catAct) : '—' }}</span>
                                                <span class="{{ $money }} text-xs font-bold text-success-ink">{{ $catPaid ? $fmt($catPaid) : '—' }}</span>
                                                <span class="{{ $money }} text-xs font-bold {{ $secCosted->isEmpty() ? 'text-muted' : ($secSaved < 0 ? 'text-danger-ink' : 'text-success-ink') }}">{{ $secCosted->isEmpty() ? '—' : ($secSaved >= 0 ? '+' : '−').$fmt(abs($secSaved)) }}</span>
                                            @endif
                                        @endif
                                    </div>
                                    {{-- spend bar (track only) --}}
                                    @if ($track && $catEst > 0 && ! $isRenaming)
                                        <div class="px-3 pb-2">
                                            <div class="flex h-1 overflow-hidden rounded-full bg-page">
                                                <div class="bg-success" style="width: {{ $paidPctCat }}%"></div>
                                                <div class="bg-warning" style="width: {{ max(0, $spendPct - $paidPctCat) }}%"></div>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                {{-- ── line items (collapsible) ── --}}
                                @if ($isOpen)
                                    @forelse ($secItems as $item)
                                        <div wire:key="bi-{{ $item->id }}" class="group/line relative flex items-center gap-2 border-b border-line px-3 py-1.5 pl-9 last:border-0 hover:bg-page/40 {{ $this->isSelected($item->id) ? 'bg-page/60' : 'bg-page/[0.15]' }}">
                                            @unless ($item->isLinked())
                                                <button type="button" wire:click.stop="toggleSelect({{ $item->id }})" class="flex h-4 w-4 shrink-0 items-center justify-center rounded border text-eyebrow {{ $this->isSelected($item->id) ? 'border-navy-900 bg-navy-900 text-white' : 'border-line text-transparent hover:border-muted' }}" title="Select">✓</button>
                                            @endunless
                                            <button type="button" wire:click="toggleFlag({{ $item->id }})" class="shrink-0 text-micro {{ $item->flagged ? 'text-danger' : 'text-muted hover:text-muted' }}" title="{{ $item->flagged ? 'Flagged — click to unflag' : 'Flag as priority' }}">{{ $item->flagged ? '⚑' : '⚐' }}</button>
                                            @php
                                                $bits = [];
                                                if ($item->quantity > 1 || $item->unit_cents) $bits[] = $item->quantity.' × '.$fmt($item->unit_cents);
                                                if ($item->vendor) $bits[] = $item->vendor;
                                                if ($item->invoice_number) $bits[] = $item->invoice_number;
                                                if ($item->due_on) $bits[] = 'due '.$item->due_on->format('j M');
                                            @endphp
                                            <button type="button" wire:click="editLine({{ $item->id }})" @disabled($event->budgetLocked() || $item->isLinked()) class="group/edit min-w-0 flex-1 text-left" title="{{ $item->isLinked() ? 'Synced — edit in the '.\Illuminate\Support\Str::headline($item->linkedTab()).' tab' : 'Click to edit this line' }}">
                                                <p class="flex items-center gap-1.5 truncate text-xs font-medium text-ink group-hover/edit:text-gold-800">
                                                    @if ($item->isLinked())<span class="shrink-0 rounded bg-page px-1 text-eyebrow font-bold uppercase tracking-wide text-muted" title="Synced from module">🔗 {{ $item->linkedTab() === 'transportation' ? 'transport' : $item->linkedTab() }}</span>@endif
                                                    <span class="truncate">{{ $item->description ?? $item->categoryLabel() }}</span>
                                                </p>
                                                @if ($item->isLinked())
                                                    <p class="truncate text-eyebrow text-muted">Synced from {{ \Illuminate\Support\Str::headline($item->linkedTab()) }}@if ($item->vendor) · {{ $item->vendor }}@endif</p>
                                                @elseif ($bits)
                                                    <p class="truncate text-eyebrow text-muted">{{ implode(' · ', $bits) }}</p>
                                                @else
                                                    <p class="truncate text-eyebrow text-gold-700/70 group-hover/edit:text-gold-800">Click to set quantity &amp; unit cost →</p>
                                                @endif
                                            </button>
                                            @php $lineCostShown = $price ? $item->costCents() : $item->estimated_cents; @endphp
                                            <button type="button" wire:click="editLine({{ $item->id }})" @disabled($event->budgetLocked() || $item->isLinked()) class="{{ $money }} text-micro font-semibold {{ $lineCostShown ? 'text-ink' : 'text-gold-700 hover:text-gold-800' }}">{{ $lineCostShown ? $fmt($lineCostShown) : '＋ set' }}</button>
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
                                                        class="{{ $money }} text-micro font-semibold {{ $item->billable === false ? 'text-muted line-through' : 'text-ink' }}"
                                                        title="{{ $how ? 'Priced by hand ('.$how.')' : 'Charged at the '.$feePct.'% management fee' }}">
                                                    {{ $item->billable === false ? '—' : $fmt($lineSell) }}
                                                    @if ($how)<span class="ms-0.5 text-[9px] font-bold text-gold-700">{{ $how }}</span>@endif
                                                </button>
                                                <span class="{{ $money }} text-micro font-semibold {{ $lineMargin < 0 ? 'text-danger-ink' : 'text-success-ink' }}">{{ ($lineMargin >= 0 ? '+' : '−').$fmt(abs($lineMargin)) }}</span>
                                                <span class="w-12 shrink-0 text-right text-micro font-semibold {{ $linePct === null ? 'text-muted' : ($linePct < 0 ? 'text-danger-ink' : 'text-muted') }}">{{ $linePct === null ? '—' : $linePct.'%' }}</span>
                                            @elseif ($track)
                                                @php $lineSaved = $item->actual_cents > 0 ? $item->estimated_cents - $item->actual_cents : null; @endphp
                                                <span class="{{ $money }} text-micro font-semibold {{ $item->actual_cents > $item->estimated_cents && $item->estimated_cents > 0 ? 'text-danger-ink' : 'text-ink' }}">{{ $item->actual_cents ? $fmt($item->actual_cents) : '—' }}</span>
                                                <span class="{{ $money }} text-micro font-semibold text-success-ink">{{ $item->paid_cents ? $fmt($item->paid_cents) : '—' }}</span>
                                                <span class="{{ $money }} text-micro font-semibold {{ $lineSaved === null ? 'text-muted' : ($lineSaved < 0 ? 'text-danger-ink' : 'text-success-ink') }}">{{ $lineSaved === null ? '—' : ($lineSaved >= 0 ? '+' : '−').$fmt(abs($lineSaved)) }}</span>
                                            @endif
                                            {{-- hover actions --}}
                                            <div class="absolute right-2 top-1/2 hidden -translate-y-1/2 items-center gap-1 rounded-lg border border-line bg-white px-1 py-0.5 shadow-sm group-hover/line:flex">
                                                @if ($track && $item->payment_status !== 'paid')
                                                    <button type="button" wire:click="markPaid({{ $item->id }})" class="rounded bg-success-soft px-1.5 py-0.5 text-eyebrow font-bold text-success-ink hover:bg-success/20" title="Mark fully paid">✓</button>
                                                @endif
                                                @if ($item->isLinked())
                                                    <a href="{{ route('events.hub', [$event, 'tab' => $item->linkedTab()]) }}" class="rounded bg-page px-1.5 py-0.5 text-eyebrow font-bold text-muted hover:bg-line" title="Edit in the {{ \Illuminate\Support\Str::headline($item->linkedTab()) }} tab">↗ source</a>
                                                @else
                                                    <button type="button" wire:click="duplicateLine({{ $item->id }})" class="rounded bg-page px-1.5 py-0.5 text-eyebrow font-bold text-muted hover:bg-line" title="Duplicate">⧉</button>
                                                    <button type="button" wire:click="editLine({{ $item->id }})" class="rounded bg-page px-1.5 py-0.5 text-eyebrow font-bold text-muted hover:bg-line" title="Edit">✎</button>
                                                    <x-confirm title="Delete this line?"
                                                               confirm="Delete"
                                                               run="$wire.deleteLine({{ $item->id }})"
                                                               class="rounded bg-danger-soft px-1.5 py-0.5 text-eyebrow font-bold text-danger hover:bg-danger/20">✕</x-confirm>
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <button type="button" wire:click="newLine('{{ $catArg }}')" @disabled($event->budgetLocked()) class="flex w-full items-center gap-2 border-b border-line bg-page/[0.15] px-3 py-2 pl-9 text-left text-micro text-muted transition hover:bg-page/40 hover:text-gold-800 disabled:opacity-50">
                                            <span class="text-gold-800">＋</span> Add a line to {{ $section['name'] }}
                                        </button>
                                    @endforelse
                                    @if ($secItems->isNotEmpty() && ! $event->budgetLocked())
                                        <button type="button" wire:click="newLine('{{ $catArg }}')" class="flex w-full items-center gap-1.5 border-b border-line bg-page/[0.15] px-3 py-1.5 pl-9 text-left text-micro font-semibold text-gold-700 transition hover:bg-gold-50/60 hover:text-gold-800">
                                            <span>＋</span> Add Line Item
                                        </button>
                                    @endif
                                @endif
                                </div>{{-- /cat-block --}}
                            @endforeach
                            </div>{{-- /data-cat-sort --}}

                            {{-- ── add a category ── --}}
                            @unless ($event->budgetLocked())
                                <button type="button" x-on:click="document.querySelector('[data-newcat]')?.focus()" class="flex w-full items-center gap-1.5 border-b border-line bg-page/30 px-3 py-2.5 text-left text-micro font-bold text-muted transition hover:bg-page/60 hover:text-ink">
                                    <span class="text-gold-800">＋</span> Add New Category
                                </button>
                            @endunless

                            {{-- ── totals ── --}}
                            <div class="flex items-center gap-2 border-t border-line bg-white px-3 py-2">
                                <span class="flex-1 text-eyebrow font-bold uppercase tracking-wide text-muted">Subtotal</span>
                                <span class="{{ $money }} text-xs font-bold text-ink">{{ $fmt($estimatedTotal) }}</span>
                                @if ($track)
                                    <span class="{{ $money }} text-xs font-bold text-ink">{{ $actualTotal ? $fmt($actualTotal) : '—' }}</span>
                                    <span class="{{ $money }} text-xs font-bold text-success-ink">{{ $paidTotal ? $fmt($paidTotal) : '—' }}</span>
                                    <span class="{{ $money }} text-xs font-bold {{ ! $hasActuals ? 'text-muted' : ($savedTotal < 0 ? 'text-danger-ink' : 'text-success-ink') }}">{{ ! $hasActuals ? '—' : ($savedTotal >= 0 ? '+' : '−').$fmt(abs($savedTotal)) }}</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 border-t border-line bg-gold-50/40 px-3 py-2">
                                <span class="flex min-w-0 flex-1 items-center gap-1.5">
                                    <span class="shrink-0 text-micro text-gold-800">⚑</span>
                                    <span class="truncate text-xs font-bold text-ink">Management fee</span>
                                    <span class="inline-flex shrink-0 items-center rounded-md border border-gold-300 bg-white px-1 text-eyebrow font-bold text-gold-800">
                                        <input type="number" min="0" max="100" step="0.5" wire:model.live.debounce.500ms="feePct" class="w-7 bg-transparent text-center focus:outline-none">%
                                    </span>
                                </span>
                                <span class="{{ $money }} text-xs font-bold text-gold-800">{{ $fmt($feeEst) }}</span>
                                @if ($track)
                                    <span class="{{ $money }} text-xs font-bold text-gold-800">{{ $feeAct ? $fmt($feeAct) : '—' }}</span>
                                    <span class="{{ $money }}"></span>
                                    <span class="{{ $money }}"></span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 border-t-2 border-ink/10 bg-page/50 px-3 py-2.5">
                                <span class="flex-1 text-xs font-bold uppercase tracking-wide text-ink">Grand total <span class="hidden font-normal normal-case text-muted sm:inline">(incl. {{ rtrim(rtrim(number_format($feePct, 2), '0'), '.') }}%)</span></span>
                                <span class="{{ $money }} text-sm font-bold text-ink">{{ $fmt($grandEst) }}</span>
                                @if ($track)
                                    <span class="{{ $money }} text-sm font-bold {{ $grandAct > $grandEst && $grandEst > 0 ? 'text-danger-ink' : 'text-ink' }}">{{ $grandAct ? $fmt($grandAct) : '—' }}</span>
                                    <span class="{{ $money }} text-sm font-bold text-success-ink">{{ $paidTotal ? $fmt($paidTotal) : '—' }}</span>
                                    <span class="{{ $money }} text-sm font-bold {{ ! $hasActuals ? 'text-muted' : ($savedTotal < 0 ? 'text-danger-ink' : 'text-success-ink') }}">{{ ! $hasActuals ? '—' : ($savedTotal >= 0 ? '+' : '−').$fmt(abs($savedTotal)) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
        </div>

        {{-- ══════════ RIGHT · Budget Control Center ══════════ --}}
        <div class="xl:sticky xl:top-4 xl:h-fit">
            <div class="rounded-lg border border-line bg-white overflow-hidden">
                {{-- control-center header --}}
                <div class="relative flex items-center gap-2 border-b border-line bg-page/60 px-4 py-3 text-ink">
                    <span class="relative flex h-7 w-7 items-center justify-center rounded-lg text-white shadow-sm" style="background: {{ \App\Models\Event::moduleColor('budget') }}">
                        <x-icon name="currency" class="h-3.5 w-3.5" />
                    </span>
                    <span class="relative text-2xs font-bold uppercase tracking-[0.18em] text-ink">Budget Control Center</span>
                    <a href="{{ route('events.budget.pdf', $event) }}" class="relative ml-auto flex items-center gap-1 rounded-lg border border-line bg-white px-2 py-1 text-3xs font-bold text-muted transition hover:border-gold-300 hover:text-gold-800 {{ $items->isEmpty() ? 'pointer-events-none opacity-40' : '' }}">↧ PDF</a>
                </div>

                {{-- mode --}}
                <div class="border-b border-line p-3">
                    <p class="mb-1.5 flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.12em] text-muted"><span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Mode</p>
                    <div class="flex rounded-xl border border-line bg-page/40 p-0.5">
                        <button type="button" wire:click="$set('view', 'build')" class="flex-1 rounded-lg py-1.5 text-eyebrow font-bold transition {{ $view === 'build' ? 'bg-navy-900 text-white' : 'text-muted hover:text-ink' }}">Build</button>
                        <button type="button" wire:click="$set('view', 'track')" class="flex-1 rounded-lg py-1.5 text-eyebrow font-bold transition {{ $view === 'track' ? 'bg-navy-900 text-white' : 'text-muted hover:text-ink' }}">Track</button>
                        <button type="button" wire:click="$set('view', 'price')" class="flex-1 rounded-lg py-1.5 text-eyebrow font-bold transition {{ $view === 'price' ? 'bg-navy-900 text-white' : 'text-muted hover:text-ink' }}">Price</button>
                    </div>
                    <p class="mt-1.5 text-eyebrow leading-snug text-muted">{{ match ($view) {
                        'build' => 'Plan quantity × unit estimates.',
                        'track' => 'Budget vs actual & paid.',
                        'price' => 'Cost to you vs charged to client.',
                    } }}</p>
                </div>

                {{-- total budget + fee + currency --}}
                <div class="border-b border-line p-3">
                    <p class="mb-1.5 flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.12em] text-muted"><span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Total budget</p>
                    <div class="flex items-center gap-1.5">
                        <span class="text-lg font-bold text-muted">{{ $event->currencySymbol() }}</span>
                        <input type="number" min="0" step="1000" wire:model.live.debounce.500ms="budgetCap" class="h-10 flex-1 rounded-lg border border-line bg-white px-2.5 text-base font-bold text-ink focus:border-navy-300 focus:outline-none" placeholder="0">
                        <span class="text-eyebrow font-semibold text-muted">{{ $event->currency }}</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between rounded-lg bg-gold-50/50 px-2.5 py-1.5">
                        <span class="text-eyebrow font-semibold text-muted">Management fee</span>
                        <span class="inline-flex items-center rounded-md border border-gold-300 bg-white px-1.5 text-xs font-bold text-gold-800">
                            <input type="number" min="0" max="100" step="0.5" wire:model.live.debounce.500ms="feePct" class="w-8 bg-transparent text-center focus:outline-none">%
                        </span>
                    </div>
                    <div class="mt-1.5 flex items-center justify-between rounded-lg bg-page/60 px-2.5 py-1.5 text-eyebrow">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-ink">≈ {{ $conv($grandEst) }}</p>
                            <p class="truncate text-muted">1 {{ $event->currency }} = {{ rtrim(rtrim(number_format($fxRate, 4), '0'), '.') }} {{ $fxOther }} <span class="rounded-full px-1 text-eyebrow font-bold uppercase {{ $fxLive ? 'bg-success-soft text-success-ink' : 'bg-page text-muted' }}">{{ $fxLive ? 'live' : 'pegged' }}</span></p>
                        </div>
                        <button type="button" wire:click="refreshRate" class="shrink-0 text-gold-700 hover:text-gold-800" title="Refresh rate">↻</button>
                    </div>
                </div>

                {{-- summary readout --}}
                <div class="border-b border-line p-3">
                    <p class="mb-1.5 flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.12em] text-muted"><span class="h-1.5 w-1.5 rounded-full bg-navy-900"></span> Summary</p>
                    <div class="mb-1 flex justify-between text-eyebrow font-semibold text-muted">
                        <span>{{ $fmt($grandForecast) }} of {{ $fmt($cap) }}</span>
                        <span class="{{ $usedPct >= 100 ? 'text-danger-ink' : 'text-ink' }}">{{ $usedPct }}%</span>
                    </div>
                    <div class="flex h-1.5 overflow-hidden rounded-full bg-page">
                        <div class="bg-success" style="width: {{ $paidPct }}%"></div>
                        <div class="bg-warning" style="width: {{ max(0, $usedPct - $paidPct) }}%"></div>
                    </div>
                    <div class="mt-2 space-y-1 text-xs">
                        <div class="flex justify-between"><span class="text-muted">Grand budget</span><span class="font-bold text-ink">{{ $fmt($grandEst) }}</span></div>
                        @if ($track)
                            <div class="flex justify-between"><span class="text-muted">Actual</span><span class="font-bold {{ $grandAct > $grandEst && $grandEst > 0 ? 'text-danger-ink' : 'text-ink' }}">{{ $grandAct ? $fmt($grandAct) : '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-muted">Paid</span><span class="font-bold text-success-ink">{{ $paidTotal ? $fmt($paidTotal) : '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-muted">{{ $savedTotal < 0 ? 'Over budget' : 'Saved' }}</span><span class="font-bold {{ ! $hasActuals ? 'text-muted' : ($savedTotal < 0 ? 'text-danger-ink' : 'text-success-ink') }}">{{ ! $hasActuals ? '—' : ($savedTotal >= 0 ? '+' : '−').$fmt(abs($savedTotal)) }}</span></div>
                        @endif
                        <div class="flex justify-between border-t border-line pt-1"><span class="text-muted">{{ $remaining < 0 ? 'Over budget' : 'Remaining' }}</span><span class="font-bold {{ $remaining < 0 ? 'text-danger-ink' : 'text-ink' }}">{{ $fmt($remaining) }}</span></div>
                        @if ($costPerHead !== null)
                            <div class="flex justify-between"><span class="text-muted">Cost / attendee <span class="text-muted">· {{ number_format($heads) }} pax</span></span><span class="font-bold text-ink">{{ $fmt($costPerHead) }}</span></div>
                        @endif
                    </div>
                </div>

                {{-- profit & loss --}}
                <div class="border-b border-line p-3">
                    <p class="mb-1.5 flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.12em] text-muted"><span class="h-1.5 w-1.5 rounded-full bg-success"></span> Profit &amp; loss</p>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between"><span class="text-muted">Income (actual)</span><span class="font-bold text-success-ink">{{ $fmt($totalIncome) }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Cost to deliver</span><span class="font-bold text-ink">{{ $fmt($costToDeliver) }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Charged to client</span><span class="font-bold text-ink">{{ $fmt($grandForecast) }}</span></div>
                        <div class="flex items-center justify-between rounded-lg px-2 py-1 {{ $netResult < 0 ? 'bg-danger-soft' : 'bg-success-soft' }}">
                            <span class="text-eyebrow font-bold uppercase tracking-wide {{ $netResult < 0 ? 'text-danger-ink' : 'text-success-ink' }}">{{ $netResult < 0 ? 'Net loss' : 'Net profit' }}</span>
                            <span class="text-sm font-bold {{ $netResult < 0 ? 'text-danger-ink' : 'text-success-ink' }}">{{ $netResult >= 0 ? '+' : '−' }}{{ $fmt(abs($netResult)) }}</span>
                        </div>
                        @if ($totalTargetIncome !== $totalIncome)
                            <div class="flex justify-between border-t border-line pt-1 text-micro"><span class="text-muted">Projected (at target)</span><span class="font-bold {{ $projectedNet < 0 ? 'text-danger-ink' : 'text-ink' }}">{{ $projectedNet >= 0 ? '+' : '−' }}{{ $fmt(abs($projectedNet)) }}</span></div>
                        @endif
                    </div>
                </div>

                {{-- approval & versioning --}}
                @php
                    $bs = $event->budget_status ?? 'draft';
                    $bsMeta = ['draft' => ['Draft', 'bg-page text-muted'], 'pending' => ['Pending approval', 'bg-warning-soft text-warning-ink'], 'approved' => ['Approved · locked', 'bg-success-soft text-success-ink']];
                    [$bsLabel, $bsClass] = $bsMeta[$bs] ?? $bsMeta['draft'];
                    $approvedV = $versions->firstWhere('status', 'approved');
                @endphp
                <div class="border-b border-line p-3">
                    <p class="mb-1.5 flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.12em] text-muted"><span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Approval</p>
                    <div class="mb-2 flex items-center gap-2">
                        <span class="rounded-full px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide {{ $bsClass }}">{{ $bsLabel }}</span>
                        @if ($bs === 'approved' && $approvedV)<span class="text-eyebrow text-muted">baseline v{{ $approvedV->version }}</span>@endif
                    </div>

                    @if ($bs === 'approved')
                        <p class="text-eyebrow leading-snug text-muted">🔒 Locked {{ $event->budget_locked_at?->format('j M') }}@if ($approvedV?->decider) · by {{ $approvedV->decider->name }}@endif.</p>
                        @if ($approvedTotal)
                            <div class="mt-2 flex justify-between text-xs"><span class="text-muted">Variance vs approved</span><span class="font-bold {{ $varianceVsApproved < 0 ? 'text-danger-ink' : 'text-success-ink' }}">{{ $varianceVsApproved >= 0 ? '+' : '−' }}{{ $fmt(abs($varianceVsApproved)) }}</span></div>
                        @endif
                        <button type="button" wire:click="reviseBudget" class="mt-2 h-9 w-full rounded-xl border border-gold-300 bg-gold-50/60 text-xs font-bold text-gold-800 transition hover:bg-gold-50">✎ Create revision</button>
                    @elseif ($bs === 'pending')
                        <p class="mb-2 text-eyebrow text-muted">Submitted — awaiting sign-off.</p>
                        @can('manage-budget')
                            <div class="flex gap-2">
                                <button type="button" wire:click="approveBudget" class="btn-navy h-9 flex-1 text-xs">✓ Approve</button>
                                <button type="button" wire:click="rejectBudget" class="h-9 flex-1 rounded-xl border border-line bg-white text-xs font-semibold text-ink transition hover:border-danger/40 hover:text-danger-ink">Reject</button>
                            </div>
                        @else
                            <p class="text-eyebrow text-muted">A manager signs this off.</p>
                        @endcan
                    @else
                        <input type="text" wire:model="approvalNote" maxlength="120" class="mb-2 h-9 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none" placeholder="Version note (optional)">
                        <button type="button" wire:click="submitForApproval" @disabled($items->isEmpty()) class="btn-gold h-9 w-full text-xs disabled:opacity-40">Submit for approval</button>
                    @endif

                    @if ($versions->isNotEmpty())
                        @php $vm = ['pending' => 'text-warning-ink', 'approved' => 'text-success-ink', 'rejected' => 'text-danger-ink', 'superseded' => 'text-muted']; @endphp
                        <div class="mt-3 space-y-1 border-t border-line pt-2">
                            @foreach ($versions->take(4) as $v)
                                <div class="flex items-center justify-between text-eyebrow">
                                    <span class="text-muted">v{{ $v->version }} · <span class="font-bold {{ $vm[$v->status] ?? '' }}">{{ ucfirst($v->status) }}</span></span>
                                    <span class="text-muted">{{ $fmt($v->totals['grand'] ?? 0) }} · {{ $v->created_at->format('j M') }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- ══ what the modules put here, and what they could not ══ --}}
                @if ($linkedByModule->isNotEmpty() || $pendingFromModules)
                    <div class="border-t border-line p-3">
                        <p class="text-eyebrow font-bold uppercase tracking-[0.16em] text-muted">From the modules</p>

                        @foreach ($linkedByModule as $src => $m)
                            @php $meta = \App\Models\EventBudgetItem::SOURCES[$src] ?? ['A module', 'budget']; @endphp
                            <a href="{{ route('events.hub', [$event, 'tab' => $meta[1]]) }}" wire:navigate
                               class="mt-1.5 flex items-baseline gap-2 text-[11.5px] transition hover:text-gold-800">
                                <span class="font-bold text-ink">{{ $meta[0] }}</span>
                                <span class="text-muted">{{ $m['n'] }} {{ str('line')->plural($m['n']) }}</span>
                                <span class="ms-auto font-black tabular-nums text-ink">{{ number_format($m['cents'] / 100, 2) }}</span>
                            </a>
                        @endforeach

                        {{-- The answer to "but I booked those rooms". Rooms held
                             with no rate are real and cannot be costed, so the
                             budget says so instead of quietly omitting them. --}}
                        @if ($pendingFromModules)
                            <div class="mt-2.5 rounded-xl bg-warning-soft p-2.5">
                                <p class="text-[11px] font-bold text-warning-ink">
                                    {{ count($pendingFromModules) }} {{ str('commitment')->plural(count($pendingFromModules)) }} not in the budget
                                </p>
                                @foreach (collect($pendingFromModules)->take(4) as $p)
                                    <a href="{{ route('events.hub', [$event, 'tab' => $p['tab']]) }}" wire:navigate
                                       class="mt-1 block text-[11px] leading-snug text-warning-ink/80 hover:underline">
                                        <span class="font-semibold">{{ $p['module'] }}</span> · {{ $p['what'] }}
                                    </a>
                                @endforeach
                                @if (count($pendingFromModules) > 4)
                                    <p class="mt-1 text-[10.5px] text-warning-ink/60">…and {{ count($pendingFromModules) - 4 }} more.</p>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif

                {{-- actions --}}
                <div class="space-y-2 p-4">
                    @unless ($event->budgetLocked())
                        <button type="button" wire:click="newLine" class="btn-gold h-10 w-full text-xs">＋ Add Line</button>
                        <button type="button" wire:click="syncModules" class="h-9 w-full rounded-xl border border-gold-300 bg-gold-50/60 text-xs font-bold text-gold-800 transition hover:bg-gold-50" title="Re-read the modules. They also sync themselves whenever a booking changes.">↺ Sync modules @if ($syncedCount)· {{ $syncedCount }} linked @endif</button>
                        @if ($items->isEmpty())
                            <button type="button" wire:click="insertStarter" class="h-9 w-full rounded-xl border border-line bg-white text-xs font-semibold text-ink transition hover:border-gold-300">✨ Prefill common lines</button>
                        @endif
                    @endunless
                    <a href="{{ route('events.budget.pdf', $event) }}" class="flex h-9 w-full items-center justify-center gap-1.5 rounded-xl border border-line bg-white text-xs font-semibold text-ink transition hover:border-gold-300 {{ $items->isEmpty() ? 'pointer-events-none opacity-40' : '' }}">↧ Export PDF</a>
                    @if (! $event->budgetLocked() && ! $items->isEmpty())
                        <x-confirm title="Delete ALL budget lines?"
                                   body="This cannot be undone."
                                   confirm="Clear"
                                   run="$wire.clearAllLines()"
                                   class="h-9 w-full rounded-xl border border-line bg-white text-xs font-semibold text-ink transition hover:border-danger/40 hover:text-danger-ink">Clear all lines</x-confirm>
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
                    <h3 class="text-base font-bold text-ink">{{ $editingId ? 'Edit budget line' : 'New budget line' }}</h3>
                    <button type="button" wire:click="$set('showForm', false)" class="text-muted hover:text-ink">✕</button>
                </div>

                <form wire:submit="save" class="grid gap-3.5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Category</label>
                        <select wire:model="category" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                            @foreach ($categories as $c)<option value="{{ $c->name }}">{{ $c->name }}</option>@endforeach
                        </select>
                        @error('category') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
                        <p class="mt-1 text-eyebrow text-muted">Add or rename categories directly on the ledger.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Description</label>
                        <input type="text" wire:model="description" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="e.g. Main stage build">
                        @error('description') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Quantity</label>
                        <input type="number" min="1" wire:model="quantity" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                        @error('quantity') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Unit cost ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="unit" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="0">
                        @error('unit') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Actual cost ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="actual" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="0">
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Paid to date ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="paid" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="0">
                    </div>

                    {{-- ── What the client is charged ──
                         Leaving both blank is the normal case: the line falls
                         back to the event's management fee, which is what
                         every line did before this existed. --}}
                    <div class="sm:col-span-2 rounded-xl border border-line bg-page/40 p-3">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <p class="text-eyebrow font-bold uppercase tracking-wide text-muted">What the client is charged</p>
                            <label class="flex cursor-pointer items-center gap-1.5 text-eyebrow font-semibold text-muted">
                                <input type="checkbox" wire:model.live="billable" class="h-3.5 w-3.5 rounded border-line text-gold-600 focus:ring-gold-400/40">
                                Billable
                            </label>
                        </div>

                        @if ($billable)
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Charge ({{ $event->currency }})</label>
                                    <input type="number" step="0.01" min="0" wire:model="sell" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="quoted price">
                                    @error('sell') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">…or markup %</label>
                                    <input type="number" step="0.5" wire:model="markup" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="{{ $event->management_fee_pct ?? 15 }}">
                                    @error('markup') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
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
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Vendor / supplier</label>
                        {{-- A name from the directory links the line to that
                             supplier; anything else is still allowed. --}}
                        <input type="text" wire:model="vendor" list="budget-vendors" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="e.g. Prime AV">
                        <datalist id="budget-vendors">
                            @foreach ($vendorNames as $name)<option value="{{ $name }}">@endforeach
                        </datalist>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Invoice #</label>
                            <input type="text" wire:model="invoice_number" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="—">
                        </div>
                        <div>
                            <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Due date</label>
                            <input type="date" wire:model="due_on" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Notes</label>
                        <input type="text" wire:model="notes" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Optional notes">
                    </div>

                    <div class="mt-1 flex items-center justify-between sm:col-span-2">
                        <p class="text-xs text-muted">Budgeted total: <span class="font-bold text-ink">{{ $event->currencySymbol() }}{{ number_format((float) ($unit ?: 0) * max(1, (int) $quantity), 0) }}</span></p>
                        <div class="flex gap-2">
                            <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-muted hover:text-ink">Cancel</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="h-10 rounded-full bg-gold-500 px-6 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">
                                <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update line' : 'Add line' }}</span>
                                <span wire:loading wire:target="save">Saving…</span>
                            </button>
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
                    <h3 class="text-base font-bold text-ink">{{ $editingIncomeId ? 'Edit income' : 'New income' }}</h3>
                    <button type="button" wire:click="$set('showIncomeForm', false)" class="text-muted hover:text-ink">✕</button>
                </div>
                <form wire:submit="saveIncome" class="grid gap-3.5">
                    <p class="text-eyebrow text-muted">Sponsorship &amp; exhibition income are pulled automatically from those modules — add tickets, grants and other income here.</p>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Source</label>
                        <select wire:model="incomeSource" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                            @foreach (\App\Support\Taxonomy::options('income_source') as $key => $lbl)<option value="{{ $key }}">{{ $lbl }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Description</label>
                        <input type="text" wire:model="incomeDesc" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="e.g. 300 delegate tickets">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Amount ({{ $event->currency }})</label>
                            <input type="number" step="0.01" min="0" wire:model="incomeAmount" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="0">
                            @error('incomeAmount') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Status</label>
                            <select wire:model="incomeStatus" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                                @foreach (\App\Models\EventIncomeItem::STATUSES as $s)<option value="{{ $s }}">{{ ucfirst($s) }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showIncomeForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-muted hover:text-ink">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveIncome" class="h-10 rounded-full bg-gold-500 px-6 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">
                            <span wire:loading.remove wire:target="saveIncome">{{ $editingIncomeId ? 'Update' : 'Add income' }}</span>
                            <span wire:loading wire:target="saveIncome">Saving…</span>
                        </button>
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
