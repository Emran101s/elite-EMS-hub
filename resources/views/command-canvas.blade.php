@php use App\Support\CommandCanvasData as D; @endphp
<x-layouts.canvas title="Command Canvas">

    <x-canvas.command-header />

    <div class="px-4 pb-10 xl:px-6">

        {{-- 2 · Company pulse --}}
        <x-canvas.company-pulse-strip :items="D::pulse()" :health="D::health()" />

        {{-- 3 · dock  ·  4 · canvas  ·  5–7 · right panels --}}
        <div class="mt-5 flex flex-col gap-5 lg:flex-row lg:items-start">

            <x-canvas.left-command-dock :items="D::dock()" current="home" />

            <div class="min-w-0 flex-1">
                <x-canvas.event-command-canvas :primary="D::primaryEvent()" :events="D::events()" />
            </div>

            {{-- On tablet this column drops below the canvas; on mobile it stacks. --}}
            <aside class="flex w-full shrink-0 flex-col gap-5 lg:w-[330px] xl:w-[356px]">
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
