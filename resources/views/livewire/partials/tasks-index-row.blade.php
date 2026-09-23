{{-- One row of the global Tasks list. Reads $canWrite, $people, $control and
     $selected from the including view. Read-only people get the same row the
     old report drew; writers get the checkbox and the two inline controls. --}}
@php
    $overdue = $task->isOverdue();
    $priorityTone = match ($task->priority) {
        'urgent' => 'bg-danger-soft text-danger-ink',
        'high' => 'bg-warning-soft text-warning-ink',
        default => null,
    };
@endphp

<div wire:key="task-{{ $task->id }}" class="relative flex items-center gap-3 px-4 py-2.5 {{ in_array($task->id, array_map('intval', $selected), true) ? 'bg-page' : '' }}">
    <span class="absolute inset-y-2.5 start-0 w-[3px] rounded-full" style="background: {{ $task->stageHex() }}"></span>

    @if ($canWrite)
        <input type="checkbox" wire:click="toggleSelect({{ $task->id }})" @checked(in_array($task->id, array_map('intval', $selected), true))
               aria-label="Select “{{ $task->title }}”" class="h-4 w-4 shrink-0 rounded border-line text-navy-900">
    @endif

    <x-user-avatar :user="$task->assignee" size="h-8 w-8" text="text-[11px]" />

    <div class="min-w-0 flex-1">
        <x-record-title :record="$task" fallback="Untitled task" class="max-w-full text-[13.5px] font-semibold text-ink" />
        <p class="mt-0.5 truncate text-[11.5px] text-muted">
            @if ($task->event)
                <a href="{{ route('events.hub', [$task->event, 'tab' => 'tasks']) }}" wire:navigate class="hover:text-ink hover:underline">{{ $task->event->name }}</a>
            @endif
            @unless ($canWrite)
                @if ($task->assignee) · {{ $task->assignee->name }}@endif
            @endunless
        </p>
    </div>

    @if ($priorityTone)
        <span class="hidden shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $priorityTone }} sm:inline-flex">{{ $task->priorityLabel() }}</span>
    @endif

    <span class="hidden w-24 shrink-0 text-end text-[11.5px] font-semibold sm:block {{ $overdue ? 'text-danger-ink' : 'text-muted' }}">
        <x-date :value="$task->due_on" />
    </span>

    @if ($canWrite)
        <label class="sr-only" for="owner-{{ $task->id }}">Owner of “{{ $task->title }}”</label>
        <select id="owner-{{ $task->id }}" x-on:change="$wire.assign({{ $task->id }}, $event.target.value)"
                class="{{ $control }} hidden h-8 w-36 md:block">
            <option value="" @selected($task->assignee_id === null)>Unassigned</option>
            @foreach ($people as $person)
                <option value="{{ $person->id }}" @selected($task->assignee_id === $person->id)>{{ $person->name }}</option>
            @endforeach
        </select>

        <label class="sr-only" for="status-{{ $task->id }}">Status of “{{ $task->title }}”</label>
        <select id="status-{{ $task->id }}" x-on:change="$wire.setStatus({{ $task->id }}, $event.target.value)"
                class="{{ $control }} h-8 w-32 font-semibold" style="color: color-mix(in srgb, {{ $task->stageHex() }} 65%, black)">
            @foreach (\App\Models\Task::STAGES as $key => $stage)
                <option value="{{ $key }}" @selected($task->status === $key)>{{ \App\Support\Workflow::label('task_stage', $key) }}</option>
            @endforeach
        </select>
    @else
        <span class="hidden shrink-0 rounded-full px-2.5 py-1 text-[10.5px] font-bold sm:inline-flex"
              style="background: color-mix(in srgb, {{ $task->stageHex() }} 16%, white); color: color-mix(in srgb, {{ $task->stageHex() }} 65%, black);">
            {{ $task->stageLabel() }}
        </span>
    @endif
</div>
