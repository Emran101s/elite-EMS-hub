@props([
    // items: [['label'=>'Tasks','icon'=>'task','href'=>'…','key'=>'tasks','count'=>17], …]
    // Nine slots; the last is always AI.
    'items' => [],
    'current' => null,
    'grip' => false,     // the mobile drag handle
])
{{-- Chrome: stays dark in both themes. The active item is one of the three
     places gold is allowed — it marks where you are. --}}
<div {{ $attributes->merge(['class' => 'o-dock']) }} role="navigation" aria-label="Primary">
    @if ($grip)<span class="o-dock__grip"></span>@endif
    @foreach ($items as $item)
        @php $active = ($item['key'] ?? null) !== null && $item['key'] === $current; @endphp
        <a class="o-dock__item" href="{{ $item['href'] ?? '#' }}" @if ($active) aria-current="page" @endif>
            <x-orbit.icon :name="$item['icon'] ?? 'grid'" :size="19" />
            <span class="o-dock__label">{{ $item['label'] ?? '' }}</span>
            @if (! empty($item['count']))
                <span class="o-count">{{ $item['count'] }}</span>
            @endif
        </a>
    @endforeach
</div>
