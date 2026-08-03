<x-layouts.app title="Tasks" subtitle="Everything on the to-do list across events and projects.">
    {{-- ══ Task status strip ══
         This was the platform's last dark slab. Every other page opens with
         the same divided card of figures, and a page that opens differently
         reads as a page from a different product. ══ --}}
    @php
        $totalTasks = max($counts->sum(), 1);
        $donePct = (int) round((($counts['done'] ?? 0) + ($counts['approved'] ?? 0)) / $totalTasks * 100);

        // The tones follow the model's own stage colours, so a new status can
        // never arrive here wearing the wrong one.
        $stageTone = [
            'todo' => 'navy', 'doing' => 'blue', 'review' => 'gold',
            'approved' => 'green', 'done' => 'green', 'cancelled' => 'red',
        ];
    @endphp

    <x-figure-strip class="mb-4" :figures="$counts->filter(fn ($c) => $c > 0)
        ->map(fn ($count, $status) => [
            'label' => \App\Models\Task::STAGES[$status][0] ?? str($status)->headline()->toString(),
            'note' => round($count / $totalTasks * 100) . '% of the board',
            'value' => $count,
            'icon' => 'clipboard',
            'tone' => $stageTone[$status] ?? 'blue',
            'href' => route('tasks.index', ['status' => $status]),
        ])->values()->all()" />

    <div class="card mb-4 flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3">
        <span class="eyebrow">Completion</span>
        <div class="h-2 min-w-[180px] flex-1 overflow-hidden rounded-full bg-navy-50">
            <div class="h-full rounded-full bg-emerald-500" style="width: {{ $donePct }}%"></div>
        </div>
        <span class="pf text-[15px] font-black text-navy-950">{{ $donePct }}%</span>
        <span class="text-[11.5px] text-muted">{{ ($counts['done'] ?? 0) + ($counts['approved'] ?? 0) }} of {{ $counts->sum() }} closed</span>
    </div>

    <div class="card divide-y divide-line">
        @forelse ($tasks as $task)
            <div class="flex items-center gap-4 px-6 py-4">
                <span @class([
                        'h-2.5 w-2.5 shrink-0 rounded-full',
                        'bg-track' => $task->status === 'done',
                        'bg-warn' => $task->status === 'doing',
                        'bg-navy-200' => $task->status === 'todo',
                    ])></span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-navy-900">{{ $task->title }}</p>
                    <p class="mt-0.5 text-xs text-muted">
                        @if ($task->event) {{ $task->event->name }} @endif
                        @if ($task->assignee) · {{ $task->assignee->name }} @endif
                    </p>
                </div>
                @if ($task->priority === 'urgent' || $task->priority === 'high')
                    <span class="rounded-full bg-risk/10 px-2 py-0.5 text-[0.65rem] font-semibold uppercase text-red-700">{{ $task->priority }}</span>
                @endif
                <p class="hidden w-24 text-right text-xs text-muted sm:block">{{ $task->due_on?->format('j M') }}</p>
                <x-status-badge :status="$task->status" class="hidden sm:inline-flex" />
            </div>
        @empty
            <p class="px-6 py-12 text-center text-sm text-muted">No tasks yet.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $tasks->links() }}</div>
</x-layouts.app>
