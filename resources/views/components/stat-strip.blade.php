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
    // Columns are driven by the stat COUNT, not a fixed sm:grid-cols-3 — with
    // only two stats a three-column grid left the third cell empty, the exact
    // "wasted gutter" this row exists to avoid. Written out (not interpolated)
    // so Tailwind can see the class names at build time.
    $n = min(6, max(1, count($stats)));
    $columns = [
        1 => 'grid-cols-1',
        2 => 'grid-cols-2',
        3 => 'grid-cols-3',
        4 => 'grid-cols-2 lg:grid-cols-4',
        5 => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5',
        6 => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-6',
    ][$n];
@endphp

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-lg border border-line bg-white shadow-raise']) }}>
    <div class="grid divide-x divide-line {{ $columns }}">
        @foreach ($stats as $stat)
            @php
                [$label, $value, $icon, $pct, $tone] = array_pad(array_slice($stat, 0, 5), 5, null);
                $hint = $stat[5] ?? null;
                $valueClass = $stat[6] ?? 'text-ink';
            @endphp
            <div class="min-w-0 px-4 py-3 transition hover:bg-page/60">
                <div class="flex items-center gap-1.5">
                    @if ($icon)
                        <span class="grid h-5 w-5 shrink-0 place-items-center rounded-md bg-page text-navy-400">
                            <x-icon :name="$icon" class="h-3 w-3" />
                        </span>
                    @endif
                    <p class="eyebrow truncate">{{ $label }}</p>
                </div>

                <p class="mt-1.5 truncate text-[22px] font-bold leading-none tabular-nums {{ $valueClass }}">{{ $value }}</p>

                @if ($pct !== null)
                    {{-- One thin line rather than fourteen segments: it says the
                         same thing and leaves the number room to breathe. --}}
                    <div class="mt-2 h-[3px] overflow-hidden rounded-full bg-page">
                        <div class="h-full rounded-full {{ $tone ?: 'bg-gold-500' }}" style="width: {{ max(0, min(100, (int) $pct)) }}%"></div>
                    </div>
                @endif

                @if ($hint)
                    <p class="mt-1.5 truncate text-[10.5px] text-muted">{{ $hint }}</p>
                @endif
            </div>
        @endforeach
    </div>
</div>
