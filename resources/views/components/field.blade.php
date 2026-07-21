@props([
    'label' => null,
    'for' => null,
    'error' => null,
    'hint' => null,
])

{{--
    Label + control + error, in one place. Error text was previously written
    inline with three different colour/size combinations per form.
--}}
<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="field-label !mb-1 !text-eyebrow">{{ $label }}</label>
    @endif

    {{ $slot }}

    @if ($error)
        <p class="mt-1 text-micro font-semibold text-danger-ink">{{ $error }}</p>
    @elseif ($hint)
        <p class="mt-1 text-micro text-muted">{{ $hint }}</p>
    @endif
</div>
