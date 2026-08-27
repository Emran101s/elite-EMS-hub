<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
    <div class="rounded-lg border border-line bg-white p-6">
        <div class="mb-4 flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-gold-50 text-gold-700"><x-icon name="sparkles" class="h-5 w-5" /></span>
            <div>
                <h3 class="text-base font-bold text-ink">AI Daily Summary</h3>
                <p class="text-xs text-muted">Rule-based advisor v1 — every point traces to a record. LLM backend arrives in a later phase.</p>
            </div>
        </div>

        <div class="rounded-lg bg-page px-5 py-4">
            <p class="text-sm font-bold text-ink">{{ $ai['headline'] }}</p>

            @if (count($ai['attention']))
                <p class="mb-1.5 mt-4 text-[0.65rem] font-bold uppercase tracking-wide text-muted">Main attention points</p>
                <ol class="space-y-2 text-sm text-ink">
                    @foreach ($ai['attention'] as $i => $point)
                        <li class="flex gap-2"><span class="font-bold text-gold-700">{{ $i + 1 }}.</span> {{ $point }}</li>
                    @endforeach
                </ol>
            @else
                <p class="mt-3 text-sm text-ink">No blockers detected across approvals, risks, suppliers, tasks and agenda.</p>
            @endif

            <div class="mt-4 rounded-lg bg-gold-50 px-4 py-3 ring-1 ring-gold-200">
                <p class="text-[0.65rem] font-bold uppercase tracking-wide text-gold-700">Recommended action</p>
                <p class="mt-1 text-sm text-ink">{{ $ai['recommendation'] }}</p>
            </div>
        </div>

        <p class="mb-2 mt-6 text-[0.65rem] font-bold uppercase tracking-wide text-muted">Ask the advisor (coming with the LLM backend)</p>
        <div class="flex flex-wrap gap-2">
            @foreach (['What is delayed?', 'Which supplier is risky?', 'Is the budget healthy?', 'What should I do today?', 'Summarize for management', 'Draft the closing report'] as $question)
                <span class="cursor-not-allowed rounded-full border border-line bg-white px-3.5 py-1.5 text-xs text-muted" title="Available when the LLM backend ships">{{ $question }}</span>
            @endforeach
        </div>
    </div>

    <div class="rounded-lg border border-line bg-white p-5">
        <h3 class="mb-3 text-base font-bold text-ink">Health Inputs</h3>
        <ul class="space-y-2 text-xs">
            @foreach ([
                'tasks' => 'Task Completion', 'budget' => 'Budget Health', 'suppliers' => 'Supplier Readiness',
                'venue' => 'Venue Readiness', 'transport' => 'Transport Readiness', 'agenda' => 'Agenda Completion', 'risk' => 'Risk Level',
            ] as $key => $label)
                <li class="flex items-center justify-between">
                    <span class="text-muted">{{ $label }}</span>
                    <span class="font-bold text-ink">{{ $health['components'][$key] !== null ? $health['components'][$key].'%' : '—' }}</span>
                </li>
            @endforeach
            <li class="flex items-center justify-between border-t border-line pt-2">
                <span class="font-semibold text-ink">Weighted score</span>
                <span class="font-bold text-gold-700">{{ $health['score'] !== null ? $health['score'].'%' : '—' }}</span>
            </li>
        </ul>
    </div>
</div>
