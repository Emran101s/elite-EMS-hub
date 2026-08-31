@php
    $money = fn (int $cents, string $cur = 'JOD') => \App\Support\Money::abbreviated($cents, $cur);
    $f = $forecast;
    $unpriced = $f['count'] > 0 && $f['value'] === 0;
    $offer = $selected?->acceptedProposal() ?? $selected?->liveProposal();
    $mayOffer = auth()->user()?->can('manage-contract') ?? false;
@endphp

<div class="space-y-5">

    <x-cc.header eyebrow="Commercial Command" title="CRM Pipeline" subtitle="Queue → Deal → Action Panel. Every opportunity from first conversation to signed event.">
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-2.5 py-1 text-[10.5px] font-bold uppercase tracking-wide text-gold-700">
                <span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Deal desk
            </span>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted" />
                <input type="search" wire:model.live.debounce.300ms="q" placeholder="Search deals or clients…"
                       class="h-10 w-52 rounded-full border border-line bg-white ps-9 text-xs outline-none focus:border-gold-400">
            </div>
            <a href="{{ route('clients.index') }}" class="rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:-translate-y-0.5 hover:border-navy-300">Clients →</a>
            <button type="button" wire:click="newDeal" class="rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:-translate-y-0.5 hover:bg-gold-400">＋ New deal</button>
        </x-slot:actions>
    </x-cc.header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <x-cc.kpi-tile label="Open deals" :value="$f['count']" hint="still in play" tone="live" />
        {{-- "JD 0 · if every one lands" is a forecast, and a forecast of zero
             reads as "no pipeline". With deals open and none of them priced,
             the honest answer is that there is no figure to forecast from —
             which also says what to do about it. --}}
        <x-cc.kpi-tile label="Pipeline value" :value="$unpriced ? '—' : $money($f['value'])"
                       :hint="$unpriced ? 'no open deal carries a figure' : 'if every one lands'"
                       :tone="$unpriced ? 'warn' : null" />
        <x-cc.kpi-tile label="Weighted" :value="$unpriced ? '—' : $money($f['weighted'])"
                       :hint="$unpriced ? 'needs a value to weigh' : 'by chance of winning'"
                       :tone="$unpriced ? 'warn' : 'ok'" />
        <x-cc.kpi-tile label="Won this month" :value="$f['wonThisMonth']" hint="became events" tone="ok" />
        <x-cc.kpi-tile label="Going stale" :value="$f['stale']" :hint="$f['stale'] ? 'nobody has touched them' : 'everything is moving'" :tone="$f['stale'] ? 'risk' : 'ok'" />
    </div>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="min-w-0">
            <div class="mb-3 flex flex-wrap items-baseline gap-2">
                <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Deal queue · pipeline</p>
                <span class="text-[12px] text-muted">select to inspect · drag lanes or use stage actions</span>
            </div>

            <div class="scrollbar-none -mx-1 overflow-x-auto px-1">
                <div class="grid min-w-[860px] grid-cols-4 gap-4" data-pipeline>
                    @foreach ($lanes as $lane)
                        <div>
                            <div class="mb-2.5 flex items-center gap-2">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $lane['hex'] }}"></span>
                                <b class="text-xs font-bold text-ink">{{ $lane['label'] }}</b>
                                <span class="ml-auto grid h-5 min-w-5 place-items-center rounded-full bg-white px-1.5 text-[10.5px] font-bold text-muted ring-1 ring-line">{{ $lane['deals']->count() }}</span>
                            </div>

                            <div data-lane="{{ $lane['key'] }}" class="min-h-[60px]">
                            @forelse ($lane['deals'] as $deal)
                                <x-crm.deal-card :deal="$deal" :selected="$selected?->id === $deal->id" />
                            @empty
                                <p class="rounded-lg border border-dashed border-line px-3 py-6 text-center text-[11px] text-muted">Drop a deal here.</p>
                            @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            @if ($closed->isNotEmpty())
                <x-crm.recently-closed :closed="$closed" />
            @endif
        </div>

        {{-- Action Panel — deal inspector --}}
        <aside class="flex flex-col gap-4 self-start xl:sticky xl:top-4">
            @if ($selected)
                <x-crm.inspector :selected="$selected" :offer="$offer" />
                <x-crm.action-panel :selected="$selected" :offer="$offer" :may-offer="$mayOffer" />
                <x-crm.activity-log :activities="$activities" :show-activity="$showActivity" :a-type="$a_type" />
            @else
                <div class="rounded-lg border border-line bg-white p-4">
                    <p class="text-[14px] font-bold text-ink">Action Panel</p>
                    <p class="mt-0.5 text-[11.5px] text-muted">Select a deal from the queue</p>
                    <p class="mt-3 text-[13px] text-muted">Pick a deal from the pipeline. Detail and next actions open here — nothing expands in place.</p>
                </div>
            @endif

            @if ($dueFollowUps->isNotEmpty())
                <x-crm.follow-ups-card :due-follow-ups="$dueFollowUps" />
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
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">What is it?</label>
                    <input type="text" wire:model="title" placeholder="e.g. Regional Summit 2027" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
                    @error('title')<p class="mt-1 text-[11px] text-danger-ink">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Client</label>
                    <select wire:model.live="client_id" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink focus:border-navy-300 focus:outline-none">
                        @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                </div>

                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Contact</label>
                    <select wire:model="contact_id" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink focus:border-navy-300 focus:outline-none">
                        <option value="">— none —</option>
                        @foreach ($contacts as $c)<option value="{{ $c->id }}">{{ $c->name }}{{ $c->title ? ' · '.$c->title : '' }}</option>@endforeach
                    </select>
                </div>

                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Stage</label>
                    <select wire:model="stage" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink focus:border-navy-300 focus:outline-none">
                        @foreach (\App\Models\Deal::STAGES as $sv => [$sl, $sp, $sh])<option value="{{ $sv }}">{{ $sl }}</option>@endforeach
                    </select>
                </div>

                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Value</label>
                    <input type="number" step="0.01" min="0" wire:model="value" placeholder="0.00" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
                </div>

                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Owner</label>
                    <select wire:model="owner_id" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink focus:border-navy-300 focus:outline-none">
                        <option value="">— unassigned —</option>
                        @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                    </select>
                </div>

                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Source</label>
                    <select wire:model="source" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink focus:border-navy-300 focus:outline-none">
                        <option value="">—</option>
                        @foreach (\App\Support\Taxonomy::options('deal_source') as $sv => $sl)<option value="{{ $sv }}">{{ $sl }}</option>@endforeach
                    </select>
                </div>

                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Decision expected</label>
                    <input type="date" wire:model="expected_close_on" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
                </div>

                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Event would run</label>
                    <input type="date" wire:model="expected_event_on" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
                </div>

                <div class="sm:col-span-2">
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Notes</label>
                    <textarea wire:model="notes" rows="3" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none" placeholder="What do we know?"></textarea>
                </div>
            </div>

            <x-slot:footer>
                <button type="button" wire:click="$set('showForm', false)" class="btn-sm rounded-full border border-line font-semibold text-ink transition hover:border-gold-300">Cancel</button>
                <button type="button" wire:click="saveDeal" class="rounded-full bg-gold-500 px-4 py-2 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">{{ $editingId ? 'Save deal' : 'Create deal' }}</button>
            </x-slot:footer>
        </x-modal>
    @endif

    {{-- ══════════ Why was it lost? ══════════ --}}
    @if ($losingId)
        <x-modal title="Mark this deal lost" subtitle="A pipeline without loss reasons teaches you nothing."
                 close="$set('losingId', null)" max="md">
            <div>
                <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">What happened?</label>
                <input type="text" wire:model="lostReason" placeholder="e.g. Budget cut · went to a competitor · postponed" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
            </div>
            <x-slot:footer>
                <button type="button" wire:click="$set('losingId', null)" class="btn-sm rounded-full border border-line font-semibold text-ink transition hover:border-gold-300">Cancel</button>
                <button type="button" wire:click="confirmLost" class="btn-sm rounded-full bg-danger-soft font-bold text-danger-ink transition hover:brightness-95">Mark lost</button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
