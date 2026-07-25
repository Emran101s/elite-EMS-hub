@props(['options' => [], 'selected' => null])
<div {{ $attributes->merge(['class' => 'o-tabs']) }} role="tablist">
    @foreach ($options as $value => $label)
        <button type="button" role="tab" aria-selected="{{ $value == $selected ? 'true' : 'false' }}" value="{{ $value }}">{{ $label }}</button>
    @endforeach
</div>
