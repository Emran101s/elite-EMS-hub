@php
    $groups = [
        'pending' => $event->tasks->where('status', 'pending'),
        'in_progress' => $event->tasks->where('status', 'in_progress'),
        'completed' => $event->tasks->where('status', 'completed'),
    ];
@endphp

<div class="grid gap-5 lg:grid-cols-3">
    @foreach ($groups as $status => $tasks)
        <div class="card p-4">
            <div class="mb-3 flex items-center justify-between px-1">
                <x-status-badge :status="$status" />
                <span class="text-xs font-bold text-navy-900">{{ $tasks->count() }}</span>
            </div>
            <div class="space-y-2.5">
                @forelse ($tasks->sortBy('due_on')->take(8) as $task)
                    <div class="rounded-xl border border-line bg-page/50 px-3.5 py-3">
                        <p class="text-xs font-semibold text-navy-900">{{ $task->title }}</p>
                        <div class="mt-1.5 flex items-center justify-between">
                            <span class="text-[0.65rem] text-muted">{{ $task->assignee?->name ?? 'Unassigned' }}</span>
                            <span class="flex items-center gap-2">
                                @if (in_array($task->priority, ['urgent', 'high']))
                                    <span class="rounded-full bg-risk/10 px-1.5 py-0.5 text-[0.55rem] font-bold uppercase text-red-700">{{ $task->priority }}</span>
                                @endif
                                <span class="text-[0.65rem] font-semibold {{ $task->due_on?->isPast() && $status !== 'completed' ? 'text-risk' : 'text-muted' }}">
                                    {{ $task->due_on?->format('M j') }}
                                </span>
                            </span>
                        </div>
                    </div>
                @empty
                    <p class="px-1 text-xs text-muted">Nothing here.</p>
                @endforelse
                @if ($tasks->count() > 8)
                    <p class="px-1 text-[0.65rem] text-muted">+ {{ $tasks->count() - 8 }} more</p>
                @endif
            </div>
        </div>
    @endforeach
</div>
<p class="mt-4 text-xs text-muted">Board is read-only in this build — task create/edit, checklists, approvals workflow and auto-templates per event type land next. Full task system: <a class="font-semibold text-gold-600" href="{{ route('tasks.index') }}">Tasks module</a>.</p>
