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

    <div class="flex gap-6">
        {{-- ══ Section rail ══ --}}
        <aside class="hidden w-48 shrink-0 lg:block">
            <nav class="sticky top-32 space-y-0.5">
                <p class="mb-2 px-2 text-eyebrow font-bold uppercase tracking-[0.2em] text-navy-300">Contents</p>
                @foreach ($sections as $key => [$num, $title, $type])
                    <a href="#sec-{{ $key }}" class="group flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-micro text-navy-500 transition hover:bg-gold-50/60 hover:text-navy-900">
                        <span class="w-4 text-right text-eyebrow font-bold text-gold-500/70">{{ $num }}</span>
                        <span class="truncate">{{ $title }}</span>
                    </a>
                @endforeach
            </nav>
        </aside>

        {{-- ══ Document canvas ══ --}}
        <main class="min-w-0 flex-1">
            {{-- A4 width (794px) so the editor and the PDF export stay pixel-matched --}}
            <div class="mx-auto w-full max-w-[794px] overflow-hidden rounded-[1.75rem] bg-white shadow-[0_40px_90px_-40px_rgba(11,31,58,0.4)] ring-1 ring-line/70">
                {{-- Dossier hero --}}
                <div class="relative overflow-hidden bg-gradient-to-br from-navy-900 via-navy-900 to-[#071528] px-8 pb-8 pt-10 text-white sm:px-12">
                    <div class="pointer-events-none absolute inset-0">
                        <div class="absolute -right-12 top-1/2 h-56 w-56 -translate-y-1/2 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.22),transparent_65%)]"></div>
                        <div class="absolute left-8 top-8 h-px w-16 bg-gold-400"></div>
                    </div>
                    <div class="relative">
                        <p class="pt-3 text-eyebrow font-bold uppercase tracking-[0.36em] text-gold-400">◆ Elite Business Hub · Event Dossier</p>
                        <h1 class="mt-4 text-3xl font-bold leading-tight text-white sm:text-4xl" style="{{ $serif }}">{{ $event->name }}</h1>
                        <input type="text" wire:model.blur="data.meta.subtitle" class="mt-2 w-full border-0 bg-transparent p-0 text-sm text-white/60 placeholder:text-white/30 focus:outline-none focus:ring-0" placeholder="Document subtitle…" style="{{ $serif }} font-style: italic;">
                        <div class="mt-6 grid grid-cols-2 gap-x-8 gap-y-4 border-t border-white/10 pt-5 sm:grid-cols-3">
                            @foreach ([['Prepared For','prepared_for'],['Prepared By','prepared_by'],['Confidentiality','confidentiality']] as [$lbl,$mk])
                                <div>
                                    <p class="text-eyebrow font-bold uppercase tracking-[0.2em] text-white/40">{{ $lbl }}</p>
                                    <input type="text" wire:model.blur="data.meta.{{ $mk }}" class="mt-1 w-full border-0 border-b border-transparent bg-transparent p-0 pb-0.5 text-sm font-medium text-white transition placeholder:text-white/30 focus:border-gold-400/60 focus:outline-none focus:ring-0">
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- How-to strip --}}
                <div class="border-b border-line bg-gold-50/40 px-8 py-3.5 sm:px-12">
                    <div class="flex items-start gap-2.5">
                        <span class="mt-0.5 text-gold-500">✦</span>
                        <textarea wire:model.blur="data.meta.how_to" rows="2" class="w-full resize-none border-0 bg-transparent p-0 text-micro italic leading-relaxed text-navy-500 focus:outline-none focus:ring-0" placeholder="How to use this document…"></textarea>
                    </div>
                </div>

                {{-- Sections --}}
                <div class="space-y-11 px-8 py-10 sm:px-12">
                    @foreach ($sections as $key => [$num, $title, $type])
                        <section id="sec-{{ $key }}" wire:key="sec-{{ $key }}" class="scroll-mt-32">
                            {{-- section header --}}
                            <div class="mb-5 flex items-end justify-between gap-3 border-b border-line pb-2.5">
                                <div class="flex items-baseline gap-3">
                                    <span class="pf text-2xl font-bold leading-none text-gold-400/40">{{ str_pad($num, 2, '0', STR_PAD_LEFT) }}</span>
                                    <h3 class="pf text-lg font-bold text-navy-900">{{ $title }}</h3>
                                </div>
                                <div class="flex shrink-0 items-center gap-1.5">
                                    @if (in_array($type, ['bullets', 'kpi', 'twocol', 'approval'], true))
                                        <button type="button" wire:click="addRow('{{ $key }}')" class="rounded-full border border-line px-2.5 py-1 text-eyebrow font-bold uppercase tracking-wide text-navy-400 transition hover:border-gold-300 hover:text-gold-600">＋ Add</button>
                                    @endif
                                    @if ($savedSection === $key)
                                        <span class="flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-eyebrow font-bold uppercase tracking-wide text-emerald-600 ring-1 ring-emerald-200">✓ Saved</span>
                                    @else
                                        <button type="button" wire:click="saveSection('{{ $key }}')" class="rounded-full bg-navy-900 px-3 py-1 text-eyebrow font-bold uppercase tracking-wide text-white transition hover:bg-navy-800">Save</button>
                                    @endif
                                </div>
                            </div>

                            @include('livewire.hub.partials.brief-section', ['type' => $type, 'key' => $key])
                        </section>
                    @endforeach
                </div>

                <div class="border-t border-line bg-navy-950 px-8 py-4 text-center sm:px-12">
                    <p class="text-eyebrow font-medium uppercase tracking-[0.3em] text-white/40">{{ $data['meta']['confidentiality'] ?? 'Confidential' }} · Elite Business Hub · v{{ $version }}</p>
                </div>
            </div>
        </main>
    </div>
</div>
