@php $serif = 'font-family: \'Spectral\', Georgia, \'Times New Roman\', serif;'; @endphp
<div class="-mx-1">
    {{-- ══ Command bar ══ --}}
    <div class="sticky top-16 z-20 mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-navy-800/10 bg-navy-950 px-5 py-3.5 shadow-[0_18px_50px_-20px_rgba(11,31,58,0.6)]">
        <div class="pointer-events-none absolute inset-0 overflow-hidden rounded-2xl">
            <div class="absolute -right-10 -top-16 h-40 w-40 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.28),transparent_70%)]"></div>
        </div>
        <div class="relative flex items-center gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-gold-400 to-gold-600 text-navy-950 shadow">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z" opacity=".3"/><path d="M7 8h10M7 12h10M7 16h6"/></svg>
            </span>
            <div>
                <p class="text-eyebrow font-bold uppercase tracking-[0.32em] text-gold-400">Event Dossier</p>
                <p class="text-sm font-semibold text-white">Event Brief</p>
            </div>
            <select wire:change="switchTemplate($event.target.value)"
                    wire:confirm="Switch template? This replaces the section content with that template's content set."
                    class="ml-3 h-8 rounded-xl border border-white/15 bg-white/5 px-2 text-micro font-semibold text-white focus:outline-none">
                @foreach ($templates as $tk => $tname)
                    <option class="text-navy-900" value="{{ $tk }}" @selected($template === $tk)>{{ $tname }}</option>
                @endforeach
            </select>
        </div>
        <div class="relative flex flex-wrap items-center gap-2">
            <span class="mr-1 flex items-center gap-1.5 text-eyebrow font-medium text-white/50">
                <span wire:loading.remove class="flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Saved</span>
                <span wire:loading class="flex items-center gap-1.5 text-gold-300"><span class="h-1.5 w-1.5 animate-pulse rounded-full bg-gold-400"></span> Saving…</span>
            </span>
            <div class="flex items-center gap-1.5 rounded-xl border border-white/15 bg-white/5 px-2.5 py-1.5">
                <span class="text-eyebrow font-bold uppercase tracking-wider text-white/40">Ver</span>
                <input type="text" wire:model.blur="version" class="w-9 border-0 bg-transparent p-0 text-xs font-semibold text-white focus:outline-none focus:ring-0">
            </div>
            <button type="button" wire:click="toggleApproved"
                    class="flex h-9 items-center gap-1.5 rounded-xl px-3 text-micro font-bold transition {{ $status === 'approved' ? 'bg-emerald-400/15 text-emerald-300 ring-1 ring-emerald-400/30' : 'bg-white/5 text-white/60 ring-1 ring-white/15 hover:text-white' }}">
                {{ $status === 'approved' ? '✓ Approved' : 'Approve' }}
            </button>
            <button type="button" wire:click="generatePlan"
                    wire:confirm="Generate from this brief? Budget categories, risks and sponsor packages will be created. Existing items are never overwritten."
                    @disabled($status !== 'approved')
                    title="{{ $status === 'approved' ? 'Generate plan, budget, risks, sponsors & approvals' : 'Approve the brief first' }}"
                    class="flex h-9 items-center gap-1.5 rounded-xl px-3 text-micro font-bold transition {{ $status === 'approved' ? 'bg-white/10 text-white ring-1 ring-white/20 hover:bg-white/15' : 'cursor-not-allowed bg-white/5 text-white/25 ring-1 ring-white/10' }}">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M13 2L4.5 12.5H11l-1 9.5 8.5-10.5H12l1-9.5z"/></svg> Generate plan
            </button>
            <button type="button" wire:click="resetToTemplate" wire:confirm="Reset all sections to the template? Your edits will be replaced." class="flex h-9 w-9 items-center justify-center rounded-xl text-white/40 transition hover:bg-white/5 hover:text-white/80" title="Reset to template">↺</button>
            <a href="{{ route('events.brief.pdf', $event) }}" target="_blank"
               class="flex h-9 items-center gap-1.5 rounded-xl bg-gradient-to-r from-gold-400 to-gold-600 px-4 text-micro font-bold text-navy-950 shadow transition hover:brightness-105">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14"/></svg> Export PDF
            </a>
        </div>
    </div>

    {{-- ══ Generation result ══ --}}
    @error('generate')
        <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-3 text-xs font-semibold text-amber-800">{{ $message }}</div>
    @enderror

    @if (! empty($generated))
        @php $total = array_sum($generated); @endphp
        <div class="mb-5 overflow-hidden rounded-2xl border border-emerald-200 bg-gradient-to-r from-emerald-50 to-white px-5 py-4">
            <div class="flex flex-wrap items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-500 text-sm text-white">✓</span>
                <div class="flex-1">
                    <p class="text-sm font-bold text-navy-900">
                        {{ $total > 0 ? 'Plan generated from the brief' : 'Everything is already in sync' }}
                    </p>
                    <p class="text-micro text-muted">
                        @if ($total > 0)
                            Created {{ $total }} {{ \Illuminate\Support\Str::plural('record', $total) }}. Nothing existing was overwritten.
                        @else
                            No new records — the plan already reflects this brief.
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    @foreach (['budget' => 'Budget', 'risks' => 'Risks', 'sponsors' => 'Sponsors'] as $k => $lbl)
                        <span class="rounded-full bg-white px-2.5 py-1 text-eyebrow font-bold text-navy-600 ring-1 ring-line">
                            {{ $lbl }} <span class="{{ ($generated[$k] ?? 0) > 0 ? 'text-emerald-600' : 'text-navy-300' }}">+{{ $generated[$k] ?? 0 }}</span>
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,30rem)_minmax(0,1fr)]">

        {{-- ══════════ LEFT · the sections, as collapsible modules ══════════ --}}
        @php $in = 'w-full rounded-xl border border-transparent bg-page/70 px-3 py-2 text-sm font-medium text-navy-900 placeholder:text-navy-300 transition focus:border-gold-400 focus:bg-white focus:outline-none'; @endphp
        <div class="space-y-3">

            {{-- Cover module --}}
            <section x-data="{ open: false }" class="overflow-hidden rounded-2xl border border-line bg-white">
                <button type="button" @click="open = !open" class="flex w-full items-center gap-3 px-5 py-3.5 text-left">
                    <span class="pf text-lg font-bold text-gold-700">00</span>
                    <span class="flex-1">
                        <span class="block text-sm font-bold text-navy-900">Cover</span>
                        <span class="block text-eyebrow text-muted">Subtitle, parties and how to use this document</span>
                    </span>
                    <span class="text-navy-300 transition" :class="open && 'rotate-180'">▾</span>
                </button>
                <div x-show="open" class="space-y-2.5 border-t border-line px-5 py-4">
                    <div><label class="field-label !mb-1 !text-eyebrow">Subtitle</label>
                        <input type="text" wire:model.live.debounce.500ms="data.meta.subtitle" class="{{ $in }}"></div>
                    <div class="grid gap-2.5 sm:grid-cols-2">
                        <div><label class="field-label !mb-1 !text-eyebrow">Prepared for</label>
                            <input type="text" wire:model.live.debounce.500ms="data.meta.prepared_for" class="{{ $in }}"></div>
                        <div><label class="field-label !mb-1 !text-eyebrow">Prepared by</label>
                            <input type="text" wire:model.live.debounce.500ms="data.meta.prepared_by" class="{{ $in }}"></div>
                    </div>
                    <div><label class="field-label !mb-1 !text-eyebrow">Confidentiality</label>
                        <input type="text" wire:model.live.debounce.500ms="data.meta.confidentiality" class="{{ $in }}"></div>
                    <div><label class="field-label !mb-1 !text-eyebrow">How to use this document</label>
                        <textarea rows="3" wire:model.live.debounce.500ms="data.meta.how_to" class="{{ $in }} !text-xs leading-relaxed"></textarea></div>
                </div>
            </section>

            @foreach ($sections as $key => [$num, $title, $type])
                @php
                    $summary = match ($type) {
                        'text' => trim((string) ($data[$key] ?? '')) !== '' ? 'Written' : 'Empty — write it',
                        'kv' => collect($data['event_info'] ?? [])->filter(fn ($v) => trim((string) $v) !== '')->count().' of '.count($infoFields).' fields',
                        default => count($data[$key] ?? []).' '.\Illuminate\Support\Str::plural('row', count($data[$key] ?? [])),
                    };
                @endphp
                <section x-data="{ open: false }" wire:key="mod-{{ $key }}" class="overflow-hidden rounded-2xl border border-line bg-white">
                    <button type="button" @click="open = !open" class="flex w-full items-center gap-3 px-5 py-3.5 text-left">
                        <span class="pf text-lg font-bold text-gold-700">{{ str_pad($num, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="flex-1">
                            <span class="block text-sm font-bold text-navy-900">{{ $title }}</span>
                            <span class="block text-eyebrow text-muted">{{ $summary }}</span>
                        </span>
                        <span class="text-navy-300 transition" :class="open && 'rotate-180'">▾</span>
                    </button>
                    <div x-show="open" class="border-t border-line px-5 py-4">
                        @include('livewire.hub.partials.brief-section', ['type' => $type, 'key' => $key])
                        @if (in_array($type, ['bullets', 'kpi', 'twocol', 'approval'], true))
                            <button type="button" wire:click="addRow('{{ $key }}')" class="mt-3 btn-ghost btn-xs">＋ Add row</button>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>

        {{-- ══════════ RIGHT · the living dossier ══════════ --}}
        <div class="xl:sticky xl:top-12">
            <div class="rounded-3xl bg-navy-900/[0.05] p-3 ring-1 ring-line sm:p-5">
                <div class="mb-2 flex items-center justify-between px-1">
                    <span class="flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.16em] text-navy-500">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span> Live preview
                    </span>
                    <span class="text-eyebrow text-muted">Exactly what exports</span>
                </div>
                <div class="max-h-[calc(100vh-220px)] overflow-y-auto rounded-lg">
                    @include('event-brief.paper', [
                        'event' => $event, 'data' => $data, 'version' => $version, 'status' => $status,
                        'sections' => $sections, 'infoFields' => $infoFields, 'twocolHeads' => $twocolHeads,
                        'forPdf' => false,
                    ])
                </div>
            </div>
        </div>
    </div>
</div>
