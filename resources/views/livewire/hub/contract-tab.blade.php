@php
    $f = $data['financials'] ?? [];
    $cur = $f['currency'] ?? 'JOD';
    $est = $f['estimated_total_cents'] ?? 0;
    $fmt = fn ($c) => $cur.' '.number_format(($c ?? 0) / 100);
    $statusMeta = [
        'draft' => ['Draft', 'bg-navy-50 text-navy-500 ring-line'],
        'sent' => ['Sent for signature', 'bg-amber-50 text-amber-700 ring-amber-200'],
        'signed' => ['Signed', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
    ][$status] ?? ['Draft', 'bg-navy-50 text-navy-500 ring-line'];
@endphp

<div class="space-y-5">
    {{-- ══ Command strip ══ --}}
    <div class="strip-dark flex flex-wrap items-center justify-between gap-3 px-5 py-4">
        <div class="pointer-events-none absolute -right-8 -top-16 h-48 w-48 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.3),transparent_70%)]"></div>
        <div class="relative flex items-center gap-3">
            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/[0.07] text-gold-400 ring-1 ring-white/10">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 3H6a2 2 0 00-2 2v14a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2h-2M9 3h6M8 11h8M8 15h5"/></svg>
            </span>
            <div>
                <p class="text-eyebrow font-bold uppercase tracking-[0.28em] text-gold-300">Event Contract</p>
                <p class="pf text-base font-semibold text-white">Management Services Agreement</p>
            </div>
        </div>
        <div class="relative flex flex-wrap items-center gap-2">
            <span class="rounded-full px-2.5 py-1 text-eyebrow font-bold uppercase tracking-wide ring-1 {{ str_replace(['bg-navy-50','ring-line'], ['bg-white/5','ring-white/15'], $statusMeta[1]) }} !text-white">{{ $statusMeta[0] }}</span>
            <button type="button" wire:click="cycleStatus" class="h-9 rounded-xl bg-white/5 px-3 text-micro font-bold text-white/70 ring-1 ring-white/15 transition hover:text-white" title="Advance status">Advance →</button>
            <button type="button" wire:click="resetContract" wire:confirm="Reset the contract to defaults? Your edits will be replaced." class="flex h-9 w-9 items-center justify-center rounded-xl text-white/40 transition hover:bg-white/5 hover:text-white/80" title="Reset">↺</button>
            <a href="{{ route('events.contract.pdf', $event) }}" target="_blank" class="flex h-9 items-center gap-1.5 rounded-xl bg-gradient-to-r from-gold-400 to-gold-600 px-4 text-micro font-bold text-navy-950 shadow transition hover:brightness-105">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14"/></svg> Export Contract PDF
            </a>
        </div>
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
        {{-- ── Editable variables ── --}}
        <div class="space-y-5">
            <div class="card p-5">
                <x-section-head number="01" title="Parties" subtitle="First party is Elite Business Hub · edit the client entities below" />
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2 rounded-xl bg-navy-50/60 px-3 py-2.5">
                        <p class="text-eyebrow font-bold uppercase tracking-wide text-navy-400">First Party</p>
                        <p class="text-sm font-semibold text-navy-900">{{ $data['first_party']['name_en'] ?? 'Elite Business Hub' }}</p>
                    </div>
                    @foreach ($data['second_parties'] ?? [] as $i => $sp)
                        <div>
                            <label class="field-label !mb-1 !text-eyebrow">Second party {{ $i + 1 }} — name</label>
                            <input type="text" wire:model.blur="data.second_parties.{{ $i }}.name_en" class="input h-9 text-sm">
                        </div>
                        <div>
                            <label class="field-label !mb-1 !text-eyebrow">Cost share (%)</label>
                            <input type="number" min="0" max="100" wire:model.live.debounce.300ms="data.second_parties.{{ $i }}.share" class="input h-9 text-sm">
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card p-5">
                <x-section-head number="02" title="Budget Assumptions" subtitle="What the estimated budget is based on" />
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Attendees — from</label>
                        <input type="number" wire:model.blur="data.assumptions.attendees_min" class="input h-9 text-sm">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Attendees — to</label>
                        <input type="number" wire:model.blur="data.assumptions.attendees_max" class="input h-9 text-sm">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Rooms</label>
                        <input type="number" wire:model.blur="data.assumptions.rooms" class="input h-9 text-sm">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Nights per guest</label>
                        <input type="number" wire:model.blur="data.assumptions.nights" class="input h-9 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label !mb-1 !text-eyebrow">Catering (English)</label>
                        <input type="text" wire:model.blur="data.assumptions.catering_en" class="input h-9 text-sm">
                    </div>
                </div>
            </div>

            <div class="card p-5">
                <x-section-head number="03" title="Payment Schedule" subtitle="Percentages must total 100%" />
                <div class="space-y-2">
                    @php $total = collect($f['payment_schedule'] ?? [])->sum('pct'); @endphp
                    @foreach ($f['payment_schedule'] ?? [] as $i => $s)
                        <div class="flex items-center gap-3">
                            <input type="number" min="0" max="100" wire:model.live.debounce.300ms="data.financials.payment_schedule.{{ $i }}.pct" class="input h-9 w-20 text-center text-sm font-bold">
                            <span class="text-eyebrow font-semibold text-navy-400">%</span>
                            <input type="text" wire:model.blur="data.financials.payment_schedule.{{ $i }}.when_en" class="input h-9 flex-1 text-xs">
                            <span class="w-24 shrink-0 text-right text-micro font-semibold text-navy-500">{{ $fmt($est * ($s['pct'] ?? 0) / 100) }}</span>
                        </div>
                    @endforeach
                    <div class="flex items-center justify-between border-t border-line pt-2 text-micro font-bold">
                        <span class="{{ $total === 100 ? 'text-emerald-600' : 'text-risk' }}">Total: {{ $total }}%</span>
                        @if ($total !== 100)<span class="text-risk">Must equal 100%</span>@endif
                    </div>
                </div>
            </div>

            <div class="card p-5">
                <div class="mb-1 flex items-start justify-between gap-3">
                    <x-section-head number="04" title="Payments Received" subtitle="The schedule, tracked against reality" />
                    @can('manage-contract')
                        <button type="button" wire:click="repricePayments" class="btn-ghost btn-xs shrink-0" title="Re-price unpaid installments after the estimate or percentages changed">↻ Re-price unpaid</button>
                    @endcan
                </div>

                @php $pctCollected = $scheduledTotal ? (int) round($collected / $scheduledTotal * 100) : 0; @endphp
                <div class="mb-4 flex items-center gap-3">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-navy-50">
                        <div class="h-full rounded-full bg-gradient-to-r from-gold-400 to-gold-600" style="width: {{ $pctCollected }}%"></div>
                    </div>
                    <span class="shrink-0 text-xs font-bold text-navy-900">{{ $fmt($collected) }} <span class="font-medium text-muted">of {{ $fmt($scheduledTotal) }} · {{ $pctCollected }}%</span></span>
                </div>
                @if ($overdueCount > 0)
                    <p class="mb-3 rounded-xl bg-risk/10 px-3.5 py-2 text-micro font-semibold text-red-700 ring-1 ring-risk/30">⚠ {{ $overdueCount }} {{ \Illuminate\Support\Str::plural('installment', $overdueCount) }} overdue — chase before it becomes a cash-flow problem.</p>
                @endif

                <div class="divide-y divide-line">
                    @foreach ($payments as $p)
                        @php [$stLabel, $stHex] = \App\Models\EventContractPayment::STATUS_META[$p->status()]; @endphp
                        <div wire:key="pay-{{ $p->id }}" class="flex flex-wrap items-center gap-x-3 gap-y-1.5 py-2.5">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-navy-50 text-eyebrow font-bold text-navy-600">{{ $p->sort + 1 }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-xs font-semibold text-navy-900">{{ $p->label }}</span>
                                <span class="text-eyebrow text-muted">{{ $p->pct }}% · {{ $fmt($p->amount_cents) }}@if ($p->paid_cents > 0 && $p->status() !== 'paid') · {{ $fmt($p->paid_cents) }} received @endif</span>
                            </span>

                            @can('manage-contract')
                                <input type="date" value="{{ $p->due_on?->toDateString() }}" wire:change="setPaymentDue({{ $p->id }}, $event.target.value)" wire:key="due-{{ $p->id }}" class="input h-8 w-36 text-micro" title="Due date">
                            @else
                                <span class="text-micro text-muted">{{ $p->due_on?->format('M j, Y') ?? '—' }}</span>
                            @endcan

                            <span class="w-16 shrink-0 rounded-full px-2 py-0.5 text-center text-eyebrow font-bold uppercase" style="background: {{ $stHex }}1c; color: {{ $stHex }};">{{ $stLabel }}</span>

                            @can('manage-contract')
                                <span class="flex shrink-0 gap-1">
                                    @if ($p->status() !== 'paid')
                                        <button type="button" wire:click="recordPayment({{ $p->id }})" wire:confirm="Record {{ $fmt($p->outstandingCents()) }} received for “{{ $p->label }}”?" class="rounded-lg bg-track/10 px-2 py-1 text-eyebrow font-bold text-emerald-700 hover:bg-track/20">✓ Paid</button>
                                    @else
                                        <button type="button" wire:click="clearPayment({{ $p->id }})" wire:confirm="Clear the recorded payment on “{{ $p->label }}”?" class="rounded-lg bg-navy-50 px-2 py-1 text-eyebrow font-bold text-navy-500 hover:bg-navy-100">↺</button>
                                    @endif
                                </span>
                            @endcan
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── Summary rail ── --}}
        <div class="xl:sticky xl:top-[112px] xl:h-fit">
            <div class="card overflow-hidden">
                <div class="border-b border-line bg-navy-900 px-4 py-3">
                    <span class="text-xs font-bold uppercase tracking-[0.14em] text-gold-300">Contract Summary</span>
                </div>
                <div class="space-y-3 p-4 text-sm">
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Reference</label>
                        <input type="text" wire:model.blur="reference" class="input h-9 text-sm">
                    </div>
                    <div class="flex items-center justify-between rounded-xl bg-gold-50/50 px-3 py-2.5">
                        <span class="text-eyebrow font-semibold text-muted">Estimated total</span>
                        <span class="pf text-lg font-bold text-navy-900">{{ $fmt($est) }}</span>
                    </div>
                    <button type="button" wire:click="syncBudget" class="w-full rounded-xl border border-line bg-white px-3 py-2 text-micro font-bold text-navy-600 transition hover:border-gold-300 hover:text-gold-700">↻ Sync from event budget</button>

                    <div class="space-y-1.5 border-t border-line pt-3 text-xs">
                        @foreach ($data['second_parties'] ?? [] as $sp)
                            <div class="flex justify-between"><span class="text-muted">{{ \Illuminate\Support\Str::limit($sp['name_en'] ?? '', 18) }}</span><span class="font-bold text-navy-900">{{ $sp['share'] ?? 0 }}% · {{ $fmt($est * ($sp['share'] ?? 0) / 100) }}</span></div>
                        @endforeach
                    </div>

                    <p class="border-t border-line pt-3 text-eyebrow leading-relaxed text-muted">
                        The full agreement — scope of work, cost sharing, payment terms, cancellation policy and signatures — is generated bilingually (English &amp; Arabic) in the PDF.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
