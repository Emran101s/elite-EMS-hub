@props(['venue', 'activeTab'])

<nav class="hubx-modnav">
    <div class="hubx-modnav-scroll">
        @foreach (\App\Models\Venue::STUDIO_TABS as $key => [$label, $purpose, $icon])
            <a href="{{ route('venues.show', [$venue, 'tab' => $key]) }}" wire:navigate
               class="hubx-modnav-item {{ $key === $activeTab ? 'is-active' : '' }}">
                <x-icon :name="$icon" class="h-4 w-4" />
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </div>
</nav>
