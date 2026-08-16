@php $theme = $event->theme(); @endphp

<x-layouts.app :title="$event->name . ' — Event Hub'"
               :hide-title-row="true"
               :crumbs="[
                   ['label' => 'Command Center', 'href' => route('home')],
                   ['label' => 'Events', 'href' => route('events.index')],
                   ['label' => $event->name, 'href' => route('events.hub', $event)],
                   ['label' => \App\Models\Event::moduleLabel($tab)],
               ]">

    {{-- ══ The header ══
         Identity, status, location, dates, journey type. Real numbers off
         EventCommandHeader::for() — see resources/views/components/eo/hubx-header.blade.php. ══ --}}
    <x-eo.hubx-header :event="$event" :header="$header" />

    {{-- ══ Orbit Journey (Phase E.2) ══
         Replaces the old flat Journey strip and its separate sticky "doors"
         row entirely — this is the only journey element on the page. The
         Event is the centre object; the eight stages orbit it as readable
         cards on a progress ring; the active stage's own enabled tabs attach
         below as satellites. See
         docs/32-event-hub-orbit-journey-architecture.md. ══ --}}
    @php
        // Stage → tabs. Each stage lands on existing hub tabs (no route
        // changes); the active stage follows the current tab, exactly as
        // it did before this phase.
        $journey = [
            ['key' => 'overview', 'label' => 'Overview', 'tabs' => ['overview']],
            ['key' => 'brief', 'label' => 'Brief', 'tabs' => ['brief', 'contract']],
            ['key' => 'planning', 'label' => 'Planning', 'tabs' => ['planning', 'tasks']],
            ['key' => 'programme', 'label' => 'Programme', 'tabs' => ['agenda', 'speakers']],
            ['key' => 'operations', 'label' => 'Operations', 'tabs' => ['venue', 'transportation', 'accommodation', 'catering', 'suppliers', 'exhibition', 'attendees']],
            ['key' => 'commercial', 'label' => 'Commercial', 'tabs' => ['budget', 'pricing', 'sponsors']],
            ['key' => 'control', 'label' => 'Control', 'tabs' => ['risks', 'approvals', 'reports', 'ai', 'files']],
            ['key' => 'closeout', 'label' => 'Closeout', 'tabs' => ['settings']],
        ];

        $activeJourney = collect($journey)->first(fn ($step) => in_array($tab, $step['tabs'], true))
            ?? $journey[0];
        $activeJourneyIndex = collect($journey)->search(fn ($step) => $step['key'] === $activeJourney['key']);

        // Orbit stage state — a thin presentation-layer read of
        // EventCommandHeader's own numbers, not a new computation.
        // meters() covers 6 of the 23 tabs and attention() covers a
        // different 6 — a stage whose tabs fall outside both simply
        // carries no pct and no issue count, rather than a fabricated one.
        $metersByKey = collect($header['meters'])->keyBy('key');
        $attentionByKey = collect($header['attention'] ?? []);

        // logistics is one meter for three tabs (EventCommandHeader::meters()
        // — "the venue, the suppliers... and transport") — aliased here so
        // Operations can read it without a tab literally called "logistics".
        $meterAliases = ['venue' => 'logistics', 'suppliers' => 'logistics', 'transportation' => 'logistics'];

        $journey = collect($journey)->map(function (array $stage, int $i) use ($metersByKey, $attentionByKey, $meterAliases, $activeJourney, $activeJourneyIndex) {
            $pcts = collect($stage['tabs'])
                ->map(fn ($k) => $metersByKey->get($meterAliases[$k] ?? $k))
                ->filter()
                ->pluck('pct')
                ->filter(fn ($p) => $p !== null);

            $signals = collect($stage['tabs'])->map(fn ($k) => $attentionByKey->get($k))->filter();

            $stage['pct'] = $pcts->isNotEmpty() ? (int) round($pcts->avg()) : null;
            $stage['issues'] = (int) $signals->sum('count');

            $isActive = $stage['key'] === $activeJourney['key'];
            $hasAlarm = $signals->contains(fn ($s) => $s['tone'] === 'alarm');
            $hasWait = $signals->contains(fn ($s) => $s['tone'] === 'wait');

            $stage['state'] = match (true) {
                $isActive => 'active',
                $hasAlarm => 'blocked',
                $hasWait => 'watch',
                $i < $activeJourneyIndex => 'complete',
                $stage['pct'] !== null && $stage['pct'] > 0 => 'pending',
                default => 'future',
            };

            return $stage;
        })->all();
    @endphp

    @php
        // Every module the Universal Inspector has a real data case for
        // (see hubx-inspector.blade.php's match()) plus Overview, which
        // falls back to the Agenda case — the rest render without a panel
        // rather than showing an inspector with nothing in it.
        $showPanel = in_array($tab, ['overview', 'agenda', 'budget', 'transportation', 'approvals', 'accommodation', 'planning'], true);
    @endphp

    {{-- ══ Mission Control grid (redesign) ══
         Left: Command Stack (EventCommandHeader::attention(), real signals
         only). Then the vertical Module Dock (meters() + attention()).
         Then the centre — Stage Radar, Cortex (Overview only), the tab's
         own existing content unchanged, and the KPI strip (Overview only).
         Right: the Universal Module Inspector, per-module data. ══ --}}
    <div class="hubx-grid {{ $showPanel ? 'has-panel' : '' }} mt-3">
        <div class="hubx-col-stack">
            <x-eo.hubx-command-stack :event="$event" :header="$header" />
        </div>

        <div class="hubx-col-rail">
            <x-eo.hubx-module-rail :event="$event" :header="$header" :active-tab="$tab" />
        </div>

        <div class="min-w-0">
            <x-eo.orbit-journey :event="$event" :header="$header" :journey="$journey" :active-key="$activeJourney['key']" />

            @if ($tab === 'overview')
                <div class="mt-3">
                    <x-eo.hubx-cortex :event="$event" :header="$header" :ai="$ai" />
                </div>
            @endif

            @if ($tab === 'overview')
                <div class="mt-3">
                    <x-eo.hubx-kpi-strip :event="$event" :header="$header" :health="$health" />
                </div>
            @endif

            {{-- Universal Module Header — same modules the Inspector has
                 real data for, shown once inside the module's own content
                 rather than on Overview (which is the whole dashboard, not
                 a single module). --}}
            @if ($showPanel && $tab !== 'overview')
                <div class="mt-3">
                    <x-eo.hubx-module-header :event="$event" :header="$header" :tab="$tab" />
                </div>
            @endif

            <div class="mt-3">
                @includeIf('events.hub.' . $tab, ['event' => $event, 'health' => $health, 'ai' => $ai, 'alerts' => $alerts, 'workload' => $workload])
            </div>
        </div>

        @if ($showPanel)
            <div class="hubx-col-panel">
                <x-eo.hubx-inspector :event="$event" :header="$header" :tab="$tab" />
            </div>
        @endif
    </div>
</x-layouts.app>
