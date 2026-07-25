@props([
    'label' => 'Quick add',
    // items: [['label'=>'Task','icon'=>'task','href'=>'…'], …]
    'items' => [],
])
<div {{ $attributes->merge(['class' => 'o-quick']) }}>
    <span class="o-quick__label">{{ $label }}</span>
    @foreach ($items as $item)
        <a class="o-quick__item" href="{{ $item['href'] ?? '#' }}">
            <span class="o-quick__ico"><x-orbit.icon :name="$item['icon'] ?? 'plus'" :size="18" /></span>
            <span class="o-quick__t">{{ $item['label'] ?? '' }}</span>
        </a>
    @endforeach
</div>
