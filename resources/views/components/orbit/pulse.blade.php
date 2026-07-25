@props([
    // metrics: [['label'=>'Tasks on time','value'=>'88%','tone'=>'vital'], …]
    'metrics' => [],
])
<ul {{ $attributes->merge(['class' => 'o-pulse']) }}>
    @foreach ($metrics as $m)
        @php $t = \App\Support\Tone::tryFrom($m['tone'] ?? 'ion') ?? \App\Support\Tone::Ion; @endphp
        <li><i style="background:{{ $t->lit() }}"></i>{{ $m['label'] ?? '' }}<b>{{ $m['value'] ?? '' }}</b></li>
    @endforeach
</ul>
