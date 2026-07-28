@props([
    // [label, value, icon, pct (0–100 or null), toneClass, hint?, valueClass?]
    'stats' => [],
])

{{--
    The light counterpart to x-module-head.

    These figures used to be five separate cards, each with its own border,
    shadow and 16px of padding, holding one number apiece. Five cards is five
    borders and four gaps to say what one row says — the height of a card
    spent on chrome rather than on anything you can read. One card, divided,
    is the same information in roughly a third of the space.
--}}

@php
    // Written out rather than interpolated: a class name built at runtime is
    // never in the source Tailwind scans, so it would simply not exist.
    $columns = [
        2 => '2xl:grid-cols-2', 3 => '2xl:grid-cols-3', 4 => '2xl:grid-cols-4',
        5 => '2xl:grid-cols-5', 6 => '2xl:grid-cols-6',
    ][min(6, max(2, count($stats)))];
@endphp

<div {{ $attributes->merge(['class' => 'card overflow-hidden']) }}>
    <div class="grid grid-cols-2 divide-x divide-line sm:grid-cols-3 {{ $columns }}">
        @foreach ($stats as $stat)
            @php
                [$label, $value, $icon, $pct, $tone] = array_pad(array_slice($stat, 0, 5), 5, null);
                $hint = $stat[5] ?? null;
                $valueClass = $stat[6] ?? 'text-navy-900';
            @endphp
            <div class="min-w-0 px-3.5 py-2.5 transition hover:bg-page/50">
                <div class="flex items-center gap-1.5">
                    @if ($icon)
                        <x-icon :name="$icon" class="h-3 w-3 shrink-0 text-navy-300" />
                    @endif
                    <p class="eyebrow truncate">{{ $label }}</p>
                </div>

                <p class="pf mt-1 truncate text-[22px] font-bold leading-none {{ $valueClass }}">{{ $value }}</p>

                @if ($pct !== null)
                    {{-- One thin line rather than fourteen segments: it says the
                         same thing and leaves the number room to breathe. --}}
                    <div class="mt-2 h-[3px] overflow-hidden rounded-full bg-navy-50">
                        <div class="h-full rounded-full {{ $tone ?: 'bg-navy-300' }}" style="width: {{ max(0, min(100, (int) $pct)) }}%"></div>
                    </div>
                @endif

                @if ($hint)
                    <p class="mt-1.5 truncate text-[10.5px] text-muted">{{ $hint }}</p>
                @endif
            </div>
        @endforeach
    </div>
</div>
