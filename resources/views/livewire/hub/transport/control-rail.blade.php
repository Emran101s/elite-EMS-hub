        <div class="space-y-3 xl:sticky xl:top-12 xl:h-fit">
            <div class="cx-panel">
                <div class="cx-lcard-head" style="background: var(--cx-espresso-1); border-bottom-color: transparent;">
                    <span class="flex items-center gap-2 text-[10.5px] font-bold uppercase tracking-[0.14em]" style="color:#F0E7D5">
                        <span class="cx-cathex" style="width:22px;height:24px;background:{{ $moduleHex }}"><x-icon name="truck" class="h-3 w-3" /></span>
                        Transport Control
                    </span>
                </div>

                <div class="cx-panel-sec">
                    <button type="button" wire:click="newItem" class="cx-btn cx-btn-accent w-full justify-center" style="height:36px">＋ Add Movement</button>
                </div>

                <div class="cx-panel-sec">
                    <p class="cx-panel-k"><span class="cx-hexdot"></span> Summary</p>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between"><span class="text-muted">Movements</span><span class="font-bold text-ink">{{ $total }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Seats booked</span><span class="font-bold text-ink">{{ $seatsTotal }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Passengers</span><span class="font-bold text-ink">{{ $paxTotal }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Named on manifests</span><span class="font-bold text-ink">{{ $namedTotal }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Still to place</span><span class="font-bold {{ $unassignedCount ? 'text-amber-700' : 'text-ink' }}">{{ $unassignedCount }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Not ready</span><span class="font-bold {{ $notReady ? 'text-amber-700' : 'text-ink' }}">{{ $notReady }}</span></div>
                        @if ($notReady)
                            <p class="text-eyebrow leading-relaxed text-amber-700">Missing driver, vehicle or passengers</p>
                        @endif
                        @if ($overbooked)
                            <div class="flex justify-between"><span class="text-muted">Over capacity</span><span class="font-bold text-red-700">{{ $overbooked }}</span></div>
                        @endif
                        <div class="flex justify-between border-t border-line pt-1.5"><span class="text-muted">Transport cost</span><span class="font-bold text-ink">{{ $money3($costTotal) }}</span></div>
                        <x-budget-routing :routing="$this->budgetRouting()" />
                    </div>
                </div>

                @if ($fleet->isNotEmpty())
                    <div class="cx-panel-sec">
                        <p class="cx-panel-k"><span class="cx-hexdot"></span> Vehicles required</p>
                        <div class="space-y-1.5">
                            @foreach ($fleet as $f)
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="min-w-0 truncate text-xs text-ink">{{ $f['name'] }}<span class="text-muted"> · max {{ $f['capacity'] }}</span></span>
                                    <span class="shrink-0 text-xs font-bold text-ink">×{{ $f['vehicles'] }}</span>
                                </div>
                                <div class="text-eyebrow text-muted">{{ $f['runs'] }} {{ \Illuminate\Support\Str::plural('run', $f['runs']) }} · {{ $f['pax'] }} pax</div>
                            @endforeach
                        </div>
                        <p class="mt-2 text-eyebrow leading-relaxed text-muted">What to order from the supplier.</p>
                    </div>
                @endif

                <div class="cx-panel-sec space-y-2">
                    @if ($total)
                        <a href="{{ route('events.transport.pdf', [$event, ...$this->exportFilters()]) }}" target="_blank"
                           class="block rounded-xl border border-line bg-white px-3 py-2 text-center text-xs font-bold text-ink transition hover:border-amber-300 hover:text-ink">
                            ↧ Manifest PDF
                        </a>
                        <div class="grid grid-cols-2 gap-1.5">
                            <a href="{{ route('events.transport.dispatch', $event) }}"
                               class="rounded-lg border border-line bg-white px-2 py-1.5 text-center text-eyebrow font-bold text-muted hover:border-amber-300">Dispatch</a>
                            <a href="{{ route('events.transport.live', $event) }}"
                               class="rounded-lg border border-line bg-white px-2 py-1.5 text-center text-eyebrow font-bold text-muted hover:border-amber-300">Live</a>
                        </div>
                    @endif
                    <a href="{{ route('transport-settings.index') }}"
                       class="block rounded-xl px-3 py-2 text-center text-micro font-semibold text-muted hover:bg-page hover:text-ink">
                        Manage vehicles &amp; services →
                    </a>
                </div>
            </div>
        </div>
