@php use App\Support\CommandCanvasData as D; @endphp
<x-layouts.canvas title="Command Canvas">

    <x-canvas.command-header />

    <div class="px-4 pb-10 xl:px-6">

        {{-- 2 · Company pulse --}}
        <x-canvas.company-pulse-strip :items="D::pulse()" :health="D::health_()" />

        {{-- 3 · dock  ·  4 · canvas  ·  5–7 · right panels --}}
        {{-- Measured, not guessed: the radial arena needs ~845px for a hexagon
             flanked by two pods. With the panel column alongside, only a 1536px
             screen (the width the reference is drawn at) leaves that much — at
             1280 the canvas gets 722px and the ring collapses. So the panels sit
             beside the canvas from 2xl and drop beneath it below that, where the
             canvas takes the full width instead of being crushed. --}}
        <div class="mt-5 flex flex-col gap-5 2xl:flex-row 2xl:items-start">

            <x-canvas.left-command-dock :items="D::dock()" current="home" />

            <div class="min-w-0 flex-1">
                <x-canvas.event-command-canvas :primary="D::primaryEvent()" :events="D::events()" />
            </div>

            {{-- On tablet this column drops below the canvas; on mobile it stacks. --}}
            <aside class="grid w-full shrink-0 grid-cols-1 gap-5 md:grid-cols-2 2xl:w-[356px] 2xl:grid-cols-1">
                <x-canvas.ai-executive-director :items="D::aiRoute()" />
                <x-canvas.live-signals-panel :items="D::signals()" />
                <x-canvas.quick-actions-panel :items="D::quickActions()" />
            </aside>
        </div>

        {{-- 8 · Insight cards --}}
        <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
            <x-canvas.mission-route-card :phases="D::missionRoute()" />
            <x-canvas.financial-overview-card :data="D::financial()" />
            <x-canvas.team-workload-card :teams="D::workload()" />
            <x-canvas.upcoming-milestones-card :items="D::milestones()" />
        </div>
    </div>
</x-layouts.canvas>
