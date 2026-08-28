<div class="cx-canvas grid gap-3 xl:grid-cols-[minmax(0,1fr)_19rem]">
    {{-- ══ The briefing ══
         The advisor speaks on navy, the way the module hero and the
         portfolio spotlight do — this is the one surface on the tab that
         is telling you something rather than listing it, so it gets the
         dark ground and the others stay light beneath it. --}}
    <div class="min-w-0">
        <div class="cx-lhero">
            <div class="relative flex items-start gap-3">
                <span class="cx-cathex shrink-0" style="width:32px;height:35px;background:var(--cx-accent);color:var(--cx-ink)">
                    <x-icon name="sparkles" class="h-4 w-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-eyebrow font-bold uppercase tracking-[0.22em]" style="color: var(--cx-accent-hi)">AI Daily Summary</p>
                    <p class="mt-1 text-[15px] font-bold leading-snug text-white">{{ $ai['headline'] }}</p>
                    <p class="mt-1 text-[11px]" style="color: rgba(234,240,251,.5)">
                        Rule-based advisor v1 — every point traces to a record. LLM backend arrives in a later phase.
                    </p>
                </div>
            </div>

            @if (count($ai['attention']))
                <div class="relative mt-3 border-t pt-3" style="border-color: var(--cx-espresso-line)">
                    <p class="mb-2 text-eyebrow font-bold uppercase tracking-[0.14em]" style="color: rgba(234,240,251,.5)">Main attention points</p>
                    <ol class="space-y-1.5">
                        @foreach ($ai['attention'] as $i => $point)
                            <li class="flex gap-2 text-[13px] leading-snug text-white">
                                <span class="shrink-0 font-bold" style="color: var(--cx-accent-hi)">{{ $i + 1 }}.</span>
                                <span style="color: rgba(234,240,251,.9)">{{ $point }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @else
                <p class="relative mt-3 border-t pt-3 text-[13px]" style="border-color: var(--cx-espresso-line); color: rgba(234,240,251,.8)">
                    No blockers detected across approvals, risks, suppliers, tasks and agenda.
                </p>
            @endif

            <div class="cx-spot-action">
                <span class="cx-al">Recommended action</span>
                <p class="cx-av">{{ $ai['recommendation'] }}</p>
            </div>
        </div>

        {{-- The questions are visibly not-yet-live: they read as a preview of
             what the advisor will answer, not as buttons that quietly do
             nothing when clicked. --}}
        <div class="cx-lcard">
            <div class="cx-lcard-head">
                <span class="cx-lt">Ask the advisor</span>
                <span class="text-eyebrow text-muted">Coming with the LLM backend</span>
            </div>
            <div class="flex flex-wrap gap-1.5 p-3">
                @foreach (['What is delayed?', 'Which supplier is risky?', 'Is the budget healthy?', 'What should I do today?', 'Summarize for management', 'Draft the closing report'] as $question)
                    <span class="cx-chip cursor-not-allowed opacity-60" title="Available when the LLM backend ships">{{ $question }}</span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ══ Health inputs ══
         Each input gets a bar, not just a number. The weighted score is
         what the header already shows, so here it closes the list as the
         sum of its parts rather than repeating as a headline. --}}
    <div class="xl:sticky xl:top-12 xl:h-fit">
        <div class="cx-panel">
            <div class="cx-lcard-head" style="background: var(--cx-espresso-1); border-bottom-color: transparent;">
                <span class="flex items-center gap-2 text-[10.5px] font-bold uppercase tracking-[0.14em]" style="color:#F0E7D5">
                    <span class="cx-cathex" style="width:22px;height:24px;background:var(--cx-accent);color:var(--cx-ink)">
                        <x-icon name="chart" class="h-3 w-3" />
                    </span>
                    Health Inputs
                </span>
            </div>

            <div class="cx-panel-sec">
                @foreach ([
                    'tasks' => 'Task Completion', 'budget' => 'Budget Health', 'suppliers' => 'Supplier Readiness',
                    'venue' => 'Venue Readiness', 'transport' => 'Transport Readiness', 'agenda' => 'Agenda Completion', 'risk' => 'Risk Level',
                ] as $key => $label)
                    @php $v = $health['components'][$key]; @endphp
                    <div class="mb-2 last:mb-0">
                        <div class="mb-1 flex items-baseline justify-between gap-2">
                            <span class="text-[11.5px] text-muted">{{ $label }}</span>
                            <span class="text-[11.5px] font-bold tabular-nums {{ $v === null ? 'text-muted' : 'text-ink' }}">{{ $v !== null ? $v.'%' : '—' }}</span>
                        </div>
                        <div class="cx-bar">
                            <span class="{{ $v === null ? '' : ($v >= 60 ? 'tone-ok' : ($v >= 30 ? 'tone-warn' : 'tone-risk')) }}"
                                  style="width: {{ $v ?? 0 }}%"></span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="cx-panel-sec flex items-baseline justify-between">
                <span class="text-[12px] font-semibold text-ink">Weighted score</span>
                <span class="text-[15px] font-bold tabular-nums" style="color: var(--cx-accent-ink)">{{ $health['score'] !== null ? $health['score'].'%' : '—' }}</span>
            </div>
        </div>
    </div>
</div>
