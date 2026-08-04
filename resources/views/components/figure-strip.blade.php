@props([
    // [label, note, value, icon, tone, href?, trend? => [up, label]]
    'figures' => [],

    // One row instead of two, for pages where what is below the strip needs the
    // height more than the figures do — the events deck, whose card is sized
    // from whatever vertical room is left after this. Drops the note and the
    // trend, which are the two lines you do not read while choosing a view.
    'dense' => false,
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
        'blue' => ['bg-info', 'text-white'],
        'red' => ['bg-red-500', 'text-white'],
        'gold' => ['bg-gold-500', 'text-navy-950'],
        'violet' => ['bg-navy-700', 'text-white'],
    ];
    // Container queries, not breakpoints: this strip sits in a column with a
    // rail beside it on some pages and not on others, and a breakpoint answers
    // to the window rather than to the column it is actually in.
    $n = min(6, max(2, count($figures)));
    $columns = $dense
        // Dense drops a breakpoint step: without the note and the trend a cell
        // needs far less width, so six fit where five used to.
        ? [2 => '@lg/strip:grid-cols-2', 3 => '@lg/strip:grid-cols-3', 4 => '@xl/strip:grid-cols-4',
           5 => '@2xl/strip:grid-cols-5', 6 => '@3xl/strip:grid-cols-6'][$n]
        : [2 => '@2xl/strip:grid-cols-2', 3 => '@3xl/strip:grid-cols-3', 4 => '@4xl/strip:grid-cols-4',
           5 => '@5xl/strip:grid-cols-5', 6 => '@6xl/strip:grid-cols-6'][$n];
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
               @if ($dense && ($f['note'] ?? null)) title="{{ $f['note'] }}" @endif
               class="flex items-center transition {{ $dense ? 'gap-2.5 px-3 py-2.5' : 'gap-3.5 px-4 py-4' }} {{ ($f['href'] ?? null) ? 'hover:bg-page/60' : '' }}">
                <span class="grid shrink-0 place-items-center rounded-xl {{ $dense ? 'h-9 w-9' : 'h-11 w-11' }} {{ $fill }} {{ $ink }}">
                    <x-icon :name="$f['icon'] ?? 'chart'" class="{{ $dense ? 'h-4 w-4' : 'h-5 w-5' }}" />
                </span>

                <div class="min-w-0">
                    <p class="pf font-black leading-none text-navy-950 {{ $dense ? 'text-[20px]' : 'text-[26px]' }}">{{ $f['value'] }}</p>
                    <p class="truncate text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-500 {{ $dense ? 'mt-0.5' : 'mt-1' }}">{{ $f['label'] }}</p>
                    @if (! $dense && ($f['note'] ?? null))
                        <p class="truncate text-[10.5px] text-muted">{{ $f['note'] }}</p>
                    @endif
                </div>

                @unless ($dense)
                    @if ($f['trend'] ?? null)
                        <span @class([
                            'ms-auto shrink-0 self-start text-[11px] font-bold',
                            'text-emerald-600' => $f['trend']['up'],
                            'text-navy-400' => ! $f['trend']['up'],
                        ])>{{ $f['trend']['label'] }}</span>
                    @else
                        <span class="ms-auto shrink-0 self-start text-[11px] font-bold text-navy-200">—</span>
                    @endif
                @endunless
            </{{ $tag }}>
        @endforeach
    </div>
  </div>
</div>
