@props([
    'label' => null,
    'type' => 'text',       // text | number | select | textarea
    'name' => null,
    'value' => null,
    'affix' => null,        // e.g. "JD" — sits before the input
    'help' => null,
    'placeholder' => null,
    'options' => [],        // select only: ['value' => 'Label']
])
@php $num = $type === 'number'; @endphp
<div {{ $attributes->only('class')->merge(['class' => 'o-field']) }}>
    @if ($label)<label class="o-label" @if ($name) for="{{ $name }}" @endif>{{ $label }}</label>@endif

    @if ($type === 'select')
        <select class="o-select" @if ($name) name="{{ $name }}" id="{{ $name }}" @endif {{ $attributes->except('class') }}>
            @foreach ($options as $v => $l)
                <option value="{{ $v }}" @selected((string) $v === (string) $value)>{{ $l }}</option>
            @endforeach
        </select>
    @elseif ($type === 'textarea')
        <textarea class="o-textarea" @if ($name) name="{{ $name }}" id="{{ $name }}" @endif
                  placeholder="{{ $placeholder }}" {{ $attributes->except('class') }}>{{ $value }}</textarea>
    @elseif ($affix)
        <div class="o-inputgroup">
            <span class="o-affix">{{ $affix }}</span>
            <input class="o-input{{ $num ? ' o-input--num' : '' }}" type="{{ $num ? 'number' : $type }}"
                   @if ($name) name="{{ $name }}" id="{{ $name }}" @endif
                   value="{{ $value }}" placeholder="{{ $placeholder }}" {{ $attributes->except('class') }}>
        </div>
    @else
        <input class="o-input{{ $num ? ' o-input--num' : '' }}" type="{{ $num ? 'number' : $type }}"
               @if ($name) name="{{ $name }}" id="{{ $name }}" @endif
               value="{{ $value }}" placeholder="{{ $placeholder }}" {{ $attributes->except('class') }}>
    @endif

    @if ($help)<p class="o-help">{{ $help }}</p>@endif
</div>
