@props(['record', 'fallback' => 'Untitled', 'muted' => false])

@php
    $name = trim((string) ($record->title ?? ''));
    $missing = method_exists($record, 'incomplete') ? $record->incomplete() : [];
@endphp

{{--
    A record's name, and whether it is finished being made.

    "Untitled task · Unassigned · —" rendered exactly like a real task makes a
    board look broken and the design take the blame. Here an unnamed record is
    set in italic grey — visibly a placeholder, not a thing — and anything still
    missing its essentials carries a small amber dot that says which.

    Quiet on purpose. A board with many incomplete records should read as a
    board with work to do, not as an error state.
--}}
<span {{ $attributes->merge(['class' => 'inline-flex min-w-0 items-baseline gap-1.5']) }}>
    <span @class(['min-w-0 truncate', 'italic font-medium text-eo-muted' => $name === '', 'line-through text-eo-muted' => $muted])>
        {{ $name !== '' ? $name : $fallback }}
    </span>

    @if ($missing !== [])
        <span class="mt-px inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-eo-warn"
              title="Still needs {{ \Illuminate\Support\Arr::join($missing, ', ', ' and ') }}"
              aria-label="Incomplete — still needs {{ \Illuminate\Support\Arr::join($missing, ', ', ' and ') }}"></span>
    @endif
</span>
