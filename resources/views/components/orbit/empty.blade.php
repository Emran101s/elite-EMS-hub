@props([
    'icon' => 'grid',
    'title' => '',
    'body' => null,      // teach the concept — "No data" is not acceptable copy
])
<div {{ $attributes->merge(['class' => 'o-empty']) }}>
    <span class="o-empty__orb"><x-orbit.icon :name="$icon" :size="28" /></span>
    <div>
        <h3>{{ $title }}</h3>
        @if ($body)<p style="margin:8px auto 0">{{ $body }}</p>@endif
    </div>
    {{ $slot }}
</div>
