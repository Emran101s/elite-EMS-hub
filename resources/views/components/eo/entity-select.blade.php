@props([
    'label' => null,
    'items' => [],
    'labelKey' => 'name',
    'subtitleKey' => null,
    'emptyLabel' => '— none —',
])

{{--
    One labeled <select> for "pick a record from a short list" — a supplier,
    a venue, anything else with an id and a name. Every legacy Hub module that
    picks a supplier or venue already writes this exact markup by hand; this
    is that markup, once, so a future conversion swaps a duplicated block for
    one line instead of retyping it.

    Presentation only: it takes whatever collection the caller already
    queried and renders it. No query, no model change — the caller keeps
    deciding what belongs in the list (Accommodation's hotel-priority
    ordering, a category filter, whatever the module already does).
--}}

<div>
    @if ($label)
        <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">{{ $label }}</label>
    @endif

    <select {{ $attributes->class(['w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink focus:border-navy-300 focus:outline-none']) }}>
        @if ($emptyLabel)
            <option value="">{{ $emptyLabel }}</option>
        @endif
        @foreach ($items as $item)
            <option value="{{ $item->id }}">
                {{ data_get($item, $labelKey) }}@if ($subtitleKey && data_get($item, $subtitleKey)) · {{ data_get($item, $subtitleKey) }}@endif
            </option>
        @endforeach
    </select>
</div>
