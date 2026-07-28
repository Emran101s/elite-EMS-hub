<x-layouts.app title="Projects" subtitle="Portfolios that group events, tasks and budgets.">
    <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
        @forelse ($projects as $project)
            <div class="card p-6">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-sm font-bold text-navy-900">{{ $project->name }}</p>
                    <x-status-badge :status="$project->status" />
                </div>
                <p class="mt-2 line-clamp-2 text-xs text-muted">{{ $project->description }}</p>
                <dl class="mt-5 grid grid-cols-2 gap-3 border-t border-line pt-4 text-center">
                    <div>
                        <dt class="text-[0.65rem] uppercase tracking-wide text-muted">Events</dt>
                        <dd class="mt-1 text-sm font-bold text-navy-900">{{ $project->events_count }}</dd>
                    </div>
                    <div>
                        <dt class="text-[0.65rem] uppercase tracking-wide text-muted">Budget</dt>
                        <dd class="mt-1 text-sm font-bold text-navy-900">
                            ${{ \Illuminate\Support\Number::abbreviate($project->budget_cents / 100, 1) }}
                        </dd>
                    </div>
                </dl>
            </div>
        @empty
            <p class="col-span-full py-12 text-center text-sm text-muted">No projects yet.</p>
        @endforelse
    </div>
</x-layouts.app>
