<div>
    <div class="mb-4 flex items-center justify-between">
        <p class="text-xs text-muted">{{ $event->tasks->count() }} tasks · click a card's actions to move it through the board</p>
        <button type="button" wire:click="$toggle('showForm')" class="btn-gold h-10 px-4 text-xs">＋ Add Task</button>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="card mb-5 grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-5">
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium text-navy-800" for="t-title">Task title</label>
                <input id="t-title" type="text" wire:model="title" class="input h-10 text-sm" placeholder="e.g. Confirm keynote AV rehearsal">
                @error('title') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-navy-800" for="t-assignee">Assignee</label>
                <select id="t-assignee" wire:model="assignee_id" class="input h-10 text-sm">
                    <option value="">Unassigned</option>
                    @foreach ($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-navy-800" for="t-priority">Priority</label>
                <select id="t-priority" wire:model="priority" class="input h-10 text-sm">
                    @foreach (\App\Models\Task::PRIORITIES as $priorityOption)<option value="{{ $priorityOption }}">{{ str($priorityOption)->title() }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-navy-800" for="t-due">Due date</label>
                <input id="t-due" type="date" wire:model="due_on" class="input h-10 text-sm">
                @error('due_on') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-end justify-end gap-2 sm:col-span-2 xl:col-span-5">
                <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                <button type="submit" class="btn-navy h-10 px-5 text-xs">Save Task</button>
            </div>
        </form>
    @endif

    <div class="grid gap-5 lg:grid-cols-3">
        @foreach ($groups as $status => $tasks)
            <div class="card p-4">
                <div class="mb-3 flex items-center justify-between px-1">
                    <x-status-badge :status="$status" />
                    <span class="text-xs font-bold text-navy-900">{{ $tasks->count() }}</span>
                </div>
                <div class="space-y-2.5">
                    @forelse ($tasks->take(10) as $task)
                        <div class="group/task rounded-xl border border-line bg-page/50 px-3.5 py-3">
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
                            <div class="mt-2 flex gap-1.5 opacity-0 transition group-hover/task:opacity-100">
                                @if ($status !== 'in_progress' && $status !== 'completed')
                                    <button type="button" wire:click="setStatus({{ $task->id }}, 'in_progress')" class="rounded-lg bg-warn/10 px-2 py-1 text-[0.6rem] font-bold text-amber-700 hover:bg-warn/20">▶ Start</button>
                                @endif
                                @if ($status !== 'completed')
                                    <button type="button" wire:click="setStatus({{ $task->id }}, 'completed')" class="rounded-lg bg-track/10 px-2 py-1 text-[0.6rem] font-bold text-emerald-700 hover:bg-track/20">✓ Complete</button>
                                @else
                                    <button type="button" wire:click="setStatus({{ $task->id }}, 'pending')" class="rounded-lg bg-navy-50 px-2 py-1 text-[0.6rem] font-bold text-navy-600 hover:bg-navy-100">↺ Reopen</button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="px-1 text-xs text-muted">Nothing here.</p>
                    @endforelse
                    @if ($tasks->count() > 10)
                        <p class="px-1 text-[0.65rem] text-muted">+ {{ $tasks->count() - 10 }} more</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
