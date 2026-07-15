<x-layouts.app title="Tasks" subtitle="Everything on the to-do list across events and projects.">
    {{-- ══ Task status strip ══ --}}
    <div class="strip-dark mb-5 px-6 py-5">
        <div class="pointer-events-none absolute -right-8 -top-16 h-48 w-48 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.30),transparent_70%)]"></div>

        <div class="relative flex flex-wrap items-center gap-x-5 gap-y-5">
            @php
                $tones = [
                    'completed' => ['Completed', 'text-emerald-300', 'bg-emerald-400'],
                    'in_progress' => ['In Progress', 'text-gold-300', 'bg-gold-400'],
                    'pending' => ['Pending', 'text-white', 'bg-white/40'],
                ];
                $totalTasks = max(array_sum($counts), 1);
            @endphp
            @foreach ($counts as $status => $count)
                @php [$label, $tone, $dot] = $tones[$status] ?? [str($status)->headline(), 'text-white', 'bg-white/40']; @endphp
                @if (! $loop->first)
                    <span class="hidden h-11 w-px bg-white/10 sm:block" aria-hidden="true"></span>
                @endif
                <div class="min-w-[110px]">
                    <p class="flex items-center gap-1.5 text-[0.48rem] font-bold uppercase tracking-[0.16em] text-gold-300/80">
                        <span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span> {{ $label }}
                    </p>
                    <p class="pf mt-1 text-[26px] font-bold leading-none {{ $tone }}">{{ $count }}</p>
                    <p class="mt-1 text-[0.6rem] text-white/40">{{ round($count / $totalTasks * 100) }}% of all tasks</p>
                </div>
            @endforeach

            <div class="ml-auto min-w-[180px] flex-1">
                <div class="mb-1.5 flex items-baseline justify-between">
                    <span class="text-[0.48rem] font-bold uppercase tracking-[0.16em] text-gold-300/80">Completion</span>
                    <span class="text-xs font-bold text-white">{{ round(($counts['completed'] ?? 0) / $totalTasks * 100) }}%</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-white/15">
                    <div class="h-full rounded-full bg-emerald-400" style="width: {{ round(($counts['completed'] ?? 0) / $totalTasks * 100) }}%"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card divide-y divide-line">
        @forelse ($tasks as $task)
            <div class="flex items-center gap-4 px-6 py-4">
                <span @class([
                        'h-2.5 w-2.5 shrink-0 rounded-full',
                        'bg-track' => $task->status === 'completed',
                        'bg-warn' => $task->status === 'in_progress',
                        'bg-navy-200' => $task->status === 'pending',
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
                <p class="hidden w-24 text-right text-xs text-muted sm:block">{{ $task->due_on?->format('M j') }}</p>
                <x-status-badge :status="$task->status" class="hidden sm:inline-flex" />
            </div>
        @empty
            <p class="px-6 py-12 text-center text-sm text-muted">No tasks yet.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $tasks->links() }}</div>
</x-layouts.app>
