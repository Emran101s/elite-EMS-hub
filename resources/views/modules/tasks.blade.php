<x-layouts.app title="Tasks" subtitle="Everything on the to-do list across events and projects.">
    <div class="mb-5 flex flex-wrap gap-3">
        @foreach ($counts as $status => $count)
            <span class="card inline-flex items-center gap-2 px-4 py-2 text-sm">
                <x-status-badge :status="$status" />
                <span class="font-bold text-navy-900">{{ $count }}</span>
            </span>
        @endforeach
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
