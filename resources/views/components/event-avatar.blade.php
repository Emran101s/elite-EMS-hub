@props(['event' => null, 'avatar' => null, 'size' => 'md', 'ring' => true, 'percent' => null, 'group' => null])

@php
    // The single way avatars render anywhere in the platform.
    // Priority: uploaded image → built-in SVG scene for the slug → neutral placeholder.
    $avatar ??= $event?->avatar;

    $frame = match ($size) {
        'sm' => 'h-10 w-14 rounded-lg',
        'lg' => 'h-28 w-40 rounded-2xl',
        'xl' => 'h-36 w-52 rounded-2xl',
        default => 'h-14 w-20 rounded-xl',
    };
@endphp

<span {{ $attributes->merge(['class' => 'relative inline-block shrink-0']) }}
      @if ($avatar) data-avatar="{{ $avatar->slug }}" @endif>
    <span class="{{ $frame }} block overflow-hidden {{ $avatar?->image_path ? 'bg-transparent' : 'bg-navy-50 ring-1 ring-line' }}">
        @if ($avatar?->image_path)
            @php $src = in_array($size, ['sm', 'md']) && $avatar->thumbnail_path ? $avatar->thumbnail_path : $avatar->image_path; @endphp
            <img src="{{ asset($src) }}" alt="{{ $avatar->name }}" loading="lazy" class="h-full w-full object-contain">
        @elseif ($avatar && view()->exists('components.avatars.' . $avatar->slug))
            <x-dynamic-component :component="'avatars.' . $avatar->slug" class="h-full w-full" />
        @else
            <span class="flex h-full w-full items-center justify-center text-navy-300">
                <x-icon name="calendar" class="h-5 w-5" />
            </span>
        @endif
    </span>

    @if ($ring && $event)
        @php
            $ringPercent = $percent ?? $event->progress;
            $ringGroup = $group ?? $event->healthGroup();
        @endphp
        <span class="absolute -bottom-2 -right-2 rounded-full bg-white p-0.5 shadow ring-1 ring-line"
              title="{{ $ringPercent }}% · {{ str($event->status)->replace('_', ' ')->title() }}">
            <x-health-ring :percent="$ringPercent" :group="$ringGroup"
                           :label="$size === 'lg' || $size === 'xl'"
                           :size="$size === 'lg' || $size === 'xl' ? 'h-10 w-10' : 'h-6 w-6'" />
        </span>
    @endif
</span>
