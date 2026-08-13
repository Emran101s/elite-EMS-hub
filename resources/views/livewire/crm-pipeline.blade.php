@php
    $money = fn (int $cents, string $cur = 'JOD') => \App\Support\Money::abbreviated($cents, $cur);
    $f = $forecast;
@endphp

<div class="eo-event-atmosphere space-y-5 rounded-[24px]">

    <x-eo.page-header
        eyebrow="Commercial Command"
        title="CRM Pipeline"
        subtitle="Queue → Deal → Action Panel. Every opportunity from first conversation to signed event."
    >
        <x-slot:actions>
            <span class="eo-journey-chip">Deal desk</span>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-eo-muted" />
                <input type="search" wire:model.live.debounce.300ms="q" placeholder="Search deals or clients…"
                       class="eo-input h-10 w-52 !py-0 !ps-9 text-xs">
            </div>
            <x-eo.button variant="ghost" size="sm" href="{{ route('clients.index') }}">Clients →</x-eo.button>
            <x-eo.button size="sm" wire:click="newDeal">＋ New deal</x-eo.button>
        </x-slot:actions>
    </x-eo.page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <x-eo.metric-pill label="Open deals" :value="$f['count']" hint="still in play" tone="live" />
        <x-eo.metric-pill label="Pipeline value" :value="$money($f['value'])" hint="if every one lands" />
        <x-eo.metric-pill label="Weighted" :value="$money($f['weighted'])" hint="by chance of winning" tone="ok" />
        <x-eo.metric-pill label="Won this month" :value="$f['wonThisMonth']" hint="became events" tone="ok" />
        <x-eo.metric-pill label="Going stale" :value="$f['stale']" :hint="$f['stale'] ? 'nobody has touched them' : 'everything is moving'" :tone="$f['stale'] ? 'risk' : 'ok'" />
    </div>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="min-w-0">
            <div class="mb-3 flex flex-wrap items-baseline gap-2">
                <p class="eo-label">Deal queue · pipeline</p>
                <span class="text-[12px] text-eo-muted">select to inspect · drag lanes or use stage actions</span>
            </div>

            <div class="scrollbar-none -mx-1 overflow-x-auto px-1">
                <div class="grid min-w-[860px] grid-cols-4 gap-4" data-pipeline>
                    @foreach ($lanes as $lane)
                        <div>
                            <div class="mb-2.5 flex items-center gap-2">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $lane['hex'] }}"></span>
                                <b class="text-xs font-bold text-eo-text">{{ $lane['label'] }}</b>
                                <span class="ml-auto grid h-5 min-w-5 place-items-center rounded-full bg-white px-1.5 text-[10.5px] font-bold text-eo-muted ring-1 ring-eo-line">{{ $lane['deals']->count() }}</span>
                            </div>

                            <div data-lane="{{ $lane['key'] }}" class="min-h-[60px]">
                            @forelse ($lane['deals'] as $deal)
                                <div wire:key="deal-{{ $deal->id }}" data-deal="{{ $deal->id }}"
                                     @class([
                                         'mb-3 rounded-2xl border bg-white p-3.5 transition',
                                         'border-eo-teal ring-1 ring-eo-teal/40 shadow-eo-teal' => $selected?->id === $deal->id,
                                         'border-eo-line hover:-translate-y-0.5 hover:border-eo-teal/30 hover:shadow-eo' => $selected?->id !== $deal->id,
                                     ])>
                                    <button type="button" wire:click="select({{ $deal->id }})" class="block w-full text-left">
                                        <span class="flex items-start gap-2">
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-[13px] font-bold leading-tight text-eo-text">{{ $deal->title }}</span>
                                                <span class="mt-1 block truncate text-[10.5px] text-eo-muted">{{ $deal->client?->name }}</span>
                                            </span>
                                            @if ($deal->isStale())
                                                <x-eo.status-pill tone="risk" class="shrink-0 !text-[9px]" title="Past its expected close date">STALE</x-eo.status-pill>
                                            @endif
                                        </span>

                                        <span class="mt-2.5 flex items-baseline justify-between gap-2">
                                            <span class="text-[15px] font-bold text-eo-text">{{ $money($deal->value_cents, $deal->currency) }}</span>
                                            <span class="text-[10.5px] font-bold tabular-nums text-eo-muted">{{ $deal->probability }}%</span>
                                        </span>
                                        <span class="mt-1.5 block h-1 overflow-hidden rounded-full bg-eo-bg">
                                            <span class="block h-full rounded-full" style="width: {{ $deal->probability }}%; background: {{ $deal->stageHex() }}"></span>
                                        </span>

                                        <span class="mt-2.5 flex items-center gap-2 border-t border-eo-line pt-2 text-[10.5px] text-eo-muted">
                                            @if ($deal->owner)
                                                <x-user-avatar :user="$deal->owner" size="h-5 w-5" />
                                            @endif
                                            <span class="truncate">{{ $deal->contact?->name ?? 'No contact' }}</span>
                                            <span class="ml-auto shrink-0 tabular-nums">{{ $deal->expected_close_on?->format('j M') ?? '—' }}</span>
                                        </span>
                                    </button>

                                    {{-- move it on --}}
                                    <div class="mt-2.5 flex items-center gap-1 border-t border-eo-line pt-2.5">
                                        @php
                                            $order = \App\Models\Deal::OPEN;
                                            $i = array_search($deal->stage, $order, true);
                                            $next = $order[$i + 1] ?? 'won';

                                            // A win with no accepted offer opens an event nobody agreed
                                            // a figure for. The gate is soft on purpose: enough deals
                                            // close on a phone call that a hard block would only teach
                                            // people to raise a proposal after the fact.
                                            $unpriced = $next === 'won' && ! $deal->acceptedProposal();

                                            $moveLabel = $next === 'won' ? '✓ Mark won' : '→ '.\App\Models\Deal::STAGES[$next][0];
                                            $moveClass = 'flex-1 rounded-lg bg-eo-bg py-1.5 text-[11px] font-bold text-eo-text transition hover:bg-eo-navy hover:text-white';
                                        @endphp
                                        @if ($unpriced)
                                            <x-confirm title="Win without an agreed figure?"
                                                       body="No accepted proposal on this deal, so the event opens with an empty budget and nothing to invoice from. Draft the offer first if the client has not signed one off."
                                                       confirm="Win anyway" cancel="Not yet" tone="warn"
                                                       run="$wire.moveTo({{ $deal->id }}, 'won')"
                                                       class="{{ $moveClass }}">{{ $moveLabel }}</x-confirm>
                                        @else
                                            <button type="button" wire:click="moveTo({{ $deal->id }}, '{{ $next }}')"
                                                    class="{{ $moveClass }}">
                                                {{ $moveLabel }}
                                            </button>
                                        @endif
                                        <button type="button" wire:click="moveTo({{ $deal->id }}, 'lost')" title="Mark lost"
                                                class="grid h-7 w-7 place-items-center rounded-lg text-eo-muted transition hover:bg-eo-risk/10 hover:text-eo-risk">✕</button>
                                    </div>
                                </div>
                            @empty
                                <p class="rounded-2xl border border-dashed border-eo-line px-3 py-6 text-center text-[11px] text-eo-muted">Drop a deal here.</p>
                            @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- ══════════ Closed ══════════ --}}
            @if ($closed->isNotEmpty())
                <div class="mt-5">
                    <p class="eo-label mb-2">Recently closed</p>
                    <div class="eo-soft-card divide-y divide-eo-line">
                        @foreach ($closed as $deal)
                            <div class="flex items-center gap-3 px-4 py-2.5">
                                <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $deal->stageHex() }}"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-[12.5px] font-semibold text-eo-text">{{ $deal->title }}</span>
                                    <span class="block truncate text-[10.5px] text-eo-muted">
                                        {{ $deal->client?->name }}
                                        @if ($deal->stage === 'lost' && $deal->lost_reason) · {{ $deal->lost_reason }} @endif
                                    </span>
                                </span>
                                <span class="shrink-0 text-[11px] font-bold tabular-nums text-eo-text">{{ $money($deal->value_cents, $deal->currency) }}</span>
                                @if ($deal->event)
                                    <a href="{{ route('events.hub', $deal->event) }}" class="eo-btn-ghost eo-btn-sm !px-2.5 !py-1 !text-[11px] shrink-0">Open event →</a>
                                @else
                                    <button type="button" wire:click="reopen({{ $deal->id }})" class="eo-btn-ghost eo-btn-sm !px-2.5 !py-1 !text-[11px] shrink-0">Reopen</button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Action Panel — Soft Command deal inspector --}}
        <aside class="flex flex-col gap-4 self-start xl:sticky xl:top-4">
            @if ($selected)
                <x-eo.detail-panel title="{{ $selected->title }}" subtitle="Deal · {{ $selected->stageLabel() }}">
                    <x-slot:header>
                        <button type="button" wire:click="editDeal({{ $selected->id }})" class="eo-btn-ghost eo-btn-sm shrink-0">Edit</button>
                    </x-slot:header>
                    <div class="space-y-3.5">

                        <div>
                            <div class="flex items-baseline justify-between gap-2 text-[11px]">
                                <span class="text-eo-muted">{{ $selected->stageLabel() }}</span>
                                <span class="font-bold tabular-nums" style="color: {{ $selected->stageHex() }}">{{ $selected->probability }}%</span>
                            </div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-eo-bg">
                                <div class="h-full rounded-full" style="width: {{ $selected->probability }}%; background: {{ $selected->stageHex() }}"></div>
                            </div>
                        </div>

                        <x-eo.commercial-card
                            title="Deal value"
                            :subtitle="$selected->client?->name ?? 'No client'"
                            :value="$money($selected->value_cents, $selected->currency)"
                            :meta="'Weighted '.$money($selected->weightedCents(), $selected->currency)"
                        />

                        @foreach ([
                            ['Value', $money($selected->value_cents, $selected->currency)],
                            ['Weighted', $money($selected->weightedCents(), $selected->currency)],
                            ['Client', $selected->client?->name ?? '—', $selected->client ? route('crm.client', $selected->client) : null],
                            ['Contact', $selected->contact?->name ?? 'None set'],
                            ['Owner', $selected->owner?->name ?? 'Unassigned'],
                            ['Decision by', $selected->expected_close_on?->format('j M Y') ?? '—'],
                            ['Event date', $selected->expected_event_on?->format('j M Y') ?? '—'],
                            ['Source', $selected->source ?? '—'],
                        ] as $row)
                            @php [$k, $v] = $row; $href = $row[2] ?? null; @endphp
                            <div class="flex justify-between gap-3 border-b border-eo-line/70 pb-2 text-[12px] last:border-0">
                                <span class="text-eo-muted">{{ $k }}</span>
                                @if ($href)
                                    <a href="{{ $href }}" class="truncate font-semibold text-eo-teal-ink transition hover:text-eo-teal-deep">{{ $v }} →</a>
                                @else
                                    <span class="truncate font-semibold text-eo-text">{{ $v }}</span>
                                @endif
                            </div>
                        @endforeach

                        @if ($selected->notes)
                            <p class="rounded-lg bg-eo-workspace px-3 py-2 text-[11.5px] leading-relaxed text-eo-muted">{{ $selected->notes }}</p>
                        @endif

                        {{-- The offer, which is where the agreed figure comes from. --}}
                        @php
                            $offer = $selected->acceptedProposal() ?? $selected->liveProposal();
                            $mayOffer = auth()->user()?->can('manage-contract') ?? false;
                        @endphp
                        <div class="rounded-lg border border-eo-line bg-eo-workspace px-3 py-2.5">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="eo-label">Offer</span>
                                @if ($offer)
                                    @php [$offerLabel, $offerHex] = \App\Models\Proposal::STATE_META[$offer->state()]; @endphp
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold text-white" style="background: {{ $offerHex }}">{{ $offerLabel }}</span>
                                @endif
                            </div>

                            @if ($offer)
                                <a href="{{ route('proposals.edit', $offer) }}" wire:navigate
                                   class="mt-1.5 flex items-baseline justify-between gap-2 text-[12px] font-semibold text-eo-teal-ink transition hover:text-eo-teal-deep">
                                    <span class="truncate font-mono text-[11px]">{{ $offer->number }}</span>
                                    <span class="shrink-0 tabular-nums">{{ $money($offer->totalCents(), $offer->currencyCode()) }} →</span>
                                </a>
                            @else
                                <p class="mt-1 text-[11.5px] leading-relaxed text-eo-muted">
                                    Nothing sent yet. Winning now opens the event with an empty budget.
                                </p>
                            @endif
                        </div>
                    </div>
                </x-eo.detail-panel>

                <x-eo.action-panel title="Deal actions">
                    @if ($mayOffer && ! $offer)
                        <x-eo.button wire:click="draftProposal({{ $selected->id }})" class="w-full justify-center" size="sm">＋ Draft proposal</x-eo.button>
                    @elseif ($offer)
                        <x-eo.button href="{{ route('proposals.edit', $offer) }}" class="w-full justify-center" size="sm">Open offer</x-eo.button>
                    @endif
                    @if ($selected->event)
                        <x-eo.button href="{{ route('events.hub', $selected->event) }}" class="w-full justify-center" size="sm">Open the event →</x-eo.button>
                    @endif
                    <button type="button" wire:click="$toggle('showActivity')" class="eo-btn-ghost eo-btn-sm w-full justify-center">＋ Log activity</button>
                </x-eo.action-panel>

                {{-- activity --}}
                <div class="eo-soft-card overflow-hidden !p-0">
                    <div class="flex items-center justify-between gap-3 border-b border-eo-line px-4 py-3">
                        <p class="eo-label">Activity</p>
                        <button type="button" wire:click="$toggle('showActivity')" class="eo-btn-ghost eo-btn-sm">＋ Log</button>
                    </div>

                    @if ($showActivity)
                        <div class="space-y-2 border-b border-eo-line bg-eo-workspace p-4">
                            <div class="flex gap-1">
                                @foreach (\App\Support\Taxonomy::options('activity_type') as $tv => $tl)
                                    <button type="button" wire:click="$set('a_type', '{{ $tv }}')"
                                            @class(['flex-1 rounded-lg border py-1.5 text-[10.5px] font-bold transition',
                                                    'border-eo-navy bg-eo-navy text-white' => $a_type === $tv,
                                                    'border-eo-line bg-white text-eo-muted hover:border-eo-teal/30' => $a_type !== $tv])>{{ $tl }}</button>
                                @endforeach
                            </div>
                            <input type="text" wire:model="a_subject" placeholder="What happened?" class="eo-input h-9 text-xs">
                            @error('a_subject')<p class="text-[10.5px] text-eo-risk">{{ $message }}</p>@enderror
                            <textarea wire:model="a_body" rows="2" placeholder="Detail (optional)" class="eo-textarea text-xs"></textarea>
                            <label class="block">
                                <span class="eo-label">Follow up on</span>
                                <input type="date" wire:model="a_follow_up_on" class="eo-input mt-1 h-9 text-xs">
                            </label>
                            <x-eo.button size="sm" wire:click="logActivity" class="h-9 w-full justify-center">Save</x-eo.button>
                        </div>
                    @endif

                    <div class="divide-y divide-eo-line">
                        @forelse ($activities as $a)
                            <div class="px-4 py-2.5">
                                <div class="flex items-baseline gap-2">
                                    <x-eo.status-pill tone="pending">{{ $a->typeLabel() }}</x-eo.status-pill>
                                    <span class="min-w-0 flex-1 truncate text-[12px] font-semibold text-eo-text">{{ $a->subject }}</span>
                                    <span class="shrink-0 text-[10px] tabular-nums text-eo-muted">{{ $a->happened_at->diffForHumans(short: true) }}</span>
                                </div>
                                @if ($a->body)
                                    <p class="mt-1 text-[11px] leading-relaxed text-eo-muted">{{ $a->body }}</p>
                                @endif
                                @if ($a->follow_up_on && ! $a->follow_up_done)
                                    <button type="button" wire:click="completeFollowUp({{ $a->id }})"
                                            class="mt-1.5 inline-flex items-center gap-1.5 rounded-lg bg-eo-warn-soft px-2 py-1 text-[10px] font-bold text-eo-warn ring-1 ring-eo-warn/20 transition hover:bg-eo-warn-soft/60">
                                        ↻ Follow up {{ $a->follow_up_on->format('j M') }} · mark done
                                    </button>
                                @endif
                            </div>
                        @empty
                            <p class="px-4 py-5 text-center text-[11.5px] text-eo-muted">Nothing logged yet.</p>
                        @endforelse
                    </div>
                </div>
            @else
                <x-eo.detail-panel title="Action Panel" subtitle="Select a deal from the queue">
                    <p class="text-[13px] text-eo-muted">Pick a deal from the pipeline. Detail and next actions open here — nothing expands in place.</p>
                </x-eo.detail-panel>
            @endif

            {{-- follow-ups due, whatever is selected --}}
            @if ($dueFollowUps->isNotEmpty())
                <div class="eo-soft-card p-4">
                    <p class="eo-label mb-2">Follow-ups due</p>
                    <div class="space-y-1.5">
                        @foreach ($dueFollowUps as $a)
                            <button type="button" wire:click="select({{ $a->deal_id }})"
                                    class="flex w-full items-center gap-2 rounded-lg bg-eo-workspace px-2.5 py-1.5 text-left transition hover:bg-eo-teal-soft/40">
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-eo-risk"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-[11.5px] font-semibold text-eo-text">{{ $a->subject }}</span>
                                    <span class="block truncate text-[10px] text-eo-muted">{{ $a->deal?->title ?? $a->client?->name }}</span>
                                </span>
                                <span class="shrink-0 text-[10px] font-bold tabular-nums text-eo-risk">{{ $a->follow_up_on->format('j M') }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </aside>
    </div>

    @script
    <script>
        /* Drag a deal to another lane. The stage buttons on each card do the same
           thing and stay the accessible path — this is a shortcut, not the only
           way through. Winning is deliberately NOT draggable: it creates an event
           and redirects, which is too much to trigger by accident. */
        const wire = $wire;
        document.querySelectorAll('[data-lane]').forEach(lane => {
            if (lane.dataset.sortable) return;
            lane.dataset.sortable = '1';
            new Sortable(lane, {
                group: 'pipeline',
                animation: 150,
                ghostClass: 'opacity-40',
                onAdd(event) {
                    const id = event.item.dataset.deal;
                    const stage = event.to.dataset.lane;
                    if (id && stage) wire.moveTo(Number(id), stage);
                },
            });
        });
    </script>
    @endscript

    {{-- ══════════ Deal form ══════════ --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit deal' : 'New deal'" close="$set('showForm', false)" max="2xl">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="eo-label mb-1">What is it?</label>
                    <input type="text" wire:model="title" placeholder="e.g. Regional Summit 2027" class="eo-input">
                    @error('title')<p class="mt-1 text-[11px] text-eo-risk">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="eo-label mb-1">Client</label>
                    <select wire:model.live="client_id" class="eo-select">
                        @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                </div>

                <div>
                    <label class="eo-label mb-1">Contact</label>
                    <select wire:model="contact_id" class="eo-select">
                        <option value="">— none —</option>
                        @foreach ($contacts as $c)<option value="{{ $c->id }}">{{ $c->name }}{{ $c->title ? ' · '.$c->title : '' }}</option>@endforeach
                    </select>
                </div>

                <div>
                    <label class="eo-label mb-1">Stage</label>
                    <select wire:model="stage" class="eo-select">
                        @foreach (\App\Models\Deal::STAGES as $sv => [$sl, $sp, $sh])<option value="{{ $sv }}">{{ $sl }}</option>@endforeach
                    </select>
                </div>

                <div>
                    <label class="eo-label mb-1">Value</label>
                    <input type="number" step="0.01" min="0" wire:model="value" placeholder="0.00" class="eo-input">
                </div>

                <div>
                    <label class="eo-label mb-1">Owner</label>
                    <select wire:model="owner_id" class="eo-select">
                        <option value="">— unassigned —</option>
                        @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                    </select>
                </div>

                <div>
                    <label class="eo-label mb-1">Source</label>
                    <select wire:model="source" class="eo-select">
                        <option value="">—</option>
                        @foreach (\App\Support\Taxonomy::options('deal_source') as $sv => $sl)<option value="{{ $sv }}">{{ $sl }}</option>@endforeach
                    </select>
                </div>

                <div>
                    <label class="eo-label mb-1">Decision expected</label>
                    <input type="date" wire:model="expected_close_on" class="eo-input">
                </div>

                <div>
                    <label class="eo-label mb-1">Event would run</label>
                    <input type="date" wire:model="expected_event_on" class="eo-input">
                </div>

                <div class="sm:col-span-2">
                    <label class="eo-label mb-1">Notes</label>
                    <textarea wire:model="notes" rows="3" class="eo-textarea" placeholder="What do we know?"></textarea>
                </div>
            </div>

            <x-slot:footer>
                <button type="button" wire:click="$set('showForm', false)" class="eo-btn-ghost eo-btn-sm">Cancel</button>
                <x-eo.button size="sm" wire:click="saveDeal">{{ $editingId ? 'Save deal' : 'Create deal' }}</x-eo.button>
            </x-slot:footer>
        </x-modal>
    @endif

    {{-- ══════════ Why was it lost? ══════════ --}}
    @if ($losingId)
        <x-modal title="Mark this deal lost" subtitle="A pipeline without loss reasons teaches you nothing."
                 close="$set('losingId', null)" max="md">
            <div>
                <label class="eo-label mb-1">What happened?</label>
                <input type="text" wire:model="lostReason" placeholder="e.g. Budget cut · went to a competitor · postponed" class="eo-input">
            </div>
            <x-slot:footer>
                <button type="button" wire:click="$set('losingId', null)" class="eo-btn-ghost eo-btn-sm">Cancel</button>
                <button type="button" wire:click="confirmLost" class="eo-btn-danger eo-btn-sm">Mark lost</button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
