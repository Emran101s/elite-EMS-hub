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
                    <div role="group" aria-label="Budget view" class="flex rounded-xl border border-line bg-page/40 p-0.5">
                        <button type="button" wire:click="$set('view', 'build')" aria-pressed="{{ $view === 'build' ? 'true' : 'false' }}" class="flex-1 rounded-lg py-1.5 text-eyebrow font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gold-400 focus-visible:ring-offset-1 {{ $view === 'build' ? 'bg-navy-900 text-white' : 'text-muted hover:text-ink' }}">Build</button>
                        <button type="button" wire:click="$set('view', 'track')" aria-pressed="{{ $view === 'track' ? 'true' : 'false' }}" class="flex-1 rounded-lg py-1.5 text-eyebrow font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gold-400 focus-visible:ring-offset-1 {{ $view === 'track' ? 'bg-navy-900 text-white' : 'text-muted hover:text-ink' }}">Track</button>
                        <button type="button" wire:click="$set('view', 'price')" aria-pressed="{{ $view === 'price' ? 'true' : 'false' }}" class="flex-1 rounded-lg py-1.5 text-eyebrow font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gold-400 focus-visible:ring-offset-1 {{ $view === 'price' ? 'bg-navy-900 text-white' : 'text-muted hover:text-ink' }}">Price</button>
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
