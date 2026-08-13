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
         Identity, scale, the one thing that needs a person, module-by-module
         progress, where to go, and what is true right now. See
         resources/views/components/event-header.blade.php for why it is these
         four blocks and not the one white bar it replaces. ══ --}}
    <x-event-header :event="$event" :header="$header" />

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

    <div class="mt-4">
        <x-eo.orbit-journey :event="$event" :header="$header" :journey="$journey" :active-key="$activeJourney['key']" />
    </div>

    {{-- ══ Priority Area (Phase E) ══
         Open risks, pending approvals, escalations — scoped to this one
         event, same pattern as Command Center's Today's Command Queue.
         Kept directly below the Orbit per Phase E.2's approved placement. ══ --}}
    <div class="mt-4">
        <x-eo.priority-area :event="$event" />
    </div>

    <div class="mt-5">
        @includeIf('events.hub.' . $tab, ['event' => $event, 'health' => $health, 'ai' => $ai, 'alerts' => $alerts, 'workload' => $workload])
    </div>
</x-layouts.app>
