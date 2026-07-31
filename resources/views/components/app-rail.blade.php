@php
    use App\Support\NavPanel;
    use Illuminate\Support\Facades\Route;

    $current = NavPanel::currentArea();
@endphp

{{--
    THE COMMAND RAIL — which area you are in.

    Every area is on it, which is the point: five modules once sat behind a More
    menu, and a menu is a place things go to be forgotten.

    Where you are is drawn as a planet in orbit rather than a highlighted row.
    Two gold rings breathe around the icon and a gold node sits on the rail's
    edge beside it, so the rail and the panel read as one instrument with one
    focus rather than two lists that happen to agree.

    Settings is not here — it lives in the panel's dock, where the reference
    puts it, next to the other two things you reach for without reading.
--}}
{{-- No overflow-hidden here, ever: the hover labels are painted OUTSIDE this
     box (absolute, left-full), so clipping it silently swallows them. The
     decorative layer below clips itself instead. --}}
<nav class="orbit-rail relative flex h-full w-[78px] shrink-0 flex-col items-center"
     aria-label="Areas">

    {{-- Gold dust and orbit arcs. Fixed positions rather than random, so the
         rail is the same rail on every render — texture, not noise. --}}
    <span aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
        @foreach ([[18, 12, 14], [62, 26, 22], [30, 58, 10], [70, 74, 18], [22, 88, 12], [54, 44, 26]] as [$x, $y, $o])
            <span class="absolute h-[2px] w-[2px] rounded-full bg-orbit-gold-lit"
                  style="left: {{ $x }}%; top: {{ $y }}%; opacity: {{ $o / 100 }}"></span>
        @endforeach
        <span class="absolute -left-24 bottom-16 aspect-square w-[220px] rounded-full border border-orbit-gold/[0.06]"></span>
        <span class="absolute -left-32 bottom-4 aspect-square w-[300px] rounded-full border border-orbit-cyan/[0.05]"></span>
    </span>

    {{-- the mark --}}
    <a href="{{ route('home') }}" title="{{ config('app.name') }}"
       class="group relative mt-7 grid h-12 w-12 shrink-0 place-items-center rounded-2xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orbit-gold/70">
        <span aria-hidden="true" class="absolute inset-0 rounded-full bg-[radial-gradient(circle,rgba(244,215,106,0.24),transparent_68%)]"></span>
        <svg viewBox="0 0 24 24" class="relative h-7 w-7 text-orbit-gold-lit drop-shadow-[0_0_10px_rgba(244,215,106,0.7)] transition duration-200 ease-out group-hover:scale-110"
             fill="currentColor" aria-hidden="true">
            <path d="M12 1.6l2.35 7.4 7.4 2.35-7.4 2.35-2.35 7.4-2.35-7.4L2.25 11.35l7.4-2.35z"/>
        </svg>
    </a>

    <span aria-hidden="true" class="grow"></span>

    {{-- the areas --}}
    <div class="relative flex shrink-0 flex-col items-center gap-2.5">
        @foreach (NavPanel::AREAS as $key => $area)
            @continue (! Route::has($area['route']))
            @php $active = $current === $key; @endphp

            <a href="{{ route($area['route']) }}" title="{{ $area['label'] }}"
               aria-label="{{ $area['label'] }}"
               @if ($active) aria-current="page" @endif
               class="group relative grid h-12 w-12 shrink-0 place-items-center rounded-2xl transition duration-200 ease-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orbit-gold/70">

                @if ($active)
                    {{-- the orbit --}}
                    <span aria-hidden="true" class="orbit-ring pointer-events-none absolute inset-[-6px] rounded-full"></span>
                    <span aria-hidden="true" class="orbit-ring orbit-ring-2 pointer-events-none absolute inset-[-14px] rounded-full"></span>
                    <span aria-hidden="true" class="pointer-events-none absolute inset-[-2px] rounded-full bg-[radial-gradient(circle,rgba(244,215,106,0.26),transparent_68%)]"></span>

                    {{-- the node on the rail's edge, where the panel begins --}}
                    <span aria-hidden="true" class="pointer-events-none absolute left-full top-1/2 hidden h-px w-[9px] -translate-y-1/2 bg-gradient-to-r from-orbit-gold-lit/70 to-transparent xl:block"></span>
                    <span aria-hidden="true" class="pointer-events-none absolute left-full top-1/2 hidden h-[7px] w-[7px] -translate-y-1/2 translate-x-[5px] rounded-full bg-orbit-gold-lit shadow-[0_0_12px_rgba(244,215,106,0.95)] xl:block"></span>
                @endif

                <x-icon :name="$area['icon']" @class([
                    'relative h-[22px] w-[22px] transition duration-200 ease-out',
                    'text-orbit-gold-lit drop-shadow-[0_0_6px_rgba(244,215,106,0.5)]' => $active,
                    'text-white/45 group-hover:scale-[1.07] group-hover:text-white' => ! $active,
                ]) />

                {{-- The rail is icons only. Below xl, where the panel is gone,
                     the tooltip is the only label there is. --}}
                <span class="pointer-events-none absolute left-full z-50 ml-4 hidden whitespace-nowrap rounded-xl border border-white/12 bg-orbit-navy px-2.5 py-1 text-[11px] font-semibold text-white shadow-xl group-hover:block">
                    {{ $area['label'] }}
                </span>
            </a>
        @endforeach
    </div>

    <span aria-hidden="true" class="grow-[1.1]"></span>

    {{-- the brand marker --}}
    <div class="relative flex shrink-0 flex-col items-center gap-2.5">
        <svg viewBox="0 0 24 24" class="h-4 w-4 text-orbit-gold/80" fill="currentColor" aria-hidden="true">
            <path d="M12 1.6l2.35 7.4 7.4 2.35-7.4 2.35-2.35 7.4-2.35-7.4L2.25 11.35l7.4-2.35z"/>
        </svg>
        <span aria-hidden="true" class="h-10 w-px bg-gradient-to-b from-orbit-gold/35 to-transparent"></span>
        <span class="text-center text-[9px] font-bold uppercase leading-[1.7] tracking-[0.28em] text-orbit-label/65">
            Elite<br>Orbit<br>OS
        </span>
        <span aria-hidden="true" class="h-10 w-px bg-gradient-to-b from-transparent via-orbit-gold/25 to-transparent"></span>
    </div>

    <span aria-hidden="true" class="grow-[1.6]"></span>
</nav>
