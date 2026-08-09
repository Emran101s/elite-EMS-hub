@php
    $crumbs = [
        ['label' => 'Command Center'],
        ['label' => 'Phase 2 Shell Review'],
    ];
@endphp

<x-layouts.eo-shell title="Phase 2 Shell" :crumbs="$crumbs">
    <div id="shell-workspace-content" class="eo-event-atmosphere -m-1 space-y-6 rounded-[24px] p-1 sm:p-2">
        <x-eo.page-header
            eyebrow="Phase 2 · App shell preview"
            title="Event Command Operating System"
            subtitle="MiniRail + ContextSidebar + TopCommandBar + WorkspaceShell. Live product pages are unchanged until this shell is approved."
        >
            <x-slot:actions>
                <a href="{{ route('design.soft-command') }}" class="eo-btn-ghost eo-btn-sm">← Component gallery</a>
                <span class="eo-journey-chip">Shell review</span>
            </x-slot:actions>
        </x-eo.page-header>

        <div id="specimen-event-dna" class="grid gap-4 xl:grid-cols-12">
            <div id="specimen-mission-radar" class="xl:col-span-4">
                <x-eo.soft-card class="flex flex-col items-center p-5">
                    <p class="eo-label mb-3 self-start">Mission Radar</p>
                    <x-eo.mission-radar label="Portfolio pulse" size="md" />
                    <p class="eo-body mt-4 text-center text-[12px] text-eo-muted">
                        Signature identity for Command Center, Portfolio, Overview, and Operations readiness.
                    </p>
                </x-eo.soft-card>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:col-span-8">
                <div id="specimen-mission-card">
                    <x-eo.mission-card
                        title="Gulf Leadership Summit"
                        type="Summit"
                        stage="Planning"
                        venue="Riyadh"
                        dates="18–20 Sep"
                        :readiness="72"
                    />
                </div>
                <div id="specimen-event-health-card">
                    <x-eo.event-health-card
                        title="Gulf Leadership Summit"
                        :score="74"
                        status="warn"
                        hint="Transport + catering on the critical path"
                    />
                </div>
                <div id="specimen-operations-card">
                    <x-eo.operations-card
                        title="Live operations desk"
                        subtitle="Arrivals · transport · check-in"
                        :open="14"
                        :due="3"
                        :blocked="1"
                    />
                </div>
                <div id="specimen-commercial-card">
                    <x-eo.commercial-card
                        title="Apex Group · contracted"
                        subtitle="Proposal accepted · contract in review"
                        value="JD 186K"
                        meta="Margin target 22%"
                    />
                </div>
            </div>
        </div>

        <div id="specimen-readiness-cards" class="grid gap-4 md:grid-cols-3">
            <x-eo.readiness-card
                domain="Venue readiness"
                title="Main ballroom lock"
                :percent="88"
                status="ok"
                hint="AV + seating confirmed"
            />
            <x-eo.readiness-card
                domain="Delegate readiness"
                title="VIP arrival corridor"
                :percent="61"
                status="warn"
                hint="2 manifests still draft"
            />
            <x-eo.readiness-card
                domain="Exhibition readiness"
                title="Partner pavilion"
                :percent="40"
                status="risk"
                hint="Booth production lag"
            />
        </div>

        <x-eo.soft-card>
            <p class="eo-label">Shell components in this preview</p>
            <ul class="mt-3 grid gap-2 text-[13px] sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    'x-eo.app-shell',
                    'x-eo.mini-rail',
                    'x-eo.context-sidebar',
                    'x-eo.top-command-bar',
                    'x-eo.workspace-shell',
                    'x-eo.mission-radar',
                    'x-eo.mission-card',
                    'x-eo.readiness-card',
                    'x-eo.operations-card',
                    'x-eo.commercial-card',
                    'x-eo.event-health-card',
                ] as $name)
                    <li class="rounded-xl bg-eo-workspace px-3 py-2 font-medium text-eo-text">{{ $name }}</li>
                @endforeach
            </ul>
            <p class="mt-4 text-[12px] text-eo-muted">
                Gold is brand-only (mark + premium commercial accents). Teal carries operational action and active shell states.
            </p>
        </x-eo.soft-card>
    </div>
</x-layouts.eo-shell>
