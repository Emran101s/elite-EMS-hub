@php $theme = $event->theme(); @endphp

<x-layouts.app :title="$event->name . ' — Event Hub'"
               :hide-title-row="true"
               :crumbs="[
                   ['label' => 'Command Center', 'href' => route('home')],
                   ['label' => 'Events', 'href' => route('events.index')],
                   ['label' => $event->name, 'href' => route('events.hub', $event)],
                   ['label' => \App\Models\Event::moduleLabel($tab)],
               ]">

    {{-- ══ Event Command Header + Event Utilities drawer ══
         Identity, status, location, dates, journey type. Real numbers off
         EventCommandHeader::for() — see resources/views/components/eo/hubx-header.blade.php.
         Team Workload and Recent Activity used to sit permanently on
         Overview; they're real, just secondary — accessed intentionally
         from the header's own ⋯ icon now, not stacked on every visit.
         Shared x-data so the header's button and the drawer (siblings in
         the rendered DOM, even though they're separate Blade components)
         agree on open/closed state. ══ --}}
    <div x-data="{ utilitiesOpen: false }">
        <x-hub.header :event="$event" :header="$header" />
        <x-hub.utilities-drawer :event="$event" :workload="$workload" />
    </div>

    {{-- ══ Module Navigation Bar ══
         Replaces the old Stage Radar / Orbit Journey entirely — no radar,
         no orbit, no lifecycle diagram. A flat door per enabled module,
         same meters()/attention() numbers as everywhere else on this page.
         Full-width row, not a grid column — see hub/module-nav.blade.php. ══ --}}
    <div class="mt-3">
        <x-hub.module-nav :event="$event" :header="$header" :active-tab="$tab" />
    </div>

    {{-- ══ Event Pulse Strip ══
         Health + Readiness (primary), Days Out / Budget / Risk (secondary).
         Shown on every tab now, not just Overview, so the event's vitals
         stay visible no matter which module is open. ══ --}}
    <div class="mt-2">
        <x-hub.kpi-strip :event="$event" :header="$header" :health="$health" />
    </div>

    @php
        // Every module HubModuleInspector::data() has a real case for (see
        // its match()) plus Overview, which falls back to the Agenda case.
        // ai/settings/reports stay out — they're utility tabs, not modules
        // with their own readiness/metrics story, same exclusion the Module
        // Navigation Bar's own "More" list already makes.
        $showPanel = in_array($tab, [
            'overview', 'agenda', 'budget', 'transportation', 'approvals', 'accommodation', 'planning',
            'tasks', 'risks', 'speakers', 'venue', 'suppliers', 'catering', 'exhibition',
            'sponsors', 'attendees', 'pricing', 'brief', 'contract', 'files',
        ], true);
    @endphp

    {{-- ══ Active Workspace | Inspector ══
         The Command Stack is gone outright — not collapsed, not a drawer,
         not replaced by anything else. Its job (what needs a person) is
         already covered by the Header's own attention pill, Event Pulse,
         the Universal Module Header, and the Inspector's Next Action.
         Removing the column gives the workspace the width back. Left:
         the Universal Module Header (every tab but Overview) then the
         tab's own existing content, unchanged — Mission Timeline IS the
         Overview workspace now, not a card floating in a bigger layout.
         Right: the Universal Module Inspector, per-module data. ══ --}}
    <div class="ehx-grid {{ $showPanel ? 'has-panel' : '' }} mt-2">
        <div class="min-w-0">
            {{-- Universal Module Header — same modules the Inspector has
                 real data for, shown once inside the module's own content
                 rather than on Overview (which is the whole dashboard, not
                 a single module). --}}
            @if ($showPanel && $tab !== 'overview')
                <div class="mb-2">
                    <x-hub.module-header :event="$event" :header="$header" :tab="$tab" />
                </div>
            @endif

            @includeIf('events.hub.' . $tab, ['event' => $event, 'health' => $health, 'ai' => $ai, 'alerts' => $alerts, 'workload' => $workload])
        </div>

        @if ($showPanel)
            <div class="ehx-col-panel">
                <x-hub.inspector :event="$event" :header="$header" :tab="$tab" />
            </div>
        @endif
    </div>
</x-layouts.app>
