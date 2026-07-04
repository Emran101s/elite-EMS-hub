<x-layouts.app
    :title="'Welcome back, ' . str(auth()->user()->name)->before(' ') . ' 👋'"
    subtitle="Here's what's happening across your events and projects.">

    {{-- KPI row --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
        @foreach ([
            ['label' => 'Total Events', 'icon' => 'calendar', 'value' => $stats['events'], 'hint' => 'across the region'],
            ['label' => 'Active Projects', 'icon' => 'folder', 'value' => $stats['projects'], 'hint' => 'portfolios running'],
            ['label' => 'Total Budget', 'icon' => 'currency', 'value' => '$' . \Illuminate\Support\Number::abbreviate($stats['budget'] / 100, 2), 'hint' => 'committed to events'],
            ['label' => 'Open Tasks', 'icon' => 'clipboard', 'value' => $stats['openTasks'], 'hint' => 'pending + in progress'],
            ['label' => 'At Risk', 'icon' => 'bell', 'value' => $stats['atRisk'], 'hint' => 'events needing attention', 'risk' => $stats['atRisk'] > 0],
        ] as $kpi)
            <div class="card p-5">
                <div class="flex items-center gap-3">
                    <span @class([
                            'flex h-10 w-10 items-center justify-center rounded-xl',
                            'bg-risk/10 text-risk' => $kpi['risk'] ?? false,
                            'bg-navy-50 text-navy-600' => ! ($kpi['risk'] ?? false),
                        ])>
                        <x-icon :name="$kpi['icon']" class="h-5 w-5" />
                    </span>
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ $kpi['label'] }}</p>
                </div>
                <p class="mt-4 text-3xl font-bold {{ ($kpi['risk'] ?? false) ? 'text-risk' : 'text-navy-900' }}">{{ $kpi['value'] }}</p>
                <p class="mt-1 text-xs text-muted">{{ $kpi['hint'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Operations Hub placeholder --}}
    <div class="card mt-6 flex flex-col items-center px-8 py-20 text-center">
        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gold-50 text-gold-600">
            <x-icon name="sparkles" class="h-7 w-7" />
        </span>
        <h2 class="mt-5 text-lg font-bold text-navy-900">Operations Hub</h2>
        <p class="mt-2 max-w-lg text-sm text-muted">
            The real-time map of your events ecosystem — event islands around the AI Command Core,
            live alerts, resource utilization and budget — arrives in Phase 2.
        </p>
        <span class="mt-5 inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-3 py-1 text-xs font-semibold text-gold-700 ring-1 ring-gold-200">
            Planned · Phase 2
        </span>
    </div>
</x-layouts.app>
