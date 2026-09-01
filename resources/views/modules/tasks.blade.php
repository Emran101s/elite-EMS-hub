<x-layouts.app title="Tasks" :hide-title-row="true">
    @php
        // Completion counts what the MODEL calls finished, not what this page
        // used to guess. Task::STAGES marks 'approved' as still open — it is
        // approved to proceed, not delivered — so counting it as closed here
        // put two definitions of "done" on one screen: the ring called a task
        // finished while the Active list below still listed it.
        // Cancelled work is neither done nor outstanding, so it leaves the
        // denominator rather than inflating progress.
        $doneCount = $counts['done'] ?? 0;
        $cancelledCount = $counts['cancelled'] ?? 0;
        $inScope = max($counts->sum() - $cancelledCount, 1);
        $donePct = (int) round($doneCount / $inScope * 100);

        $stages = collect(\App\Models\Task::STAGES)->mapWithKeys(fn ($stage, $key) => [
            $key => ['label' => $stage[0], 'hex' => $stage[1], 'count' => $counts[$key] ?? 0],
        ]);

        // Active/Closed, not six near-empty buckets — the page shows 25 tasks
        // at a time, and a finer split than the model's own open/closed flag
        // risks a section with nothing in it depending on which page you're on.
        $active = $tasks->filter(fn ($t) => \App\Models\Task::STAGES[$t->status][2] ?? true);
        $closed = $tasks->filter(fn ($t) => ! (\App\Models\Task::STAGES[$t->status][2] ?? true));

        // The badges count the whole list, not this page of it. $tasks is one
        // page of 25, so filtering it gave "Active 24" on a board with 40
        // active tasks — the page slice wearing the total's clothes, on the
        // one screen whose entire job is telling you how much is outstanding.
        // $counts already holds the real per-status totals; scope it to the
        // status filter so a filtered view still adds up.
        $scoped = $statusFilter ? $counts->only([$statusFilter]) : $counts;
        $activeTotal = $scoped->filter(fn ($c, $k) => \App\Models\Task::STAGES[$k][2] ?? true)->sum();
        $closedTotal = $scoped->sum() - $activeTotal;
    @endphp

    <x-cc.header eyebrow="Task Command" title="Tasks" subtitle="Everything on the to-do list across events and projects." />

    <div class="mt-4 grid gap-3 lg:grid-cols-[1fr_260px]">
        <x-tasks.stage-flow :stages="$stages" />

        <div class="flex items-center gap-3.5 rounded-lg border border-line bg-white p-3.5">
            <div class="ccx-ring h-[72px] w-[72px] shrink-0" style="--ccx-ring: var(--color-success); --ccx-ring-pct: {{ $donePct }}%">
                <span class="ccx-ring-value !text-[16px]">{{ $donePct }}%</span>
            </div>
            <div class="min-w-0">
                <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Completion</p>
                <p class="mt-1 text-[13px] font-semibold text-ink">{{ $doneCount }} of {{ $inScope }} done</p>
                <p class="mt-0.5 text-[11.5px] text-muted">
                    @if ($cancelledCount)
                        {{ $cancelledCount }} cancelled, not counted
                    @else
                        Delivered, not just approved
                    @endif
                </p>
            </div>
        </div>
    </div>

    <div class="mt-4 space-y-3">
        <div class="overflow-hidden rounded-lg border border-line bg-white">
            <div class="flex items-center gap-2 border-b border-line bg-page px-4 py-2.5">
                <h2 class="text-[13px] font-bold text-ink">Active</h2>
                <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-bold tabular-nums text-muted">{{ $activeTotal }}</span>
                {{-- The badge is the real total; the list is one page of it.
                     Say which is which rather than letting the two numbers
                     silently disagree. --}}
                @if ($active->count() < $activeTotal)
                    <span class="text-[10.5px] text-muted">showing {{ $active->count() }} on this page</span>
                @endif
            </div>
            <div class="divide-y divide-line">
                @forelse ($active as $task)
                    <x-tasks.task-row :task="$task" />
                @empty
                    <p class="px-4 py-7 text-center text-[13px] text-muted">Nothing active on this page.</p>
                @endforelse
            </div>
        </div>

        @if ($closed->isNotEmpty())
            <div class="overflow-hidden rounded-lg border border-line bg-white">
                <div class="flex items-center gap-2 border-b border-line bg-page px-4 py-2.5">
                    <h2 class="text-[13px] font-bold text-ink">Closed</h2>
                    <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-bold tabular-nums text-muted">{{ $closedTotal }}</span>
                    @if ($closed->count() < $closedTotal)
                        <span class="text-[10.5px] text-muted">showing {{ $closed->count() }} on this page</span>
                    @endif
                </div>
                <div class="divide-y divide-line">
                    @foreach ($closed as $task)
                        <x-tasks.task-row :task="$task" />
                    @endforeach
                </div>
            </div>
        @endif

        @if ($tasks->isEmpty())
            <div class="rounded-lg border border-line bg-white px-4 py-8 text-center text-[13px] text-muted">No tasks yet.</div>
        @endif
    </div>

    <div class="mt-4">{{ $tasks->links() }}</div>
</x-layouts.app>
