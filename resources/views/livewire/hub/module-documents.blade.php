@php
    // One folder glyph, tinted per module — drawn rather than an icon font so the
    // tab colour reads at any size.
    $folderSvg = function (string $hex, string $class = 'h-9 w-11') {
        return '<svg viewBox="0 0 44 36" class="'.$class.'" fill="none" xmlns="http://www.w3.org/2000/svg">'
            .'<path d="M2 7a4 4 0 0 1 4-4h10.7a4 4 0 0 1 2.9 1.2L22.6 7H38a4 4 0 0 1 4 4v21a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V7Z" fill="'.$hex.'" opacity="0.18"/>'
            .'<path d="M2 13h40v19a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V13Z" fill="'.$hex.'"/>'
            .'<path d="M2 7a4 4 0 0 1 4-4h10.7a4 4 0 0 1 2.9 1.2L22.6 7H38a4 4 0 0 1 4 4v2H2V7Z" fill="'.$hex.'" opacity="0.72"/>'
            .'</svg>';
    };
    $inp = 'w-full rounded-lg border border-transparent bg-transparent px-2 py-1 text-xs text-ink placeholder:text-muted hover:border-line focus:border-navy-300 focus:bg-white focus:outline-none';
    $count = $documents->count() + $folders->count();
@endphp

@if ($dock)
    {{-- Docked under every module tab, below the Controls spine. --}}
    <x-dock id="documents" label="Documents" :color="$color" :count="$count" :order="1" :bare="true">
        @include('livewire.hub.partials.document-drawer')
    </x-dock>
@else
    {{-- Full-width library on the Documents tab. One root for Livewire. --}}
    <div>
        @if ($library && ($inLibraryWall ?? false))
            @php
                $drawerFolders = collect($wall)->sum('folders');
            @endphp
            {{-- "Files" is dropped — it's the same total document count the
                 Universal Module Header already shows. Drawers/Folders stay:
                 neither appears in the Header. --}}
            <x-stat-strip class="mb-3" :stats="[
                ['Drawers', collect($wall)->count(), 'archive', null, null, 'One per module'],
                ['Folders', $drawerFolders, 'grid', null, null, null],
            ]" />
        @endif
        {{-- The one module built entirely around a drop zone — a touch of
             frosted-glass translucency on the card reads as the premium
             surface the brief asks for. --}}
        <div class="overflow-hidden rounded-lg border border-line bg-white/90 shadow-raise backdrop-blur-xl">
            @include('livewire.hub.partials.document-drawer')
        </div>
    </div>
@endif
