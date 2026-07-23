@props(['event' => null, 'size' => 'md', 'ring' => true, 'percent' => null, 'group' => null])

@php
    // The single way an event's visual renders anywhere in the platform.
    // Priority: uploaded cover image → generated crest so every event has a mark.
    $frame = match ($size) {
        'sm' => 'h-10 w-14 rounded-lg',
        'lg' => 'h-28 w-40 rounded-2xl',
        'xl' => 'h-36 w-52 rounded-2xl',
        default => 'h-14 w-20 rounded-xl',
    };
@endphp

<span {{ $attributes->merge(['class' => 'relative inline-block shrink-0']) }}>
    <span class="{{ $frame }} block overflow-hidden {{ $event?->cover_path ? 'bg-transparent' : 'bg-navy-50 ring-1 ring-line' }}">
        @if ($event?->cover_path)
            <img src="{{ $event->coverUrl() }}" alt="{{ $event->name }}" loading="lazy" class="h-full w-full object-cover">
        @else
            <x-event-crest :event="$event" :name="$event?->name" :type="$event?->type" class="h-full w-full" />
        @endif
    </span>

    @if ($ring && $event)
        @php
            $ringPercent = $percent ?? $event->progress;
            $ringGroup = $group ?? $event->healthGroup();
        @endphp
        <span class="absolute -bottom-2 -right-2 rounded-full bg-white p-0.5 shadow ring-1 ring-line"
              title="{{ $ringPercent }}% · {{ str($event->stage)->replace('_', ' ')->title() }}">
            <x-health-ring :percent="$ringPercent" :group="$ringGroup"
                           :label="$size === 'lg' || $size === 'xl'"
                           :size="$size === 'lg' || $size === 'xl' ? 'h-10 w-10' : 'h-6 w-6'" />
        </span>
    @endif
</span>
