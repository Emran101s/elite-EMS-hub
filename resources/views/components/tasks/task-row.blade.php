@props(['task'])

@php
    [$label, $hex] = \App\Models\Task::STAGES[$task->status] ?? ['—', '#94A3B8'];
    $overdue = $task->due_on && $task->due_on->isPast() && ! in_array($task->status, ['done', 'approved', 'cancelled'], true);
    $priorityTone = match ($task->priority) {
        'urgent' => 'bg-danger-soft text-danger-ink',
        'high' => 'bg-warning-soft text-warning-ink',
        default => null,
    };
@endphp

<div class="relative flex items-center gap-4 px-5 py-3.5">
    <span class="absolute inset-y-2.5 left-0 w-[3px] rounded-full" style="background: {{ $hex }}"></span>

    <x-user-avatar :user="$task->assignee" size="h-8 w-8" text="text-[11px]" />

    <div class="min-w-0 flex-1">
        <p class="truncate text-[13.5px] font-semibold text-ink">{{ $task->title }}</p>
        <p class="mt-0.5 truncate text-[11.5px] text-muted">
            @if ($task->event){{ $task->event->name }}@endif
            @if ($task->assignee) · {{ $task->assignee->name }}@endif
        </p>
    </div>

    @if ($priorityTone)
        <span class="hidden shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $priorityTone }} sm:inline-flex">{{ $task->priority }}</span>
    @endif

    <span class="hidden w-20 shrink-0 text-right text-[11.5px] font-semibold sm:block {{ $overdue ? 'text-danger-ink' : 'text-muted' }}">
        {{ $task->due_on?->format('j M') ?? '—' }}
    </span>

    <span class="hidden shrink-0 rounded-full px-2.5 py-1 text-[10.5px] font-bold sm:inline-flex" style="background: color-mix(in srgb, {{ $hex }} 16%, white); color: color-mix(in srgb, {{ $hex }} 65%, black);">
        {{ $label }}
    </span>
</div>
