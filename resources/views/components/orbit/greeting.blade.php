@props(['name' => null, 'heading' => null])
{{-- Law: open with a sentence someone can act on, not a bare number. --}}
<div {{ $attributes->merge(['class' => 'o-greet']) }}>
    <div>
        <h1 class="o-greet__h">{{ $heading ?? 'Good morning, '.$name }}</h1>
        @isset($summary)<p class="o-greet__s">{{ $summary }}</p>@endisset
    </div>
    @isset($aside){{ $aside }}@endisset
</div>
