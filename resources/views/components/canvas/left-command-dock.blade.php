@props(['items' => [], 'current' => 'home'])
{{-- A floating tool dock, not a sidebar. Icons with small labels, gold marks position. --}}
<nav aria-label="Primary" class="sticky top-4 z-20 flex shrink-0 flex-row gap-1 overflow-x-auto rounded-[22px] border border-cc-line bg-white p-2 cc-lift-2 2xl:flex-col 2xl:overflow-visible">
    @foreach ($items as $item)
        @php $active = $item['key'] === $current; @endphp
        <a href="#" @if ($active) aria-current="page" @endif
           class="group relative grid w-[62px] shrink-0 place-items-center gap-1 rounded-2xl px-1 py-2.5 transition
                  {{ $active ? 'bg-cc-gold/15 text-cc-navy' : 'text-cc-ink-3 hover:bg-cc-mist hover:text-cc-navy' }}">
            <span class="cc-hex-flat grid h-9 w-9 place-items-center transition
                         {{ $active ? 'bg-cc-gold text-cc-navy' : 'bg-cc-mist text-cc-ink-2 group-hover:bg-white' }}">
                <x-canvas.icon :name="$item['icon']" :size="17" />
            </span>
            <span class="text-[9.5px] font-bold tracking-tight {{ $active ? 'text-cc-navy' : '' }}">{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
