@php
    use App\Models\EventContract;

    // The words come from Workflow::rows('contract_status') — the list Settings
    // renames — so a status renamed there is renamed here too. Only the
    // treatment is local: this screen's chips are its own.
    $statusClass = [
        'draft' => 'bg-navy-50 text-navy-500',
        'sent' => 'bg-gold-100 text-gold-800',
        'partially_signed' => 'bg-gold-100 text-gold-800',
        'signed' => 'bg-emerald-50 text-emerald-700',
        'void' => 'bg-navy-50 text-navy-400 line-through',
    ];
    $statusMeta = collect(\App\Models\EventContract::STATUSES)
        ->mapWithKeys(fn (string $k) => [$k => [
            \App\Support\Workflow::label('contract_status', $k),
            $statusClass[$k] ?? 'bg-navy-50 text-navy-500',
        ]])->all();
@endphp

<div class="space-y-4">

    {{-- ══ the bar ══
         No heading here: the layout already prints "Contracts" and its
         subtitle above this, and a page that names itself twice reads as a
         page that lost track of itself. --}}
    <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
        <div class="ms-auto flex flex-wrap items-center gap-2">
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-navy-300" />
                <input type="search" wire:model.live.debounce.300ms="q" placeholder="Reference, event, counterparty…"
                       class="input h-10 w-56 !rounded-2xl !py-0 !ps-9 text-xs xl:w-72">
            </div>

            <details class="relative" data-menu>
                <summary class="flex h-10 cursor-pointer list-none items-center gap-1.5 rounded-2xl border border-line bg-white px-3.5 text-[12px] font-semibold text-navy-700 shadow-sm transition hover:border-indigo-200 [&::-webkit-details-marker]:hidden">
                    <x-icon name="list" class="h-3.5 w-3.5 text-navy-400" />
                    {{ $type === 'all' ? 'All types' : EventContract::TYPES[$type]['label'] }}
                    @if ($type !== 'all')<span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>@endif
                </summary>
                <div class="absolute end-0 z-30 mt-2 w-56 overflow-hidden rounded-2xl border border-line bg-white p-1.5 shadow-xl">
                    <button type="button" wire:click="setType('all')"
                            @class(['flex w-full items-center rounded-xl px-3 py-2 text-start text-[12px] font-semibold transition',
                                'bg-navy-950 text-white' => $type === 'all', 'text-navy-600 hover:bg-page' => $type !== 'all'])>All types</button>
                    @foreach (EventContract::TYPES as $key => $meta)
                        <button type="button" wire:click="setType('{{ $key }}')"
                                @class(['flex w-full items-center rounded-xl px-3 py-2 text-start text-[12px] font-semibold transition',
                                    'bg-navy-950 text-white' => $type === $key, 'text-navy-600 hover:bg-page' => $type !== $key])>{{ $meta['label'] }}</button>
                    @endforeach
                </div>
            </details>
        </div>
    </div>

    <x-figure-strip :figures="$figures" dense />

    {{-- ══ the switch and the status filter ══ --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="inline-flex items-center gap-1 rounded-2xl border border-line bg-white/80 p-1 shadow-sm backdrop-blur">
            @foreach (['register' => ['Register', 'list'], 'pipeline' => ['Pipeline', 'columns']] as $key => [$label, $icon])
                <button type="button" wire:click="setView('{{ $key }}')"
                        @class(['flex items-center gap-2 rounded-xl px-3.5 py-2 text-[12.5px] font-bold transition',
                            'bg-navy-950 text-white shadow-[0_8px_20px_-12px_rgba(11,31,58,0.9)]' => $view === $key,
                            'text-navy-500 hover:bg-page hover:text-navy-900' => $view !== $key])>
                    <x-icon :name="$icon" class="h-3.5 w-3.5 {{ $view === $key ? 'text-gold-400' : '' }}" />{{ $label }}
                </button>
            @endforeach
        </div>

        <p class="text-[11.5px] text-muted">{{ $docs->count() }} {{ str('document')->plural($docs->count()) }} in view</p>

        <div class="ms-auto flex flex-wrap items-center gap-1">
            @foreach (['all' => 'All'] + collect(EventContract::STATUSES)->mapWithKeys(fn ($s) => [$s => $statusMeta[$s][0]])->all() as $key => $label)
                <button type="button" wire:click="setStatus('{{ $key }}')"
                        @class(['rounded-full px-2.5 py-1 text-[11px] font-bold transition',
                            'bg-navy-950 text-white' => $status === $key,
                            'text-navy-500 hover:bg-white hover:text-navy-900' => $status !== $key])>{{ $label }}</button>
            @endforeach
        </div>
    </div>

    @if ($docs->isEmpty())
        <x-empty icon="document" title="No document matches"
                 hint="Clear the filters, or open an event and draft one from its Contract tab." />

    {{-- ══════════ REGISTER ══════════
         A table, because across events the questions are comparative: which is
         oldest, which is worth most, which is due first. The Deck inside an
         event answers "what does this one look like"; that is a different
         question and it already has a different answer. ══════════ --}}
    @elseif ($view === 'register')
        <div class="overflow-hidden rounded-2xl border border-line bg-white shadow-sm">
            <div class="overflow-x-auto">
                <div class="min-w-[940px]">
                    @php $cols = 'grid-cols-[132px_1fr_180px_110px_120px_112px_104px]'; @endphp

                    <div class="grid {{ $cols }} gap-3 border-b border-line bg-navy-50/50 px-4 py-2 text-eyebrow font-bold uppercase tracking-wide text-navy-400">
                        @foreach ([['reference', 'Reference'], [null, 'Document'], ['event', 'Event'], [null, 'Status'], ['value', 'Value'], ['due', 'Next due'], [null, 'Signatures']] as [$key, $label])
                            @if ($key)
                                <button type="button" wire:click="sortBy('{{ $key }}')"
                                        class="flex items-center gap-1 text-start transition hover:text-navy-700">
                                    {{ $label }}
                                    @if ($sort === $key)<span class="text-gold-600">▾</span>@endif
                                </button>
                            @else
                                <span>{{ $label }}</span>
                            @endif
                        @endforeach
                    </div>

                    @foreach ($docs as $doc)
                        @php
                            [$label, $chip] = $statusMeta[$doc->status] ?? ['—', 'bg-navy-50 text-navy-500'];
                            $due = $nextDue($doc);
                            $signed = $doc->signedCount();
                            $total = $doc->signatoryCount();
                        @endphp

                        <a href="{{ route('events.hub', [$doc->event, 'tab' => 'contract', 'document' => $doc->id]) }}"
                           wire:key="doc-{{ $doc->id }}"
                           class="grid {{ $cols }} items-center gap-3 border-b border-line/60 px-4 py-2.5 transition last:border-0 hover:bg-navy-50/40">

                            <span class="truncate font-mono text-[11px] font-semibold text-navy-500">{{ $doc->reference ?: '—' }}</span>

                            <span class="min-w-0">
                                <span class="block truncate text-[13px] font-bold text-navy-900">{{ $doc->displayTitle() }}</span>
                                <span class="block truncate text-[11px] text-muted">{{ EventContract::TYPES[$doc->type]['label'] ?? $doc->type }}</span>
                            </span>

                            <span class="min-w-0">
                                <span class="block truncate text-[12px] font-semibold text-navy-700">{{ $doc->event?->name ?? '—' }}</span>
                                <span class="block truncate text-[10.5px] text-muted">{{ $doc->event?->client?->name ?? 'No client' }}</span>
                            </span>

                            <span><span class="rounded-full px-2 py-0.5 text-[10.5px] font-bold {{ $chip }}">{{ $label }}</span></span>

                            <span class="pf text-[13px] font-black tabular-nums text-navy-900">
                                {{ $doc->valueCents() ? 'JD'.number_format($doc->valueCents() / 100) : '—' }}
                            </span>

                            <span @class(['text-[11.5px] font-semibold tabular-nums',
                                'text-red-600' => $due?->isPast(), 'text-navy-600' => $due && ! $due->isPast(),
                                'text-navy-300' => ! $due])>{{ $due?->format('j M Y') ?? '—' }}</span>

                            {{-- Signatures as a meter, not a fraction: "1 / 3"
                                 is a number you have to do arithmetic on. --}}
                            <span class="flex items-center gap-2">
                                @if ($total)
                                    <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-navy-50">
                                        <span class="block h-full rounded-full {{ $signed === $total ? 'bg-track' : 'bg-gold-400' }}"
                                              style="width: {{ round($signed / $total * 100) }}%"></span>
                                    </span>
                                    <span class="shrink-0 text-[10.5px] font-bold tabular-nums text-navy-400">{{ $signed }}/{{ $total }}</span>
                                @else
                                    <span class="text-[10.5px] italic text-navy-300">None yet</span>
                                @endif
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

    {{-- ══════════ PIPELINE ══════════ --}}
    @else
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-{{ min(4, max(2, $lanes->count())) }}">
            @foreach ($lanes as $lane)
                <div class="flex min-h-[140px] flex-col rounded-2xl border border-line bg-white p-3 shadow-sm">
                    <div class="mb-2 flex items-center gap-2 px-1">
                        <span @class(['h-2 w-2 rounded-full',
                            'bg-navy-300' => $lane['key'] === 'draft', 'bg-gold-400' => $lane['key'] === 'sent',
                            'bg-track' => $lane['key'] === 'signed', 'bg-navy-200' => $lane['key'] === 'void'])></span>
                        <span class="text-eyebrow font-bold uppercase tracking-[0.16em] text-navy-500">{{ $lane['label'] }}</span>
                        <span class="ms-auto text-[11px] font-black tabular-nums text-navy-300">{{ $lane['docs']->count() }}</span>
                    </div>

                    <div class="space-y-2">
                        @forelse ($lane['docs'] as $doc)
                            @php $due = $nextDue($doc); @endphp
                            <a href="{{ route('events.hub', [$doc->event, 'tab' => 'contract', 'document' => $doc->id]) }}"
                               wire:key="lane-{{ $doc->id }}"
                               class="block rounded-xl border border-line bg-page/40 p-2.5 transition hover:border-indigo-200 hover:bg-white">
                                <p class="truncate text-[12.5px] font-bold text-navy-900">{{ $doc->displayTitle() }}</p>
                                <p class="mt-0.5 truncate text-[11px] text-muted">{{ $doc->event?->name }}</p>
                                <div class="mt-1.5 flex items-center gap-2">
                                    <span class="pf text-[12px] font-black tabular-nums text-navy-800">
                                        {{ $doc->valueCents() ? 'JD'.number_format($doc->valueCents() / 100) : '—' }}
                                    </span>
                                    @if ($due)
                                        <span class="ms-auto text-[10.5px] font-semibold {{ $due->isPast() ? 'text-red-600' : 'text-navy-400' }}">
                                            {{ $due->format('j M') }}
                                        </span>
                                    @endif
                                </div>
                            </a>
                        @empty
                            <p class="px-1 py-3 text-[11.5px] italic text-navy-300">Nothing here.</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
