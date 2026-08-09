@php
    use App\Models\EventContract;

    $statusMeta = EventContract::statusMeta();
    foreach ($statusMeta as $k => [$label, $class]) {
        $statusMeta[$k] = [$label, $k === 'void' ? "{$class} line-through" : $class];
    }
    $sel = $selected;
    $due = $sel ? $nextDue($sel) : null;
    $signed = $sel ? $sel->signedCount() : 0;
    $totalSig = $sel ? $sel->signatoryCount() : 0;
@endphp

<div class="eo-event-atmosphere space-y-5 rounded-[24px]">

    <x-eo.page-header
        eyebrow="Commercial Command"
        title="Contracts"
        subtitle="Queue → Contract → Signature / Payment Panel. What is drafted, out for pen, and signed."
    >
        <x-slot:actions>
            <span class="eo-journey-chip">Agreements</span>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-eo-muted" />
                <input type="search" wire:model.live.debounce.300ms="q" placeholder="Reference, event, party…"
                       class="eo-input h-10 w-52 !py-0 !ps-9 text-xs xl:w-64">
            </div>
            <details class="relative" data-menu>
                <summary class="eo-btn-ghost eo-btn-sm cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                    {{ $type === 'all' ? 'All types' : EventContract::TYPES[$type]['label'] }}
                </summary>
                <div class="absolute end-0 z-30 mt-2 w-56 overflow-hidden rounded-2xl border border-eo-line bg-white p-1.5 shadow-eo-float">
                    <button type="button" wire:click="setType('all')" class="flex w-full rounded-xl px-3 py-2 text-start text-[12px] font-semibold hover:bg-eo-workspace">All types</button>
                    @foreach (EventContract::TYPES as $key => $meta)
                        <button type="button" wire:click="setType('{{ $key }}')" class="flex w-full rounded-xl px-3 py-2 text-start text-[12px] font-semibold hover:bg-eo-workspace">{{ $meta['label'] }}</button>
                    @endforeach
                </div>
            </details>
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

    <div class="flex flex-wrap items-center gap-3">
        <div class="inline-flex items-center gap-1 rounded-2xl border border-eo-line bg-white p-1 shadow-eo">
            @foreach (['register' => 'Register', 'pipeline' => 'Pipeline'] as $key => $label)
                <button type="button" wire:click="setView('{{ $key }}')"
                        @class([
                            'rounded-xl px-3.5 py-2 text-[12px] font-bold transition',
                            'bg-gradient-to-b from-eo-teal-lit to-eo-teal-deep text-white shadow-eo-teal' => $view === $key,
                            'text-eo-muted hover:bg-eo-workspace' => $view !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>
        <div class="flex flex-wrap items-center gap-1">
            @foreach (['all' => 'All'] + collect(EventContract::STATUSES)->mapWithKeys(fn ($s) => [$s => $statusMeta[$s][0]])->all() as $key => $label)
                <button type="button" wire:click="setStatus('{{ $key }}')"
                        @class(['eo-btn-ghost eo-btn-sm', '!bg-eo-navy !text-white' => $status === $key])>{{ $label }}</button>
            @endforeach
        </div>
        <p class="ms-auto text-[12px] text-eo-muted">{{ $docs->count() }} in view</p>
    </div>

    @if ($docs->isEmpty())
        <x-eo.empty-state title="No document matches" hint="Clear filters, or draft from an event Contract tab." icon="document" />
    @elseif ($view === 'pipeline')
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($lanes as $lane)
                <x-eo.soft-card>
                    <p class="eo-label mb-3">{{ $lane['label'] }} · {{ $lane['docs']->count() }}</p>
                    <div class="space-y-2">
                        @forelse ($lane['docs'] as $doc)
                            <button type="button" wire:click="select({{ $doc->id }})" class="w-full rounded-xl border border-eo-line bg-white px-3 py-2.5 text-start hover:border-eo-teal/30">
                                <p class="truncate text-[12.5px] font-bold text-eo-text">{{ $doc->displayTitle() }}</p>
                                <p class="truncate text-[11px] text-eo-muted">{{ $doc->event?->name }}</p>
                            </button>
                        @empty
                            <p class="text-[11px] text-eo-muted">Empty lane</p>
                        @endforelse
                    </div>
                </x-eo.soft-card>
            @endforeach
        </div>
        @if ($sel)
            <div class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-8">
                    <x-eo.detail-panel title="{{ $sel->displayTitle() }}" subtitle="{{ $sel->reference ?: 'No reference' }} · {{ $sel->event?->name }}">
                        <x-eo.commercial-card
                            title="Contract value"
                            :value="$sel->valueCents() ? 'JD'.number_format($sel->valueCents() / 100) : '—'"
                            :meta="$signed.'/'.$totalSig.' signatures · next due '.($due?->format('j M Y') ?? '—')"
                        />
                    </x-eo.detail-panel>
                </div>
                <div class="xl:col-span-4">
                    <x-eo.action-panel title="Signature / Payment">
                        <x-eo.button href="{{ route('events.hub', [$sel->event, 'tab' => 'contract', 'document' => $sel->id]) }}" class="w-full justify-center" size="sm">
                            Open in Event Hub →
                        </x-eo.button>
                    </x-eo.action-panel>
                </div>
            </div>
        @endif
    @else
        <div class="grid gap-4 xl:grid-cols-12">
            <div class="xl:col-span-4">
                <x-eo.queue-list title="Contract queue">
                    @foreach ($docs as $doc)
                        @php
                            $active = $sel?->id === $doc->id;
                            [$label] = $statusMeta[$doc->status] ?? ['—', ''];
                        @endphp
                        <button type="button" wire:click="select({{ $doc->id }})" wire:key="doc-{{ $doc->id }}" class="w-full text-start">
                            @if ($active)
                                <x-eo.selected-dark-card>
                                    <p class="font-mono text-[11px] text-eo-teal-lit">{{ $doc->reference ?: '—' }}</p>
                                    <p class="mt-1 truncate text-[14px] font-semibold text-white">{{ $doc->displayTitle() }}</p>
                                    <p class="mt-1 truncate text-[12px] text-white/50">{{ $doc->event?->name }}</p>
                                    <div class="mt-3 flex items-center justify-between gap-2">
                                        <span class="text-[15px] font-bold tabular-nums text-white">{{ $doc->valueCents() ? 'JD'.number_format($doc->valueCents() / 100) : '—' }}</span>
                                        <x-eo.status-pill tone="live">{{ $label }}</x-eo.status-pill>
                                    </div>
                                </x-eo.selected-dark-card>
                            @else
                                <div class="rounded-2xl border border-eo-line bg-white px-4 py-3 transition hover:border-eo-teal/30 hover:shadow-eo">
                                    <p class="font-mono text-[11px] text-eo-muted">{{ $doc->reference ?: '—' }}</p>
                                    <p class="mt-0.5 truncate text-[13px] font-bold text-eo-text">{{ $doc->displayTitle() }}</p>
                                    <div class="mt-2 flex items-center justify-between gap-2">
                                        <span class="truncate text-[11px] text-eo-muted">{{ $doc->event?->name }}</span>
                                        <span class="text-[12px] font-bold tabular-nums">{{ $doc->valueCents() ? number_format($doc->valueCents() / 100) : '—' }}</span>
                                    </div>
                                </div>
                            @endif
                        </button>
                    @endforeach
                </x-eo.queue-list>
            </div>

            <div class="xl:col-span-5">
                @if ($sel)
                    <x-eo.detail-panel title="{{ $sel->displayTitle() }}" subtitle="{{ EventContract::TYPES[$sel->type]['label'] ?? $sel->type }} · {{ $sel->event?->client?->name ?? 'No client' }}">
                        <x-slot:header>
                            @php [$label] = $statusMeta[$sel->status] ?? ['—', '']; @endphp
                            <x-eo.status-pill tone="{{ $sel->status === 'signed' ? 'ok' : ($sel->status === 'void' ? 'risk' : 'live') }}">{{ $label }}</x-eo.status-pill>
                        </x-slot:header>

                        <x-eo.commercial-card
                            title="Agreement value"
                            :subtitle="$sel->party?->name ?? 'Counterparty'"
                            :value="$sel->valueCents() ? 'JD'.number_format($sel->valueCents() / 100) : '—'"
                            :meta="'Next due '.($due?->format('j M Y') ?? '—')"
                        />

                        <div class="mt-4">
                            <p class="eo-label mb-2">Signatures</p>
                            @if ($totalSig)
                                <div class="h-2 overflow-hidden rounded-full bg-eo-bg">
                                    <div class="h-full rounded-full bg-gradient-to-r from-eo-teal-deep to-eo-teal-lit" style="width: {{ round($signed / max($totalSig, 1) * 100) }}%"></div>
                                </div>
                                <p class="mt-1.5 text-[12px] font-semibold text-eo-muted">{{ $signed }} of {{ $totalSig }} signed</p>
                            @else
                                <p class="text-[12px] text-eo-muted">No signatories yet</p>
                            @endif
                        </div>
                    </x-eo.detail-panel>
                @endif
            </div>

            <div class="xl:col-span-3">
                <x-eo.action-panel title="Signature / Payment">
                    @if ($sel && $sel->event)
                        <x-eo.button href="{{ route('events.hub', [$sel->event, 'tab' => 'contract', 'document' => $sel->id]) }}" class="w-full justify-center" size="sm">
                            Open in Event Hub →
                        </x-eo.button>
                        <x-eo.button variant="ghost" href="{{ route('events.hub', [$sel->event, 'tab' => 'budget']) }}" class="w-full justify-center" size="sm">
                            Commercial desk
                        </x-eo.button>
                        <x-eo.button variant="ghost" href="{{ route('payments.index', ['q' => $sel->reference ?: $sel->event->name]) }}" class="w-full justify-center" size="sm">
                            Payment schedule
                        </x-eo.button>
                    @else
                        <p class="text-[12px] text-eo-muted">Select a contract from the queue.</p>
                    @endif
                </x-eo.action-panel>
            </div>
        </div>
    @endif
</div>
