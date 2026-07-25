@props([
    // items: [['k'=>'Participants','v'=>'650','f'=>'new this week','delta'=>['dir'=>'up','text'=>'12'],'tone'=>'vital'], …]
    'items' => [],
    'cta' => null,       // slot-like: pass a <x-slot:cta> for the trailing button
])
{{-- Chrome, not a surface: this bar stays dark in both themes, so everything
     inside it uses the --chrome-ink family and the -lit signal values. --}}
<div {{ $attributes->merge(['class' => 'o-kpistrip']) }}>
    @foreach ($items as $item)
        @php
            $tone = ($item['tone'] ?? null)
                ? (\App\Support\Tone::tryFrom($item['tone']) ?? null)
                : null;
        @endphp
        <div class="o-kpi">
            <div>
                <div class="o-kpi__k">{{ $item['k'] ?? '' }}</div>
                <div class="o-kpi__v" @if ($tone) style="color:{{ $tone->lit() }}" @endif>{{ $item['v'] ?? '' }}</div>
                @if (! empty($item['f']) || ! empty($item['delta']))
                    <div class="o-kpi__f">
                        @if (! empty($item['delta']))
                            @php $up = ($item['delta']['dir'] ?? 'up') === 'up'; @endphp
                            <span class="o-delta" style="color:{{ $up ? 'var(--vital-lit)' : 'var(--critical-lit)' }}">{{ $up ? '▲' : '▼' }} {{ $item['delta']['text'] ?? '' }}</span>
                        @endif
                        {{ $item['f'] ?? '' }}
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    @isset($cta)
        <div class="o-kpi o-kpi--cta">{{ $cta }}</div>
    @endisset
</div>
