@props([
    'title',
    'hint' => null,
    'icon' => null,
])

{{--
    One empty state. Replaces 39 ad-hoc centred blocks. The `actions` slot keeps
    the "nothing here yet — here's how to start" pairing consistent, which is the
    part that was most often missing.
--}}
<div {{ $attributes->merge(['class' => 'card flex flex-col items-center px-6 py-14 text-center']) }}>
    @if ($icon)
        <span class="mb-3 flex h-11 w-11 items-center justify-center rounded-lg bg-navy-50 text-navy-500">
            <x-icon :name="$icon" class="h-5 w-5" />
        </span>
    @endif

    <p class="text-sm font-semibold text-navy-900">{{ $title }}</p>

    @if ($hint)
        <p class="mx-auto mt-1 max-w-md text-xs leading-relaxed text-muted">{{ $hint }}</p>
    @endif

    @isset($actions)
        <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
