<x-layouts.app title="Tasks" :hide-title-row="true">
    @php
        $totalTasks = max($counts->sum(), 1);
        $closedCount = ($counts['done'] ?? 0) + ($counts['approved'] ?? 0);
        $donePct = (int) round($closedCount / $totalTasks * 100);

        $stages = collect(\App\Models\Task::STAGES)->mapWithKeys(fn ($stage, $key) => [
            $key => ['label' => $stage[0], 'hex' => $stage[1], 'count' => $counts[$key] ?? 0],
        ]);

        // Active/Closed, not six near-empty buckets — the page shows 25 tasks
        // at a time, and a finer split than the model's own open/closed flag
        // risks a section with nothing in it depending on which page you're on.
        $active = $tasks->filter(fn ($t) => \App\Models\Task::STAGES[$t->status][2] ?? true);
        $closed = $tasks->filter(fn ($t) => ! (\App\Models\Task::STAGES[$t->status][2] ?? true));
    @endphp

    <x-cc.header eyebrow="Task Command" title="Tasks" subtitle="Everything on the to-do list across events and projects." />

    <div class="mt-5 grid gap-4 lg:grid-cols-[1fr_260px]">
        <x-tasks.stage-flow :stages="$stages" />

        <div class="flex items-center gap-4 rounded-lg border border-line bg-white p-5">
            <div class="ccx-ring h-[72px] w-[72px] shrink-0" style="--ccx-ring: var(--color-success); --ccx-ring-pct: {{ $donePct }}%">
                <span class="ccx-ring-value !text-[16px]">{{ $donePct }}%</span>
            </div>
            <div class="min-w-0">
                <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Completion</p>
                <p class="mt-1 text-[13px] font-semibold text-ink">{{ $closedCount }} of {{ $counts->sum() }} closed</p>
                <p class="mt-0.5 text-[11.5px] text-muted">Done + Approved</p>
            </div>
        </div>
    </div>

    <div class="mt-5 space-y-4">
        <div class="overflow-hidden rounded-lg border border-line bg-white">
            <div class="flex items-center gap-2 border-b border-line bg-page px-5 py-3">
                <h2 class="text-[13px] font-bold text-ink">Active</h2>
                <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-bold tabular-nums text-muted">{{ $active->count() }}</span>
            </div>
            <div class="divide-y divide-line">
                @forelse ($active as $task)
                    <x-tasks.task-row :task="$task" />
                @empty
                    <p class="px-5 py-10 text-center text-[13px] text-muted">Nothing active on this page.</p>
                @endforelse
            </div>
        </div>

        @if ($closed->isNotEmpty())
            <div class="overflow-hidden rounded-lg border border-line bg-white">
                <div class="flex items-center gap-2 border-b border-line bg-page px-5 py-3">
                    <h2 class="text-[13px] font-bold text-ink">Closed</h2>
                    <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-bold tabular-nums text-muted">{{ $closed->count() }}</span>
                </div>
                <div class="divide-y divide-line">
                    @foreach ($closed as $task)
                        <x-tasks.task-row :task="$task" />
                    @endforeach
                </div>
            </div>
        @endif

        @if ($tasks->isEmpty())
            <div class="rounded-lg border border-line bg-white px-5 py-12 text-center text-[13px] text-muted">No tasks yet.</div>
        @endif
    </div>

    <div class="mt-4">{{ $tasks->links() }}</div>
</x-layouts.app>
