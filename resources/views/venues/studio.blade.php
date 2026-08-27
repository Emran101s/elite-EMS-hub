<x-layouts.app :title="$venue->name . ' — Venue Studio'"
               :hide-title-row="true"
               :crumbs="[
                   ['label' => 'Command Center', 'href' => route('home')],
                   ['label' => 'Venues', 'href' => route('venues.index')],
                   ['label' => $venue->name, 'href' => route('venues.show', $venue)],
                   ['label' => \App\Models\Venue::STUDIO_TABS[$tab][0] ?? ucfirst($tab)],
               ]">

    <x-eo.venue-studio.header :venue="$venue" :header="$header" />

    <div class="mt-3">
        <x-eo.venue-studio.module-nav :venue="$venue" :active-tab="$tab" />
    </div>

    <div class="mt-2">
        <x-eo.venue-studio.kpi-strip :venue="$venue" :header="$header" />
    </div>

    <div class="hubx-grid has-panel mt-2">
        <div class="min-w-0">
            @includeIf('venues.studio.' . $tab, ['venue' => $venue, 'header' => $header])
        </div>

        <div class="hubx-col-panel">
            <x-eo.venue-studio.command-tower :venue="$venue" :header="$header" />
        </div>
    </div>
</x-layouts.app>
