@php
    use App\Models\EventContract;

    $statusMeta = EventContract::statusMeta();
    $sel = $selected;
    $due = $sel ? $nextDue($sel) : null;
    $signed = $sel ? $sel->signedCount() : 0;
    $totalSig = $sel ? $sel->signatoryCount() : 0;

    $toneMap = fn ($t) => match ($t) {
        'green' => 'ok', 'red' => 'risk', 'gold', 'amber' => 'warn', 'blue', 'violet' => 'live', default => null,
    };
@endphp

<div class="space-y-5">

    <x-cc.header eyebrow="Commercial Command" title="Contracts" subtitle="Queue → Contract → Signature / Payment Panel. What is drafted, out for pen, and signed.">
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-2.5 py-1 text-[10.5px] font-bold uppercase tracking-wide text-gold-700">
                <span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Agreements
            </span>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted" />
                <input type="search" wire:model.live.debounce.300ms="q" placeholder="Reference, event, party…"
                       class="h-10 w-52 rounded-full border border-line bg-white pl-9 pr-3 text-[12.5px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none xl:w-64">
            </div>
            <details class="relative" data-menu>
                <summary class="cursor-pointer list-none rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:border-navy-300 [&::-webkit-details-marker]:hidden">
                    {{ $type === 'all' ? 'All types' : EventContract::TYPES[$type]['label'] }}
                </summary>
                <div class="absolute end-0 z-30 mt-2 w-56 overflow-hidden rounded-lg border border-line bg-white p-1.5 shadow-overlay">
                    <button type="button" wire:click="setType('all')" class="flex w-full rounded-md px-3 py-2 text-start text-[12px] font-semibold text-ink hover:bg-page">All types</button>
                    @foreach (EventContract::TYPES as $key => $meta)
                        <button type="button" wire:click="setType('{{ $key }}')" class="flex w-full rounded-md px-3 py-2 text-start text-[12px] font-semibold text-ink hover:bg-page">{{ $meta['label'] }}</button>
                    @endforeach
                </div>
            </details>
        </x-slot:actions>
    </x-cc.header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($figures as $f)
            <x-cc.kpi-tile :label="$f['label']" :value="$f['value']" :hint="$f['note'] ?? null" :tone="$toneMap($f['tone'] ?? '')" />
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <div class="inline-flex items-center gap-1 rounded-full border border-line bg-white p-1">
            @foreach (['register' => 'Register', 'pipeline' => 'Pipeline'] as $key => $label)
                <button type="button" wire:click="setView('{{ $key }}')"
                        @class([
                            'rounded-full px-3.5 py-1.5 text-[12px] font-bold transition',
                            'bg-navy-900 text-white' => $view === $key,
                            'text-muted hover:bg-page' => $view !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>
        <div class="flex flex-wrap items-center gap-1.5">
            @foreach (['all' => 'All'] + collect(EventContract::STATUSES)->mapWithKeys(fn ($s) => [$s => $statusMeta[$s][0]])->all() as $key => $label)
                <button type="button" wire:click="setStatus('{{ $key }}')"
                        @class([
                            'rounded-full px-3 py-1.5 text-[12px] font-bold transition',
                            'bg-navy-900 text-white' => $status === $key,
                            'bg-white text-muted ring-1 ring-line hover:text-ink' => $status !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>
        <p class="ms-auto text-[12px] text-muted">{{ $docs->count() }} in view</p>
    </div>

    @if ($docs->isEmpty())
        <x-eo.empty-state title="No document matches" hint="Clear filters, or draft from an event Contract tab." icon="document" />
    @elseif ($view === 'pipeline')
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($lanes as $lane)
                <div class="rounded-lg border border-line bg-white p-4">
                    <p class="mb-3 text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">{{ $lane['label'] }} · {{ $lane['docs']->count() }}</p>
                    <div class="space-y-2">
                        @forelse ($lane['docs'] as $doc)
                            <button type="button" wire:click="select({{ $doc->id }})" class="w-full rounded-lg border border-line bg-white px-3 py-2.5 text-start transition hover:border-navy-300">
                                <p class="truncate text-[12.5px] font-bold text-ink">{{ $doc->displayTitle() }}</p>
                                <p class="truncate text-[11px] text-muted">{{ $doc->event?->name }}</p>
                            </button>
                        @empty
                            <p class="text-[11px] text-muted">Empty lane</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
        @if ($sel)
            <div class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-8">
                    <x-cc.briefing-panel :title="$sel->displayTitle()" :subtitle="($sel->reference ?: 'No reference').' · '.$sel->event?->name">
                        <x-billing.stat-card
                            eyebrow="Commercial"
                            title="Contract value"
                            :value="$sel->valueCents() ? 'JD'.number_format($sel->valueCents() / 100) : '—'"
                            :meta="$signed.'/'.$totalSig.' signatures · next due '.($due?->format('j M Y') ?? '—')"
                        />
                    </x-cc.briefing-panel>
                </div>
                <div class="xl:col-span-4">
                    <x-billing.action-panel title="Signature / Payment">
                        <a href="{{ route('events.hub', [$sel->event, 'tab' => 'contract', 'document' => $sel->id]) }}" class="flex w-full items-center justify-center rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">Open in Event Hub →</a>
                    </x-billing.action-panel>
                </div>
            </div>
        @endif
    @else
        <div class="grid gap-4 xl:grid-cols-12">
            <div class="xl:col-span-4">
                <x-billing.queue title="Contract queue">
                    @foreach ($docs as $doc)
                        @php
                            $active = $sel?->id === $doc->id;
                            [$label] = $statusMeta[$doc->status] ?? ['—', ''];
                        @endphp
                        <button type="button" wire:click="select({{ $doc->id }})" wire:key="doc-{{ $doc->id }}" class="w-full text-start">
                            <x-billing.queue-row
                                :active="$active"
                                :eyebrow="$doc->reference ?: '—'"
                                :title="$doc->displayTitle()"
                                :subtitle="$doc->event?->name"
                                :amount="$doc->valueCents() ? ($active ? 'JD' : '').number_format($doc->valueCents() / 100) : '—'"
                                :badge-label="$active ? $label : null"
                                badge-tone="live"
                            />
                        </button>
                    @endforeach
                </x-billing.queue>
            </div>

            <div class="xl:col-span-5">
                @if ($sel)
                    <x-cc.briefing-panel :title="$sel->displayTitle()" :subtitle="(EventContract::TYPES[$sel->type]['label'] ?? $sel->type).' · '.($sel->event?->client?->name ?? 'No client')">
                        <x-slot:header>
                            @php [$label] = $statusMeta[$sel->status] ?? ['—', '']; @endphp
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold {{ $sel->status === 'signed' ? 'bg-success-soft text-success-ink' : ($sel->status === 'void' ? 'bg-danger-soft text-danger-ink' : 'bg-info-soft text-info-ink') }}">{{ $label }}</span>
                        </x-slot:header>

                        <x-billing.stat-card
                            eyebrow="Commercial"
                            title="Agreement value"
                            :subtitle="$sel->party?->name ?? 'Counterparty'"
                            :value="$sel->valueCents() ? 'JD'.number_format($sel->valueCents() / 100) : '—'"
                            :meta="'Next due '.($due?->format('j M Y') ?? '—')"
                        />

                        <div class="mt-4">
                            <p class="mb-2 text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Signatures</p>
                            @if ($totalSig)
                                <div class="h-2 overflow-hidden rounded-full bg-page">
                                    <div class="h-full rounded-full bg-gold-500" style="width: {{ round($signed / max($totalSig, 1) * 100) }}%"></div>
                                </div>
                                <p class="mt-1.5 text-[12px] font-semibold text-muted">{{ $signed }} of {{ $totalSig }} signed</p>
                            @else
                                <p class="text-[12px] text-muted">No signatories yet</p>
                            @endif
                        </div>
                    </x-cc.briefing-panel>
                @endif
            </div>

            <div class="xl:col-span-3">
                <x-billing.action-panel title="Signature / Payment">
                    @if ($sel && $sel->event)
                        <a href="{{ route('events.hub', [$sel->event, 'tab' => 'contract', 'document' => $sel->id]) }}" class="flex w-full items-center justify-center rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">Open in Event Hub →</a>
                        <a href="{{ route('events.hub', [$sel->event, 'tab' => 'budget']) }}" class="flex w-full items-center justify-center rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:border-navy-300">Commercial desk</a>
                        <a href="{{ route('payments.index', ['q' => $sel->reference ?: $sel->event->name]) }}" class="flex w-full items-center justify-center rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:border-navy-300">Payment schedule</a>
                    @else
                        <p class="text-[12px] text-muted">Select a contract from the queue.</p>
                    @endif
                </x-billing.action-panel>
            </div>
        </div>
    @endif
</div>
