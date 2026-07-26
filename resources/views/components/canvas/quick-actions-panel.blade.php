@props(['items' => []])
<section class="rounded-[22px] border border-cc-line bg-white p-5 cc-lift-2">
    <h2 class="text-[12px] font-extrabold uppercase tracking-[0.13em] text-cc-navy">Quick Actions</h2>
    <p class="mt-0.5 text-[11px] text-cc-ink-3">Shortcuts</p>

    <div class="mt-4 grid grid-cols-5 gap-2">
        @foreach ($items as $a)
            <a href="{{ $a['href'] ?? '#' }}" title="{{ $a['label'] }}"
               class="group relative grid place-items-center gap-1.5 rounded-xl py-2 transition hover:bg-cc-mist">
                <span class="cc-hex-flat grid h-10 w-10 place-items-center bg-cc-mist text-cc-navy transition group-hover:bg-cc-gold">
                    <x-canvas.icon :name="$a['icon']" :size="16" />
                </span>
                <span class="w-full truncate px-0.5 text-center text-[8.5px] font-bold leading-tight text-cc-ink-2">{{ $a['label'] }}</span>
                <span role="tooltip" class="pointer-events-none absolute -top-7 z-30 whitespace-nowrap rounded-md bg-cc-navy px-2 py-1 text-[10px] font-semibold text-white opacity-0 transition group-hover:opacity-100">{{ $a['label'] }}</span>
            </a>
        @endforeach
    </div>
</section>
