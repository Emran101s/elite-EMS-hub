@props([
    'options' => [],     // ['cards' => 'Cards', 'list' => 'List', …]
    'selected' => null,
])
<div {{ $attributes->merge(['class' => 'o-seg']) }} role="tablist">
    @foreach ($options as $value => $label)
        <button type="button" role="tab" aria-selected="{{ $value == $selected ? 'true' : 'false' }}"
                @if (! $attributes->has('wire:click')) value="{{ $value }}" @endif>{{ $label }}</button>
    @endforeach
</div>
