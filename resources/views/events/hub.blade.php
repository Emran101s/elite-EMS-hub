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
         Identity, status, location, dates, journey type — and, in the same
         card, the Event Pulse row (Health/Readiness/Days Out/Budget/Risk):
         folded into the header itself rather than floating as its own
         strip underneath it. Real numbers off EventCommandHeader::for() —
         see resources/views/components/eo/hubx-header.blade.php.
         Team Workload and Recent Activity used to sit permanently on
         Overview; they're real, just secondary — accessed intentionally
         from the header's own ⋯ icon now, not stacked on every visit.
         Shared x-data so the header's button and the drawer (siblings in
         the rendered DOM, even though they're separate Blade components)
         agree on open/closed state. ══ --}}
    <div x-data="{ utilitiesOpen: false }">
        <x-hub.header :event="$event" :header="$header" :health="$health" />
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

    @php
        // The Universal Inspector shows only on tabs that DON'T already carry
        // their own right-hand rail. A tab with its own control panel —
        // budget/sponsors/transportation/catering/exhibition/accommodation/
        // attendees all render a fixed 1fr+300px sidebar; risks/suppliers a
        // 1fr+320px detail card; planning/tasks a full-width board with an
        // overlay control center — would otherwise cram a redundant THIRD
        // column (its own rail + the inspector) into ~1000px, the exact
        // "everything's squeezed / wasted gutters" problem this fixes. Those
        // tabs get the whole width for their own layout; the inspector is
        // kept for the simple list/card/document tabs that genuinely have an
        // empty right side to fill.
        // Agenda is deliberately not on this list. It is a builder, not a
        // list — its board wants every pixel of width it can get, and the
        // Inspector was spending 280px of it repeating the module header's
        // own status and an Add Session button the toolbar already carries.
        $showPanel = in_array($tab, [
            'overview', 'approvals', 'speakers',
            'venue', 'pricing', 'brief', 'contract', 'files',
        ], true);
    @endphp

    {{-- ══ Active Workspace | Inspector ══
         The Command Stack is gone outright — not collapsed, not a drawer,
         not replaced by anything else. Its job (what needs a person) is
         already covered by the Header's own attention pill, Event Pulse,
         and the Inspector's Next Action. Removing the column gives the
         workspace the width back. Left: the tab's own existing content,
         unchanged — Mission Timeline IS the Overview workspace now, not a
         card floating in a bigger layout. Right: the Universal Module
         Inspector, per-module data. ══ --}}
    <div class="ehx-grid {{ $showPanel ? 'has-panel' : '' }} mt-3">
        <div class="min-w-0">
            <x-hub.module-header :event="$event" :header="$header" :tab="$tab" />

            @includeIf('events.hub.' . $tab, ['event' => $event, 'health' => $health, 'ai' => $ai, 'alerts' => $alerts, 'workload' => $workload])
        </div>

        @if ($showPanel)
            <div class="ehx-col-panel">
                <x-hub.inspector :event="$event" :header="$header" :tab="$tab" />
            </div>
        @endif
    </div>
</x-layouts.app>
