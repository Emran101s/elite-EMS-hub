<div class="-mx-1">
    {{-- ══ Command bar ══ --}}
    <div class="sticky top-16 z-20 mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-eo-line/10 bg-eo-navy-deep px-5 py-3.5 shadow-[0_18px_50px_-20px_rgba(11,31,58,0.6)]">
        <div class="pointer-events-none absolute inset-0 overflow-hidden rounded-2xl">
            <div class="absolute -right-10 -top-16 h-40 w-40 rounded-full bg-[radial-gradient(circle,rgba(30,172,172,0.22),transparent_70%)]"></div>
        </div>
        <div class="relative flex items-center gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-eo-teal text-white shadow">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z" opacity=".3"/><path d="M7 8h10M7 12h10M7 16h6"/></svg>
            </span>
            <div>
                <p class="text-eyebrow font-bold uppercase tracking-[0.32em] text-eo-teal-lit">Event Dossier</p>
                <p class="text-sm font-semibold text-white">Event Brief</p>
            </div>
            <select
                    x-data="{ current: @js($template) }"
                    x-on:change="
                        const next = $event.target.value;
                        if (next === current) return;
                        $event.target.value = current;
                        window.ebhConfirm({
                            title: 'Switch template?',
                            body: 'This replaces the section content with that template\'s content set.',
                            confirmLabel: 'Switch',
                            tone: 'warn',
                            run: () => { current = next; $wire.switchTemplate(next); },
                        });
                    "
                    class="ml-3 h-8 rounded-xl border border-white/15 bg-white/5 px-2 text-micro font-semibold text-white focus:outline-none">
                @foreach ($templates as $tk => $tname)
                    <option class="text-eo-text" value="{{ $tk }}" @selected($template === $tk)>{{ $tname }}</option>
                @endforeach
            </select>
        </div>
        <div class="relative flex flex-wrap items-center gap-2">
            <span class="mr-1 flex items-center gap-1.5 text-eyebrow font-medium text-white/50">
                <span wire:loading.remove class="flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Saved</span>
                <span wire:loading class="flex items-center gap-1.5 text-eo-teal-lit"><span class="h-1.5 w-1.5 animate-pulse rounded-full bg-eo-teal"></span> Saving…</span>
            </span>
            <div class="flex items-center gap-1.5 rounded-xl border border-white/15 bg-white/5 px-2.5 py-1.5">
                <span class="text-eyebrow font-bold uppercase tracking-wider text-white/40">Ver</span>
                <input type="text" wire:model.blur="version" class="w-9 border-0 bg-transparent p-0 text-xs font-semibold text-white focus:outline-none focus:ring-0">
            </div>
            <button type="button" wire:click="toggleApproved"
                    class="flex h-9 items-center gap-1.5 rounded-xl px-3 text-micro font-bold transition {{ $status === 'approved' ? 'bg-emerald-400/15 text-emerald-300 ring-1 ring-emerald-400/30' : 'bg-white/5 text-white/60 ring-1 ring-white/15 hover:text-white' }}">
                {{ $status === 'approved' ? '✓ Approved' : 'Approve' }}
            </button>
            <x-confirm title="Generate from this brief?"
                    body="Budget categories, risks and sponsor packages will be created. Existing items are never overwritten."
                    confirm="Generate" tone="neutral"
                    run="$wire.generatePlan()"
                    :disabled="$status !== 'approved'"
                    class="flex h-9 items-center gap-1.5 rounded-xl px-3 text-micro font-bold transition {{ $status === 'approved' ? 'bg-white/10 text-white ring-1 ring-white/20 hover:bg-white/15' : 'cursor-not-allowed bg-white/5 text-white/25 ring-1 ring-white/10' }}">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M13 2L4.5 12.5H11l-1 9.5 8.5-10.5H12l1-9.5z"/></svg> Generate plan
            </x-confirm>
            <x-confirm title="Reset all sections to the template?"
                       body="Your edits will be replaced."
                       confirm="Reset" tone="warn"
                       run="$wire.resetToTemplate()"
                       class="flex h-9 w-9 items-center justify-center rounded-xl text-white/40 transition hover:bg-white/5 hover:text-white/80">↺</x-confirm>
            <a href="{{ route('events.brief.pdf', $event) }}" target="_blank"
               class="flex h-9 items-center gap-1.5 rounded-xl bg-eo-teal px-4 text-micro font-bold text-white shadow transition hover:bg-eo-teal-deep">
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
                    <p class="text-sm font-bold text-eo-text">
                        {{ $total > 0 ? 'Plan generated from the brief' : 'Everything is already in sync' }}
                    </p>
                    <p class="text-micro text-eo-muted">
                        @if ($total > 0)
                            Created {{ $total }} {{ \Illuminate\Support\Str::plural('record', $total) }}. Nothing existing was overwritten.
                        @else
                            No new records — the plan already reflects this brief.
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    @foreach (['budget' => 'Budget', 'risks' => 'Risks', 'sponsors' => 'Sponsors'] as $k => $lbl)
                        <span class="rounded-full bg-white px-2.5 py-1 text-eyebrow font-bold text-eo-muted ring-1 ring-line">
                            {{ $lbl }} <span class="{{ ($generated[$k] ?? 0) > 0 ? 'text-emerald-600' : 'text-eo-muted' }}">+{{ $generated[$k] ?? 0 }}</span>
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,30rem)_minmax(0,1fr)]">

        {{-- ══════════ LEFT · the sections, as collapsible modules ══════════

             One grid child, not two. The column is defined by the grid, so a
             second element out here takes the preview's column and pushes it
             to the next row — which is exactly what a stray block did. --}}
        @php $in = 'w-full rounded-xl border border-transparent bg-eo-workspace/70 px-3 py-2 text-sm font-medium text-eo-text placeholder:text-eo-muted transition focus:border-eo-teal focus:bg-white focus:outline-none'; @endphp
        <div class="min-w-0">
        <x-accordion>

            {{-- Cover module --}}
            <x-accordion-section id="cover" num="00" title="Cover"
                                 summary="Subtitle, parties and how to use this document">
                <div class="space-y-2.5">
                    <div><label class="eo-label !mb-1 !text-eyebrow">Subtitle</label>
                        <input type="text" wire:model.live.debounce.500ms="data.meta.subtitle" class="{{ $in }}"></div>
                    <div class="grid gap-2.5 sm:grid-cols-2">
                        <div><label class="eo-label !mb-1 !text-eyebrow">Prepared for</label>
                            <input type="text" wire:model.live.debounce.500ms="data.meta.prepared_for" class="{{ $in }}"></div>
                        <div><label class="eo-label !mb-1 !text-eyebrow">Prepared by</label>
                            <input type="text" wire:model.live.debounce.500ms="data.meta.prepared_by" class="{{ $in }}"></div>
                    </div>
                    <div><label class="eo-label !mb-1 !text-eyebrow">Confidentiality</label>
                        <input type="text" wire:model.live.debounce.500ms="data.meta.confidentiality" class="{{ $in }}"></div>
                    <div><label class="eo-label !mb-1 !text-eyebrow">How to use this document</label>
                        <textarea rows="3" wire:model.live.debounce.500ms="data.meta.how_to" class="{{ $in }} !text-xs leading-relaxed"></textarea></div>
                </div>
            </x-accordion-section>

            @foreach ($sections as $key => [$num, $title, $type])
                @php
                    $summary = match ($type) {
                        'text' => trim((string) ($data[$key] ?? '')) !== '' ? 'Written' : 'Empty — write it',
                        'kv' => collect($data['event_info'] ?? [])->filter(fn ($v) => trim((string) $v) !== '')->count().' of '.count($infoFields).' fields',
                        default => count($data[$key] ?? []).' '.\Illuminate\Support\Str::plural('row', count($data[$key] ?? [])),
                    };
                @endphp
                <x-accordion-section :id="$key" wire:key="mod-{{ $key }}"
                                     :num="str_pad($num, 2, '0', STR_PAD_LEFT)"
                                     :title="$title" :summary="$summary">
                    @include('livewire.hub.partials.brief-section', ['type' => $type, 'key' => $key])
                    @if (in_array($type, ['bullets', 'kpi', 'twocol', 'approval'], true))
                        <button type="button" wire:click="addRow('{{ $key }}')" class="mt-3 eo-btn-ghost btn-xs">＋ Add row</button>
                    @endif

                    {{-- Typing saves on its own; this is the button that says so.
                         Autosave that leaves no mark is autosave nobody trusts. --}}
                    <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-eo-line pt-3">
                        <button type="button" wire:click="saveSection('{{ $key }}')"
                                class="rounded-xl bg-eo-navy-deep px-3.5 py-2 text-[11.5px] font-bold text-white transition hover:bg-eo-navy-mid">
                            Save this section
                        </button>

                        @if ($savedSection === $key)
                            <span class="text-[11.5px] font-bold text-emerald-700">Saved ✓</span>
                        @else
                            <span class="text-[11px] text-eo-muted">Changes save as you type.</span>
                        @endif

                        <x-confirm title="Take “{{ $title }}” off this brief?"
                                body="What you have written in it is kept, and you can put the section back."
                                confirm="Remove" tone="warn"
                                run="$wire.removeSection('{{ $key }}')"
                                class="ms-auto text-[11.5px] font-semibold text-eo-muted transition hover:text-red-600">
                            Remove this section
                        </x-confirm>
                    </div>
                </x-accordion-section>
            @endforeach
        </x-accordion>

        {{-- One button, and the removed sections behind it.

             A standing panel listing what is NOT on the document takes as much
             room as a section that is, on a page whose whole job is the twelve
             that are. It only appears once something has been taken off, and it
             closes again the moment it is used. --}}
        @if ($hiddenSections)
            <details class="relative mt-2.5" data-menu>
                <summary class="inline-flex cursor-pointer list-none items-center gap-1.5 rounded-xl border border-dashed border-eo-line bg-white px-3.5 py-2 text-[11.5px] font-semibold text-eo-muted transition hover:border-eo-teal hover:text-eo-text [&::-webkit-details-marker]:hidden">
                    ＋ Add a section
                    <span class="rounded-full bg-eo-bg px-1.5 text-[10px] font-black tabular-nums text-eo-muted">{{ count($hiddenSections) }}</span>
                </summary>

                <div class="absolute z-30 mt-1.5 w-72 overflow-hidden rounded-2xl border border-eo-line bg-white p-1.5 shadow-xl">
                    <p class="px-2.5 pb-1 pt-1.5 text-eyebrow font-bold uppercase tracking-[0.14em] text-eo-muted">Not on this brief</p>

                    @foreach ($hiddenSections as $key => [$num, $title, $type])
                        <button type="button" wire:click="restoreSection('{{ $key }}')" wire:key="off-{{ $key }}"
                                class="flex w-full items-center gap-2 rounded-xl px-2.5 py-2 text-start text-[12px] font-semibold text-eo-text transition hover:bg-eo-workspace">
                            <span class="w-6 shrink-0 text-[13px] font-bold text-eo-muted">{{ str_pad($num, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="min-w-0 flex-1 truncate">{{ $title }}</span>
                            <span class="shrink-0 text-eo-muted">＋</span>
                        </button>
                    @endforeach

                    <p class="px-2.5 pb-1 pt-1.5 text-[10.5px] leading-snug text-eo-muted">
                        What you wrote in these is still there — putting one back returns it.
                    </p>
                </div>
            </details>
        @endif
        </div>

        {{-- ══════════ RIGHT · the living dossier ══════════ --}}
        @php
            // Mirrors event-brief/paper.blade.php's own emptiness check, kept
            // local to the tab rather than passed into the shared paper
            // partial — the PDF export uses that partial standalone and
            // should never carry an editor-only "nothing written yet" hint.
            $meta = $data['meta'] ?? [];
            $hasAnyContent = collect($meta)->contains(fn ($v) => trim((string) $v) !== '')
                || collect($sections)->contains(function ($section, $key) use ($data) {
                    [, , $type] = $section;

                    return match ($type) {
                        'text' => trim((string) ($data[$key] ?? '')) !== '',
                        'kv' => collect($data['event_info'] ?? [])->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty(),
                        default => collect($data[$key] ?? [])->filter(fn ($row) => is_array($row)
                            ? collect($row)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty()
                            : trim((string) $row) !== '')->isNotEmpty(),
                    };
                });
        @endphp
        <div class="xl:sticky xl:top-12">
            <div class="rounded-3xl bg-eo-navy/[0.05] p-3 ring-1 ring-line sm:p-5">
                <div class="mb-2 flex items-center justify-between px-1">
                    <span class="flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.16em] text-eo-muted">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span> Live preview
                    </span>
                    <span class="text-eyebrow text-eo-muted">Exactly what exports</span>
                </div>
                @unless ($hasAnyContent)
                    <p class="mb-2 rounded-xl border border-dashed border-eo-line bg-white/60 px-3 py-2 text-eyebrow text-eo-muted">
                        Nothing written yet — fill in a section on the left and it appears here, exactly as it will export.
                    </p>
                @endunless
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
