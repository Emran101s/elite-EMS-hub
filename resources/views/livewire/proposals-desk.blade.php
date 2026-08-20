@php
    use App\Models\Proposal;

    $may = auth()->user()?->can('manage-contract') ?? false;
    $states = ['draft', 'sent', 'expired', 'accepted', 'declined'];
    $sel = $selected;
    $selState = $sel?->state();
@endphp

<div class="eo-event-atmosphere space-y-5 rounded-[24px]">

    <x-eo.page-header
        eyebrow="Commercial Command"
        title="Proposals"
        subtitle="Queue → Offer → Commercial Control. What is out, what is expiring, and what came back a yes."
    >
        <x-slot:actions>
            <span class="eo-journey-chip">Commercial</span>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-eo-muted" />
                <input type="search" wire:model.live.debounce.300ms="q" placeholder="Number, title, client…"
                       class="eo-input h-10 w-52 !py-0 !ps-9 text-xs xl:w-64">
            </div>
            @if ($may)
                <x-eo.button size="sm" wire:click="create">＋ New proposal</x-eo.button>
            @endif
        </x-slot:actions>
    </x-eo.page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($figures as $f)
            @php
                $tone = match ($f['tone'] ?? '') {
                    'green' => 'ok', 'red' => 'risk', 'gold', 'amber' => 'warn', 'blue', 'violet' => 'live', default => null,
                };
            @endphp
            <x-eo.metric-pill :label="$f['label']" :value="$f['value']" :hint="$f['note'] ?? null" :tone="$tone" />
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-1">
        <button type="button" wire:click="setState('all')"
                @class(['eo-btn-ghost eo-btn-sm', '!bg-eo-navy !text-white' => $state === 'all'])>All</button>
        @foreach ($states as $key)
            @php [$label, $hex] = Proposal::STATE_META[$key]; @endphp
            <button type="button" wire:click="setState('{{ $key }}')"
                    @class(['eo-btn-ghost eo-btn-sm inline-flex items-center gap-1.5', '!bg-eo-navy !text-white' => $state === $key])>
                <span class="h-1.5 w-1.5 rounded-full" style="background: {{ $hex }}"></span>{{ $label }}
            </button>
        @endforeach
        <p class="ms-auto text-[12px] text-eo-muted">{{ $rows->count() }} {{ str('offer')->plural($rows->count()) }} in queue</p>
    </div>

    @if ($ready->isNotEmpty())
        <x-eo.soft-card class="!bg-eo-warn-soft/40">
            <button type="button" wire:click="toggleReady" class="flex w-full items-center gap-3 text-start">
                <span class="grid h-8 w-8 place-items-center rounded-xl bg-eo-warn/20 text-eo-warn-ink">
                    <x-icon name="document" class="h-4 w-4" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[13px] font-bold text-eo-text">{{ $ready->count() }} {{ str('deal')->plural($ready->count()) }} waiting for an offer</span>
                    <span class="block text-[11px] text-eo-muted">At proposal or negotiation — nothing sent</span>
                </span>
                <x-icon name="chevron" class="h-4 w-4 text-eo-muted transition {{ $showReady ? 'rotate-180' : '' }}" />
            </button>
            @if ($showReady)
                <div class="mt-3 space-y-2 border-t border-eo-line pt-3">
                    @foreach ($ready as $deal)
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[12.5px] font-bold text-eo-text">{{ $deal->title }}</span>
                                <span class="block truncate text-[11px] text-eo-muted">{{ $deal->client?->name ?? 'No client' }}</span>
                            </span>
                            <span class="text-[13px] font-bold tabular-nums">JD{{ number_format($deal->value_cents / 100) }}</span>
                            @if ($may)
                                <x-eo.button size="sm" wire:click="draftFor({{ $deal->id }})">Draft offer</x-eo.button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </x-eo.soft-card>
    @endif

    @if ($rows->isEmpty())
        <x-eo.empty-state title="No offer in view" hint="Clear filters, draft from a waiting deal, or create a new proposal." icon="document">
            <x-slot:actions>
                @if ($may)<x-eo.button wire:click="create">＋ New proposal</x-eo.button>@endif
            </x-slot:actions>
        </x-eo.empty-state>
    @else
        <div class="grid gap-4 xl:grid-cols-12">
            {{-- Master: Queue --}}
            <div class="xl:col-span-4">
                <x-eo.queue-list title="Offer queue">
                    @foreach ($rows as $p)
                        @php $active = $sel?->id === $p->id; @endphp
                        <button type="button" wire:click="select({{ $p->id }})" wire:key="prop-{{ $p->id }}"
                                class="w-full text-start">
                            @if ($active)
                                <x-eo.selected-dark-card>
                                    <p class="font-mono text-[11px] text-eo-teal-lit">{{ $p->number }}</p>
                                    <p class="mt-1 truncate text-[14px] font-semibold text-white">{{ $p->title }}</p>
                                    <p class="mt-1 truncate text-[12px] text-white/50">{{ $p->client?->name ?? '—' }}</p>
                                    <div class="mt-3 flex items-center justify-between gap-2">
                                        <span class="text-[15px] font-bold tabular-nums text-white">JD{{ number_format($p->totalCents() / 100) }}</span>
                                        <x-eo.status-pill tone="live">{{ $p->stateLabel() }}</x-eo.status-pill>
                                    </div>
                                </x-eo.selected-dark-card>
                            @else
                                <div class="rounded-2xl border border-eo-line bg-white px-4 py-3 transition hover:border-eo-teal/30 hover:shadow-eo">
                                    <p class="font-mono text-[11px] text-eo-muted">{{ $p->number }}</p>
                                    <p class="mt-0.5 truncate text-[13px] font-bold text-eo-text">{{ $p->title }}</p>
                                    <div class="mt-2 flex items-center justify-between gap-2">
                                        <span class="truncate text-[11px] text-eo-muted">{{ $p->client?->name ?? '—' }}</span>
                                        <span class="text-[12px] font-bold tabular-nums">{{ number_format($p->totalCents() / 100) }}</span>
                                    </div>
                                </div>
                            @endif
                        </button>
                    @endforeach
                </x-eo.queue-list>
            </div>

            {{-- Detail --}}
            <div class="xl:col-span-5">
                @if ($sel)
                    <x-eo.detail-panel title="{{ $sel->title }}" subtitle="{{ $sel->number }} · {{ $sel->client?->name ?? 'No client' }}">
                        <x-slot:header>
                            <x-eo.status-pill tone="{{ in_array($selState, ['expired', 'declined'], true) ? 'risk' : ($selState === 'accepted' ? 'ok' : 'live') }}">
                                {{ $sel->stateLabel() }}
                            </x-eo.status-pill>
                        </x-slot:header>

                        <x-eo.commercial-card
                            title="Offer total"
                            :subtitle="$sel->lines->count().' '.str('line')->plural($sel->lines->count())"
                            :value="'JD'.number_format($sel->totalCents() / 100)"
                            :meta="$sel->valid_until ? ('Valid until '.$sel->valid_until->format('j M Y')) : 'No validity date'"
                            :tone="$selState === 'accepted' ? 'ok' : ($selState === 'expired' ? 'warn' : 'premium')"
                        />

                        <div class="mt-4 space-y-2 text-[13px]">
                            @foreach ([
                                ['Contact', $sel->contact?->name ?? '—'],
                                ['Owner', $sel->owner?->name ?? '—'],
                                ['Issued', $sel->issued_on?->format('j M Y') ?? '—'],
                                ['Days left', $sel->daysLeft() !== null ? ($sel->daysLeft() < 0 ? abs($sel->daysLeft()).'d overdue' : $sel->daysLeft().'d') : '—'],
                            ] as [$k, $v])
                                <div class="flex justify-between gap-3 border-b border-eo-line/70 pb-2">
                                    <span class="text-eo-muted">{{ $k }}</span>
                                    <span class="font-semibold text-eo-text">{{ $v }}</span>
                                </div>
                            @endforeach
                        </div>
                    </x-eo.detail-panel>
                @endif
            </div>

            {{-- Action: Commercial Control Panel --}}
            <div class="xl:col-span-3">
                <x-eo.action-panel title="Commercial Control">
                    @if ($sel && $may)
                        <x-eo.button href="{{ route('proposals.edit', $sel) }}" class="w-full justify-center" size="sm">Open editor</x-eo.button>
                        <x-eo.button variant="ghost" href="{{ route('proposals.pdf', $sel) }}" class="w-full justify-center" size="sm">Download PDF</x-eo.button>

                        @if ($selState === 'draft')
                            <x-eo.button wire:click="send({{ $sel->id }})" class="w-full justify-center" size="sm">Send offer</x-eo.button>
                            <button type="button" wire:click="destroyDraft({{ $sel->id }})" class="eo-btn-ghost eo-btn-sm w-full justify-center text-eo-risk-ink">Delete draft</button>
                        @elseif ($selState === 'expired')
                            <x-eo.button wire:click="extend({{ $sel->id }})" class="w-full justify-center" size="sm">Extend 30 days</x-eo.button>
                            <button type="button" wire:click="decline({{ $sel->id }})" class="eo-btn-ghost eo-btn-sm w-full justify-center">Close as lost</button>
                        @elseif ($selState === 'sent')
                            <x-eo.button wire:click="accept({{ $sel->id }})" class="w-full justify-center" size="sm">Mark accepted</x-eo.button>
                            <input type="text" wire:model="reason.{{ $sel->id }}" placeholder="Decline reason…" class="eo-input text-xs">
                            <button type="button" wire:click="decline({{ $sel->id }})" class="eo-btn-ghost eo-btn-sm w-full justify-center text-eo-risk-ink">Mark lost</button>
                        @elseif ($selState === 'accepted' && $sel->event)
                            <x-eo.button href="{{ route('events.hub', $sel->event) }}" class="w-full justify-center" size="sm">Open event →</x-eo.button>
                        @endif
                    @elseif ($sel)
                        <p class="text-[12px] text-eo-muted">View only — you can open the document, not change its state.</p>
                        <x-eo.button variant="ghost" href="{{ route('proposals.edit', $sel) }}" class="w-full justify-center" size="sm">Open offer</x-eo.button>
                    @else
                        <p class="text-[12px] text-eo-muted">Select an offer from the queue.</p>
                    @endif
                </x-eo.action-panel>
            </div>
        </div>
    @endif
</div>
