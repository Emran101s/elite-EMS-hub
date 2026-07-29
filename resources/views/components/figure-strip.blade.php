@props([
    // [label, note, value, icon, tone, href?, trend? => [up, label]]
    'figures' => [],
])

{{--
    The row of figures a page opens with.

    One card divided, not five cards in a row: five cards is five borders and
    four gaps spent on chrome rather than on anything you can read. Each figure
    gets a solid coloured tile, because five numbers side by side need something
    that tells them apart before you have read any of them.

    The second line on the right is the figure's own trend — but only where a
    record carries a date to measure one with. Where it does not, the slot holds
    an em dash rather than a number nobody counted.
--}}

@php
    // Written out, not interpolated: a class name built at runtime is never in
    // the source Tailwind scans, so it would simply not exist.
    $tones = [
        'navy' => ['bg-navy-950', 'text-gold-400'],
        'green' => ['bg-emerald-500', 'text-white'],
        'blue' => ['bg-blue-500', 'text-white'],
        'red' => ['bg-red-500', 'text-white'],
        'gold' => ['bg-gold-500', 'text-navy-950'],
        'violet' => ['bg-violet-500', 'text-white'],
    ];
    // Container queries, not breakpoints: this strip sits in a column with a
    // rail beside it on some pages and not on others, and a breakpoint answers
    // to the window rather than to the column it is actually in.
    $columns = [
        2 => '@2xl/strip:grid-cols-2', 3 => '@3xl/strip:grid-cols-3', 4 => '@4xl/strip:grid-cols-4',
        5 => '@5xl/strip:grid-cols-5', 6 => '@6xl/strip:grid-cols-6',
    ][min(6, max(2, count($figures)))];
@endphp

<div {{ $attributes->merge(['class' => '@container/strip']) }}>
  <div class="card overflow-hidden">
    <div class="grid grid-cols-2 divide-x divide-y divide-line @xl/strip:grid-cols-3 {{ $columns }} @5xl/strip:divide-y-0">
        @foreach ($figures as $f)
            @php
                [$fill, $ink] = $tones[$f['tone'] ?? 'blue'] ?? $tones['blue'];
                $tag = ($f['href'] ?? null) ? 'a' : 'div';
            @endphp
            <{{ $tag }} @if ($f['href'] ?? null) href="{{ $f['href'] }}" @endif
               class="flex items-center gap-3.5 px-4 py-4 transition {{ ($f['href'] ?? null) ? 'hover:bg-page/60' : '' }}">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl {{ $fill }} {{ $ink }}">
                    <x-icon :name="$f['icon'] ?? 'chart'" class="h-5 w-5" />
                </span>

                <div class="min-w-0">
                    <p class="pf text-[26px] font-black leading-none text-navy-950">{{ $f['value'] }}</p>
                    <p class="mt-1 truncate text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-500">{{ $f['label'] }}</p>
                    @if ($f['note'] ?? null)
                        <p class="truncate text-[10.5px] text-muted">{{ $f['note'] }}</p>
                    @endif
                </div>

                @if ($f['trend'] ?? null)
                    <span @class([
                        'ms-auto shrink-0 self-start text-[11px] font-bold',
                        'text-emerald-600' => $f['trend']['up'],
                        'text-navy-400' => ! $f['trend']['up'],
                    ])>{{ $f['trend']['label'] }}</span>
                @else
                    <span class="ms-auto shrink-0 self-start text-[11px] font-bold text-navy-200">—</span>
                @endif
            </{{ $tag }}>
        @endforeach
    </div>
  </div>
</div>
