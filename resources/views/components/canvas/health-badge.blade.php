@props(['score' => 0, 'size' => 'md', 'label' => true])
@php
    // One scale for the whole platform: 90+ Excellent, 70–89 Good, 50–69 Watch, <50 At Risk.
    $band = \App\Support\CommandCanvasData::band((int) $score);
    $tones = [
        'ok'   => ['text-cc-ok', 'border-cc-ok'],
        'info' => ['text-cc-info', 'border-cc-info'],
        'warn' => ['text-cc-warn', 'border-cc-warn'],
        'risk' => ['text-cc-risk', 'border-cc-risk'],
    ];
    [$text, $border] = $tones[$band['tone']];
    $box = ['sm' => 'h-11 w-10 text-[11px]', 'md' => 'h-14 w-[52px] text-[15px]', 'lg' => 'h-[70px] w-16 text-[19px]'][$size];
@endphp
<span {{ $attributes->merge(['class' => 'inline-grid justify-items-center gap-1']) }}>
    <span class="relative grid {{ $box }} place-items-center font-extrabold {{ $text }}">
        <span class="cc-hex-flat absolute inset-0 border-2 {{ $border }} bg-white"></span>
        <span class="relative">{{ (int) $score }}</span>
    </span>
    @if ($label)
        <span class="text-[9.5px] font-bold {{ $text }}">{{ $band['label'] }}</span>
    @endif
</span>
