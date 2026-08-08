@php
    $money = fn (int $cents, string $cur = 'JOD') => \App\Support\Money::abbreviated($cents, $cur);
@endphp

<div>
    {{-- ══════════ Forecast ══════════ --}}
    @php
        $f = $forecast;
        $tiles = [
            ['Open deals', $f['count'], 'folder', null, null, 'still in play'],
            ['Pipeline value', $money($f['value']), 'currency', null, null, 'if every one lands'],
            ['Weighted', $money($f['weighted']), 'chart', $f['value'] ? (int) round($f['weighted'] / max($f['value'], 1) * 100) : 0, 'bg-track', 'by chance of winning'],
            ['Won this month', $f['wonThisMonth'], 'star', null, null, 'closed and became events'],
            ['Going stale', $f['stale'], 'bell', null, null, $f['stale'] ? 'nobody has touched them' : 'everything is moving',
                $f['stale'] ? 'text-risk' : 'text-navy-900'],
        ];
    @endphp
    <x-stat-strip :stats="$tiles" />

    {{-- ══════════ Toolbar ══════════ --}}
    <div class="mt-4 flex flex-wrap items-center gap-2.5">
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-navy-300" />
            <input type="search" wire:model.live.debounce.300ms="q" placeholder="Search deals or clients…"
                   class="input h-9 w-52 !rounded-xl !py-0 !ps-9 text-xs">
        </div>
        <a href="{{ route('clients.index') }}" class="btn-ghost btn-sm">Clients &amp; contacts →</a>
        <button type="button" wire:click="newDeal" class="btn-gold btn-sm ml-auto">＋ New deal</button>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1fr)_330px]">
        <div class="min-w-0">
            {{-- ══════════ THE PIPELINE ══════════ --}}
            <div class="mb-3 flex flex-wrap items-baseline gap-2">
                <h2 class="pf text-h1 font-bold text-navy-900">Pipeline</h2>
                <span class="text-xs text-muted">click a deal to inspect · drag it to another lane, or use the stage button</span>
            </div>

            <div class="scrollbar-none -mx-1 overflow-x-auto px-1">
                <div class="grid min-w-[860px] grid-cols-4 gap-4" data-pipeline>
                    @foreach ($lanes as $lane)
                        <div>
                            <div class="mb-2.5 flex items-center gap-2">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $lane['hex'] }}"></span>
                                <b class="text-xs font-bold text-navy-900">{{ $lane['label'] }}</b>
                                <span class="ml-auto grid h-5 min-w-5 place-items-center rounded-full bg-white px-1.5 text-[10.5px] font-bold text-navy-600 ring-1 ring-line">{{ $lane['deals']->count() }}</span>
                            </div>

                            <div data-lane="{{ $lane['key'] }}" class="min-h-[60px]">
                            @forelse ($lane['deals'] as $deal)
                                <div wire:key="deal-{{ $deal->id }}" data-deal="{{ $deal->id }}"
                                     @class([
                                         'mb-3 rounded-2xl border bg-white p-3.5 transition',
                                         'border-gold-400 ring-1 ring-gold-300' => $selected?->id === $deal->id,
                                         'border-line hover:-translate-y-0.5 hover:border-gold-300 hover:shadow-[0_16px_32px_-22px_rgba(11,31,58,0.55)]' => $selected?->id !== $deal->id,
                                     ])>
                                    <button type="button" wire:click="select({{ $deal->id }})" class="block w-full text-left">
                                        <span class="flex items-start gap-2">
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-[13px] font-bold leading-tight text-navy-900">{{ $deal->title }}</span>
                                                <span class="mt-1 block truncate text-[10.5px] text-muted">{{ $deal->client?->name }}</span>
                                            </span>
                                            @if ($deal->isStale())
                                                <span class="shrink-0 rounded-md bg-risk/10 px-1.5 py-0.5 text-[9px] font-bold text-risk" title="Past its expected close date">STALE</span>
                                            @endif
                                        </span>

                                        <span class="mt-2.5 flex items-baseline justify-between gap-2">
                                            <span class="pf text-[15px] font-bold text-navy-900">{{ $money($deal->value_cents, $deal->currency) }}</span>
                                            <span class="text-[10.5px] font-bold tabular-nums text-navy-400">{{ $deal->probability }}%</span>
                                        </span>
                                        <span class="mt-1.5 block h-1 overflow-hidden rounded-full bg-navy-50">
                                            <span class="block h-full rounded-full" style="width: {{ $deal->probability }}%; background: {{ $deal->stageHex() }}"></span>
                                        </span>

                                        <span class="mt-2.5 flex items-center gap-2 border-t border-line pt-2 text-[10.5px] text-muted">
                                            @if ($deal->owner)
                                                <x-user-avatar :user="$deal->owner" size="h-5 w-5" />
                                            @endif
                                            <span class="truncate">{{ $deal->contact?->name ?? 'No contact' }}</span>
                                            <span class="ml-auto shrink-0 tabular-nums">{{ $deal->expected_close_on?->format('j M') ?? '—' }}</span>
                                        </span>
                                    </button>

                                    {{-- move it on --}}
                                    <div class="mt-2.5 flex items-center gap-1 border-t border-line pt-2.5">
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
                                            $moveClass = 'flex-1 rounded-lg bg-navy-50 py-1.5 text-[11px] font-bold text-navy-700 transition hover:bg-navy-900 hover:text-white';
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
                                                class="grid h-7 w-7 place-items-center rounded-lg text-navy-300 transition hover:bg-risk/10 hover:text-risk">✕</button>
                                    </div>
                                </div>
                            @empty
                                <p class="rounded-2xl border border-dashed border-line px-3 py-6 text-center text-[11px] text-muted">Drop a deal here.</p>
                            @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- ══════════ Closed ══════════ --}}
            @if ($closed->isNotEmpty())
                <div class="mt-5">
                    <p class="eyebrow mb-2">Recently closed</p>
                    <div class="card divide-y divide-line">
                        @foreach ($closed as $deal)
                            <div class="flex items-center gap-3 px-4 py-2.5">
                                <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $deal->stageHex() }}"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-[12.5px] font-semibold text-navy-900">{{ $deal->title }}</span>
                                    <span class="block truncate text-[10.5px] text-muted">
                                        {{ $deal->client?->name }}
                                        @if ($deal->stage === 'lost' && $deal->lost_reason) · {{ $deal->lost_reason }} @endif
                                    </span>
                                </span>
                                <span class="shrink-0 text-[11px] font-bold tabular-nums text-navy-600">{{ $money($deal->value_cents, $deal->currency) }}</span>
                                @if ($deal->event)
                                    <a href="{{ route('events.hub', $deal->event) }}" class="btn-ghost btn-xs shrink-0">Open event →</a>
                                @else
                                    <button type="button" wire:click="reopen({{ $deal->id }})" class="btn-ghost btn-xs shrink-0">Reopen</button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- ══════════ INSPECTOR ══════════ --}}
        <aside class="flex flex-col gap-4 self-start xl:sticky xl:top-4">
            @if ($selected)
                <div class="card overflow-hidden">
                    <div class="flex items-start justify-between gap-3 border-b border-line px-4 py-3">
                        <div class="min-w-0">
                            <p class="eyebrow">Deal</p>
                            <p class="pf mt-1 truncate text-[17px] font-bold text-navy-900">{{ $selected->title }}</p>
                        </div>
                        <button type="button" wire:click="editDeal({{ $selected->id }})" class="btn-ghost btn-xs shrink-0">Edit</button>
                    </div>

                    <div class="space-y-3.5 p-4">
                        <div>
                            <div class="flex items-baseline justify-between gap-2 text-[11px]">
                                <span class="text-navy-600">{{ $selected->stageLabel() }}</span>
                                <span class="font-bold tabular-nums" style="color: {{ $selected->stageHex() }}">{{ $selected->probability }}%</span>
                            </div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-navy-50">
                                <div class="h-full rounded-full" style="width: {{ $selected->probability }}%; background: {{ $selected->stageHex() }}"></div>
                            </div>
                        </div>

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
                            <div class="flex justify-between gap-3 border-b border-line/70 pb-2 text-[12px] last:border-0">
                                <span class="text-muted">{{ $k }}</span>
                                @if ($href)
                                    <a href="{{ $href }}" class="truncate font-semibold text-gold-700 transition hover:text-gold-800">{{ $v }} →</a>
                                @else
                                    <span class="truncate font-semibold text-navy-900">{{ $v }}</span>
                                @endif
                            </div>
                        @endforeach

                        @if ($selected->notes)
                            <p class="rounded-lg bg-page/70 px-3 py-2 text-[11.5px] leading-relaxed text-navy-600">{{ $selected->notes }}</p>
                        @endif

                        {{-- The offer, which is where the agreed figure comes from. --}}
                        @php
                            $offer = $selected->acceptedProposal() ?? $selected->liveProposal();
                            $mayOffer = auth()->user()?->can('manage-contract') ?? false;
                        @endphp
                        <div class="rounded-lg border border-line bg-page/60 px-3 py-2.5">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="eyebrow">Offer</span>
                                @if ($offer)
                                    @php [$offerLabel, $offerHex] = \App\Models\Proposal::STATE_META[$offer->state()]; @endphp
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold text-white" style="background: {{ $offerHex }}">{{ $offerLabel }}</span>
                                @endif
                            </div>

                            @if ($offer)
                                <a href="{{ route('proposals.edit', $offer) }}" wire:navigate
                                   class="mt-1.5 flex items-baseline justify-between gap-2 text-[12px] font-semibold text-gold-700 transition hover:text-gold-800">
                                    <span class="truncate font-mono text-[11px]">{{ $offer->number }}</span>
                                    <span class="shrink-0 tabular-nums">{{ $money($offer->totalCents(), $offer->currencyCode()) }} →</span>
                                </a>
                            @else
                                <p class="mt-1 text-[11.5px] leading-relaxed text-muted">
                                    Nothing sent yet. Winning now opens the event with an empty budget.
                                </p>
                                @if ($mayOffer)
                                    <button type="button" wire:click="draftProposal({{ $selected->id }})"
                                            class="btn-gold mt-2 flex h-8 w-full items-center justify-center !rounded-lg text-[11.5px]">
                                        ＋ Draft proposal
                                    </button>
                                @endif
                            @endif
                        </div>

                        @if ($selected->event)
                            <a href="{{ route('events.hub', $selected->event) }}" class="btn-gold flex h-9 w-full items-center justify-center !rounded-lg text-[12px]">Open the event →</a>
                        @endif
                    </div>
                </div>

                {{-- activity --}}
                <div class="card overflow-hidden">
                    <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                        <p class="eyebrow">Activity</p>
                        <button type="button" wire:click="$toggle('showActivity')" class="btn-ghost btn-xs">＋ Log</button>
                    </div>

                    @if ($showActivity)
                        <div class="space-y-2 border-b border-line bg-page/50 p-4">
                            <div class="flex gap-1">
                                @foreach (\App\Support\Taxonomy::options('activity_type') as $tv => $tl)
                                    <button type="button" wire:click="$set('a_type', '{{ $tv }}')"
                                            @class(['flex-1 rounded-lg border py-1.5 text-[10.5px] font-bold transition',
                                                    'border-navy-900 bg-navy-900 text-white' => $a_type === $tv,
                                                    'border-line bg-white text-navy-500 hover:border-navy-200' => $a_type !== $tv])>{{ $tl }}</button>
                                @endforeach
                            </div>
                            <input type="text" wire:model="a_subject" placeholder="What happened?" class="input h-9 text-xs">
                            @error('a_subject')<p class="text-[10.5px] text-risk">{{ $message }}</p>@enderror
                            <textarea wire:model="a_body" rows="2" placeholder="Detail (optional)" class="input text-xs"></textarea>
                            <label class="block">
                                <span class="eyebrow">Follow up on</span>
                                <input type="date" wire:model="a_follow_up_on" class="input mt-1 h-9 text-xs">
                            </label>
                            <button type="button" wire:click="logActivity" class="btn-gold h-9 w-full !rounded-lg text-[12px]">Save</button>
                        </div>
                    @endif

                    <div class="divide-y divide-line">
                        @forelse ($activities as $a)
                            <div class="px-4 py-2.5">
                                <div class="flex items-baseline gap-2">
                                    <span class="chip">{{ $a->typeLabel() }}</span>
                                    <span class="min-w-0 flex-1 truncate text-[12px] font-semibold text-navy-900">{{ $a->subject }}</span>
                                    <span class="shrink-0 text-[10px] tabular-nums text-navy-300">{{ $a->happened_at->diffForHumans(short: true) }}</span>
                                </div>
                                @if ($a->body)
                                    <p class="mt-1 text-[11px] leading-relaxed text-muted">{{ $a->body }}</p>
                                @endif
                                @if ($a->follow_up_on && ! $a->follow_up_done)
                                    <button type="button" wire:click="completeFollowUp({{ $a->id }})"
                                            class="mt-1.5 inline-flex items-center gap-1.5 rounded-lg bg-gold-50 px-2 py-1 text-[10px] font-bold text-gold-700 ring-1 ring-gold-200 transition hover:bg-gold-100">
                                        ↻ Follow up {{ $a->follow_up_on->format('j M') }} · mark done
                                    </button>
                                @endif
                            </div>
                        @empty
                            <p class="px-4 py-5 text-center text-[11.5px] text-muted">Nothing logged yet.</p>
                        @endforelse
                    </div>
                </div>
            @else
                <div class="card p-5 text-center">
                    <p class="eyebrow">Inspector</p>
                    <p class="mt-3 text-[12.5px] leading-relaxed text-muted">Pick a deal from the pipeline. It opens here — nothing expands, nothing moves.</p>
                </div>
            @endif

            {{-- follow-ups due, whatever is selected --}}
            @if ($dueFollowUps->isNotEmpty())
                <div class="card p-4">
                    <p class="eyebrow mb-2">Follow-ups due</p>
                    <div class="space-y-1.5">
                        @foreach ($dueFollowUps as $a)
                            <button type="button" wire:click="select({{ $a->deal_id }})"
                                    class="flex w-full items-center gap-2 rounded-lg bg-page/70 px-2.5 py-1.5 text-left transition hover:bg-gold-50">
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-risk"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-[11.5px] font-semibold text-navy-900">{{ $a->subject }}</span>
                                    <span class="block truncate text-[10px] text-muted">{{ $a->deal?->title ?? $a->client?->name }}</span>
                                </span>
                                <span class="shrink-0 text-[10px] font-bold tabular-nums text-risk">{{ $a->follow_up_on->format('j M') }}</span>
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
                <label class="block sm:col-span-2">
                    <span class="field-label">What is it?</span>
                    <input type="text" wire:model="title" placeholder="e.g. Regional Summit 2027" class="input">
                    @error('title')<p class="mt-1 text-[11px] text-risk">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="field-label">Client</span>
                    <select wire:model.live="client_id" class="input">
                        @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="field-label">Contact</span>
                    <select wire:model="contact_id" class="input">
                        <option value="">— none —</option>
                        @foreach ($contacts as $c)<option value="{{ $c->id }}">{{ $c->name }}{{ $c->title ? ' · '.$c->title : '' }}</option>@endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="field-label">Stage</span>
                    <select wire:model="stage" class="input">
                        @foreach (\App\Models\Deal::STAGES as $sv => [$sl, $sp, $sh])<option value="{{ $sv }}">{{ $sl }}</option>@endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="field-label">Value</span>
                    <input type="number" step="0.01" min="0" wire:model="value" placeholder="0.00" class="input">
                </label>

                <label class="block">
                    <span class="field-label">Owner</span>
                    <select wire:model="owner_id" class="input">
                        <option value="">— unassigned —</option>
                        @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="field-label">Source</span>
                    <select wire:model="source" class="input">
                        <option value="">—</option>
                        @foreach (\App\Support\Taxonomy::options('deal_source') as $sv => $sl)<option value="{{ $sv }}">{{ $sl }}</option>@endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="field-label">Decision expected</span>
                    <input type="date" wire:model="expected_close_on" class="input">
                </label>

                <label class="block">
                    <span class="field-label">Event would run</span>
                    <input type="date" wire:model="expected_event_on" class="input">
                </label>

                <label class="block sm:col-span-2">
                    <span class="field-label">Notes</span>
                    <textarea wire:model="notes" rows="3" class="input" placeholder="What do we know?"></textarea>
                </label>
            </div>

            <x-slot:footer>
                <button type="button" wire:click="$set('showForm', false)" class="btn-ghost btn-sm">Cancel</button>
                <button type="button" wire:click="saveDeal" class="btn-gold btn-sm">{{ $editingId ? 'Save deal' : 'Create deal' }}</button>
            </x-slot:footer>
        </x-modal>
    @endif

    {{-- ══════════ Why was it lost? ══════════ --}}
    @if ($losingId)
        <x-modal title="Mark this deal lost" subtitle="A pipeline without loss reasons teaches you nothing."
                 close="$set('losingId', null)" max="md">
            <label class="block">
                <span class="field-label">What happened?</span>
                <input type="text" wire:model="lostReason" placeholder="e.g. Budget cut · went to a competitor · postponed" class="input">
            </label>
            <x-slot:footer>
                <button type="button" wire:click="$set('losingId', null)" class="btn-ghost btn-sm">Cancel</button>
                <button type="button" wire:click="confirmLost" class="btn-danger btn-sm">Mark lost</button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
