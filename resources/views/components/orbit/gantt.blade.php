@props([
    'scale' => [],       // ['Jul','Aug','Sep','Oct','Nov']
    // bands: [['label'=>'Planning','start'=>0,'end'=>40,'tone'=>'ion'], …] — start/end are percentages
    'bands' => [],
    'today' => null,     // percentage across the scale
])
<div {{ $attributes->merge(['class' => 'o-gantt']) }}>
    @if ($scale)
        <div class="o-gantt__scale">@foreach ($scale as $s)<span>{{ $s }}</span>@endforeach</div>
    @endif
    @foreach ($bands as $band)
        @php
            $t = \App\Support\Tone::tryFrom($band['tone'] ?? 'ion') ?? \App\Support\Tone::Ion;
            $start = (float) ($band['start'] ?? 0);
            $width = max(0, (float) ($band['end'] ?? 0) - $start);
        @endphp
        <div class="o-gantt__band">
            <div class="o-gantt__bar" style="left:{{ $start }}%;width:{{ $width }}%;background:{{ $t->tint() }};color:{{ $t->var() }};overflow:hidden;white-space:nowrap" title="{{ $band['label'] ?? '' }}">
                {{ $band['label'] ?? '' }}
                @if (! empty($band['note']))<span>{{ $band['note'] }}</span>@endif
            </div>
            @if ($today !== null && $loop->first)<div class="o-gantt__now" style="left:{{ $today }}%"></div>@endif
        </div>
    @endforeach
</div>
