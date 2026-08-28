        {{-- ══════════ RIGHT · Budget Control Center ══════════ --}}
        <div class="xl:sticky xl:top-4 xl:h-fit">
            <div class="cx-panel">
                {{-- control-center header --}}
                <div class="cx-lcard-head" style="background: var(--cx-espresso-1); border-bottom-color: transparent;">
                    <span class="flex items-center gap-2 text-[10.5px] font-bold uppercase tracking-[0.14em] text-cream" style="color:#F0E7D5">
                        <span class="cx-cathex" style="width:22px;height:24px;background:{{ \App\Models\Event::moduleColor('budget') }}"><x-icon name="currency" class="h-3 w-3" /></span>
                        Budget Control Center
                    </span>
                    <a href="{{ route('events.budget.pdf', $event) }}" class="flex items-center gap-1 rounded-lg border border-white/15 bg-white/5 px-2 py-1 text-[9px] font-bold uppercase tracking-wide text-white/70 transition hover:border-gold-400/40 hover:text-gold-300 {{ $items->isEmpty() ? 'pointer-events-none opacity-40' : '' }}">↧ PDF</a>
                </div>

                {{-- mode --}}
                <div class="cx-panel-sec">
                    <p class="cx-panel-k"><span class="cx-hexdot"></span> Mode</p>
                    <div role="group" aria-label="Budget view" class="cx-modebar">
                        <button type="button" wire:click="$set('view', 'build')" aria-pressed="{{ $view === 'build' ? 'true' : 'false' }}">Build</button>
                        <button type="button" wire:click="$set('view', 'track')" aria-pressed="{{ $view === 'track' ? 'true' : 'false' }}">Track</button>
                        <button type="button" wire:click="$set('view', 'price')" aria-pressed="{{ $view === 'price' ? 'true' : 'false' }}">Price</button>
                    </div>
                    <p class="mt-1.5 text-eyebrow leading-snug text-muted">{{ match ($view) {
                        'build' => 'Plan quantity × unit estimates.',
                        'track' => 'Budget vs actual & paid.',
                        'price' => 'Cost to you vs charged to client.',
                    } }}</p>
                </div>

                {{-- total budget + fee + currency --}}
                <div class="cx-panel-sec">
                    <p class="cx-panel-k"><span class="cx-hexdot"></span> Total budget</p>
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
                <div class="cx-panel-sec">
                    <p class="cx-panel-k"><span class="cx-hexdot" style="background:var(--cx-ink)"></span> Summary</p>
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

                {{-- ══ MARGIN, then CASH — in that order ══

                     This block used to lead with "NET LOSS −59,323" in red,
                     which was income-received minus cost. On an event that
                     simply has not been invoiced yet that is not a loss, it
                     is an empty bank line, and shouting it buries the number
                     that actually decides whether the event is worth running:
                     what you charge minus what it costs.

                     So margin leads. Cash follows, named as cash. And the gap
                     between what the budget charges and what the income table
                     expects — previously invisible — is called out, because a
                     plan to charge one figure while forecasting another is
                     exactly the thing a budget should catch. --}}
                @php
                    $margin = $grandForecast - $costToDeliver;
                    $marginPct = $grandForecast > 0 ? (int) round($margin / $grandForecast * 100) : null;
                    $outstanding = max(0, $totalTargetIncome - $totalIncome);
                    $chargeGap = $grandForecast - $totalTargetIncome;
                @endphp
                <div class="cx-panel-sec">
                    <p class="cx-panel-k"><span class="cx-hexdot" style="background:var(--cx-ok)"></span> Margin</p>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between"><span class="text-muted">Cost to deliver</span><span class="font-bold tabular-nums text-ink">{{ $fmt($costToDeliver) }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Charged to client</span><span class="font-bold tabular-nums text-ink">{{ $fmt($grandForecast) }}</span></div>
                        <div class="flex items-center justify-between rounded-lg px-2 py-1 {{ $margin < 0 ? 'bg-danger-soft' : 'bg-success-soft' }}">
                            <span class="text-eyebrow font-bold uppercase tracking-wide {{ $margin < 0 ? 'text-danger-ink' : 'text-success-ink' }}">
                                {{ $margin < 0 ? 'Under water' : 'Margin' }}@if ($marginPct !== null) · {{ $marginPct }}%@endif
                            </span>
                            <span class="text-sm font-bold tabular-nums {{ $margin < 0 ? 'text-danger-ink' : 'text-success-ink' }}">{{ $margin >= 0 ? '+' : '−' }}{{ $fmt(abs($margin)) }}</span>
                        </div>

                        @if ($chargeGap !== 0)
                            <p class="rounded-lg px-2 py-1.5 text-[10.5px] leading-snug" style="background: var(--cx-warn-wash); color: var(--cx-warn-ink)">
                                The budget charges {{ $fmt($grandForecast) }} but income only expects
                                {{ $fmt($totalTargetIncome) }} — a {{ $fmt(abs($chargeGap)) }}
                                {{ $chargeGap > 0 ? 'shortfall in what you have forecast collecting' : 'surplus over what you are charging' }}.
                            </p>
                        @endif
                    </div>
                </div>

                <div class="cx-panel-sec">
                    <p class="cx-panel-k"><span class="cx-hexdot" style="background:var(--cx-accent)"></span> Cash</p>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between"><span class="text-muted">Received so far</span><span class="font-bold tabular-nums {{ $totalIncome ? 'text-success-ink' : 'text-muted' }}">{{ $fmt($totalIncome) }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Still to collect</span><span class="font-bold tabular-nums text-ink">{{ $fmt($outstanding) }}</span></div>
                        <div class="flex justify-between border-t border-line pt-1">
                            <span class="text-muted">Against cost</span>
                            <span class="font-bold tabular-nums {{ $netResult < 0 ? 'text-warning-ink' : 'text-success-ink' }}">{{ $netResult >= 0 ? '+' : '−' }}{{ $fmt(abs($netResult)) }}</span>
                        </div>
                    </div>
                </div>

                {{-- approval & versioning --}}
                @php
                    $bs = $event->budget_status ?? 'draft';
                    $bsMeta = ['draft' => ['Draft', 'bg-page text-muted'], 'pending' => ['Pending approval', 'bg-warning-soft text-warning-ink'], 'approved' => ['Approved · locked', 'bg-success-soft text-success-ink']];
                    [$bsLabel, $bsClass] = $bsMeta[$bs] ?? $bsMeta['draft'];
                    $approvedV = $versions->firstWhere('status', 'approved');
                @endphp
                <div class="cx-panel-sec">
                    <p class="cx-panel-k"><span class="cx-hexdot"></span> Approval</p>
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
                    <div class="cx-panel-sec">
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
                <div class="cx-panel-sec space-y-2">
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
