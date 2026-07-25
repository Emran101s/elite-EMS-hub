{{--
    The Event Hub on the ORBIT shell.

    The workspace is the module's own view — the same partials the previous hub
    included, so no module had to be rewritten to move onto the new chrome. What
    changes is everything around it: the command ribbon, the live KPI ribbon, the
    orbit, and a pair of rails that re-read on every module.

    Selected with config('orbit.nav'); EventHubController picks the view.
--}}
@php
    use App\Support\Tone;

    $moduleLabel = \App\Models\Event::moduleLabel($tab);

    // Event Pulse — the same figures the health engine already computes, so the
    // rail can never disagree with the score in the ribbon.
    $pulse = collect($health['components'] ?? [])
        ->filter(fn ($score) => $score !== null)
        ->map(fn ($score, $key) => [
            'label' => ucfirst($key),
            'value' => (int) round($score).'%',
            'tone' => Tone::forHealth((int) round($score))->value,
        ])
        ->values()
        ->all();

    $overdueCount = $event->tasks->filter(fn ($t) => $t->isOpen() && $t->due_on?->isPast())->count();
    $pendingApprovals = $event->approvals->where('status', 'pending')->count();
    $openRisks = $event->risks->filter->isOpen()->count();

    $pulse = array_merge($pulse, array_values(array_filter([
        $overdueCount ? ['label' => 'Overdue tasks', 'value' => (string) $overdueCount, 'tone' => 'critical'] : null,
        $pendingApprovals ? ['label' => 'Approvals pending', 'value' => (string) $pendingApprovals, 'tone' => 'flare'] : null,
        $openRisks ? ['label' => 'Open risks', 'value' => (string) $openRisks, 'tone' => 'plasma'] : null,
    ])));

    // AI Event Director — a chief of staff, not a chatbot: what needs a decision,
    // in the order it needs one. Straight from EventHealthService::aiSummary().
    $insights = collect($ai['attention'] ?? [])->take(4)->map(function ($line) {
        $tone = str_contains($line, 'Risk') || str_contains($line, 'issue') || str_contains($line, 'overdue')
            ? 'critical'
            : (str_contains($line, 'approval') || str_contains($line, 'unassigned') ? 'flare' : 'ion');
        $parts = explode('—', $line, 2);

        return [
            'title' => trim($parts[0]),
            'sub' => isset($parts[1]) ? trim($parts[1]) : null,
            'tone' => $tone,
            'icon' => $tone === 'critical' ? 'warn' : ($tone === 'flare' ? 'clock' : 'spark'),
        ];
    })->all();
@endphp

<x-layouts.orbit :event="$event" :module="$tab"
                 :kpis="\App\Support\OrbitShell::kpis($event)"
                 :title="$event->name.' — Event Hub'">

    @includeIf('events.hub.'.$tab, [
        'event' => $event,
        'health' => $health,
        'ai' => $ai,
        'alerts' => $alerts,
        'workload' => $workload,
    ])

    <x-slot:rails>
        <x-orbit.card>
            <x-slot:head>
                <h3 class="o-card__title">Event Pulse</h3>
                <span class="o-badge" data-tone="{{ Tone::forHealth((int) $health['score'])->value }}">{{ (int) $health['score'] }}%</span>
            </x-slot:head>
            @if ($pulse)
                <x-orbit.pulse :metrics="$pulse" />
            @else
                <p class="o-mute" style="margin:0">Nothing is being tracked on this event yet.</p>
            @endif
        </x-orbit.card>

        <x-orbit.ai-panel :insights="$insights" :chips="['What is at risk?', 'Budget forecast', 'Summarise this week']">
        </x-orbit.ai-panel>

        @if ($alerts->isNotEmpty())
            <x-orbit.card title="Live alerts" :pad="false">
                <x-orbit.feed style="padding:var(--o-3)">
                    @foreach ($alerts as $alert)
                        <x-orbit.alert
                            :tone="match ($alert['tone']) { 'risk' => 'critical', 'warn' => 'flare', default => 'ion' }"
                            :icon="$alert['tone'] === 'risk' ? 'warn' : 'bell'"
                            :title="$alert['title']"
                            :sub="$alert['sub']"
                            :time="$alert['when']?->diffForHumans(short: true)" />
                    @endforeach
                </x-orbit.feed>
            </x-orbit.card>
        @endif
    </x-slot:rails>
</x-layouts.orbit>
