@php
    use App\Support\NavPanel;
    use Illuminate\Support\Facades\Route;

    $current = NavPanel::currentArea();
@endphp

{{--
    THE COMMAND RAIL — which area you are in.

    Every area is on it, which is the point: five modules once sat behind a
    More menu, and a menu is a place things go to be forgotten.

    Where you are is drawn as a planet in orbit rather than a highlighted row.
    Two gold rings breathe around the icon, and a connector runs from the rail
    into the selected item in the panel beside it — so the two dark surfaces
    read as one instrument with one focus, not two lists that happen to agree.
--}}
<div class="flex w-[84px] shrink-0 flex-col gap-3">
    <nav class="orbit-rail orbit-field relative flex min-h-0 flex-1 flex-col items-center gap-1.5 overflow-hidden rounded-[26px] px-2.5 py-4 shadow-[0_24px_60px_-28px_rgba(3,10,25,0.9)]"
         aria-label="Areas">

        {{-- Gold dust. Fixed positions rather than random, so the rail is the
             same rail on every render — texture, not noise. --}}
        <span aria-hidden="true" class="pointer-events-none absolute inset-0">
            @foreach ([[18, 12, 14], [62, 26, 22], [30, 58, 10], [70, 74, 18], [22, 88, 12], [54, 44, 26]] as [$x, $y, $o])
                <span class="absolute h-[2px] w-[2px] rounded-full bg-orbit-gold-lit"
                      style="left: {{ $x }}%; top: {{ $y }}%; opacity: {{ $o / 100 }}"></span>
            @endforeach
            <span class="absolute inset-x-4 top-[22%] h-px bg-gradient-to-r from-transparent via-orbit-gold/20 to-transparent"></span>
            <span class="absolute inset-x-4 bottom-[24%] h-px bg-gradient-to-r from-transparent via-orbit-cyan/15 to-transparent"></span>
        </span>

        {{-- the mark --}}
        <a href="{{ route('home') }}" title="{{ config('app.name') }}"
           class="group relative mb-2 grid h-12 w-12 shrink-0 place-items-center rounded-2xl">
            <span aria-hidden="true" class="absolute inset-1 rounded-2xl bg-[radial-gradient(circle,rgba(244,215,106,0.22),transparent_70%)]"></span>
            <span class="relative block h-4 w-4 rotate-45 rounded-[3px] border-[1.5px] border-orbit-gold-lit shadow-[0_0_12px_rgba(244,215,106,0.55)] transition duration-200 ease-out group-hover:scale-110"></span>
        </a>

        <span aria-hidden="true" class="mb-1.5 h-px w-7 shrink-0 bg-white/10"></span>

        @foreach (NavPanel::AREAS as $key => $area)
            @continue (! Route::has($area['route']))
            @php $active = $current === $key; @endphp

            <a href="{{ route($area['route']) }}" title="{{ $area['label'] }}"
               aria-label="{{ $area['label'] }}"
               @if ($active) aria-current="page" @endif
               class="group relative grid h-12 w-12 shrink-0 place-items-center rounded-2xl transition duration-200 ease-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orbit-gold/70">

                @if ($active)
                    {{-- the orbit --}}
                    <span aria-hidden="true" class="orbit-ring pointer-events-none absolute inset-[-7px] rounded-full"></span>
                    <span aria-hidden="true" class="orbit-ring orbit-ring-2 pointer-events-none absolute inset-[-13px] rounded-full"></span>
                    <span aria-hidden="true" class="pointer-events-none absolute inset-[-2px] rounded-full bg-[radial-gradient(circle,rgba(244,215,106,0.28),transparent_68%)]"></span>

                    {{-- the connector into the panel --}}
                    <span aria-hidden="true" class="pointer-events-none absolute left-full top-1/2 hidden h-px w-[26px] -translate-y-1/2 bg-gradient-to-r from-orbit-gold-lit to-transparent xl:block"></span>
                    <span aria-hidden="true" class="pointer-events-none absolute left-full top-1/2 hidden h-1.5 w-1.5 -translate-y-1/2 translate-x-[3px] rounded-full bg-orbit-gold-lit shadow-[0_0_10px_rgba(244,215,106,0.9)] xl:block"></span>
                @endif

                <x-icon :name="$area['icon']" @class([
                    'relative h-[19px] w-[19px] transition duration-200 ease-out',
                    'text-orbit-gold-lit drop-shadow-[0_0_6px_rgba(244,215,106,0.5)]' => $active,
                    'text-white/45 group-hover:scale-[1.07] group-hover:text-white' => ! $active,
                ]) />

                {{-- The rail is icons only. On a tablet, where the panel is
                     gone, the tooltip is the only label there is. --}}
                <span class="pointer-events-none absolute left-full z-50 ml-4 hidden whitespace-nowrap rounded-xl border border-white/12 bg-orbit-navy px-2.5 py-1 text-[11px] font-semibold text-white shadow-xl group-hover:block">
                    {{ $area['label'] }}
                </span>
            </a>
        @endforeach

        {{-- the brand marker --}}
        <div class="mt-auto flex shrink-0 flex-col items-center gap-2 pt-4">
            <span aria-hidden="true" class="block h-2 w-2 rotate-45 rounded-[1px] border border-orbit-gold/50"></span>
            <span aria-hidden="true" class="h-8 w-px bg-gradient-to-b from-orbit-gold/30 to-transparent"></span>
            <span class="text-center text-[8.5px] font-bold uppercase leading-[1.6] tracking-[0.3em] text-orbit-label/60">
                Elite<br>Orbit<br>OS
            </span>
        </div>
    </nav>

    {{-- Settings sits apart: it is not a place you work, it is where you go to
         change how the places you work behave. --}}
    @php $inSettings = $current === 'settings'; @endphp
    <a href="{{ route('settings.index') }}" title="Settings" aria-label="Settings"
       @if ($inSettings) aria-current="page" @endif
       @class([
           'orbit-rail group relative grid h-14 shrink-0 place-items-center rounded-[24px] shadow-[0_20px_44px_-26px_rgba(3,10,25,0.9)] transition duration-200 ease-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orbit-gold/70',
           'text-orbit-gold-lit' => $inSettings,
           'text-white/50 hover:text-white' => ! $inSettings,
       ])>
        @if ($inSettings)
            <span aria-hidden="true" class="orbit-ring pointer-events-none absolute inset-[8px] rounded-full"></span>
        @endif
        <x-icon name="cog" class="relative h-[19px] w-[19px] transition duration-200 ease-out group-hover:scale-[1.07]" />
        <span class="pointer-events-none absolute left-full z-50 ml-4 hidden whitespace-nowrap rounded-xl border border-white/12 bg-orbit-navy px-2.5 py-1 text-[11px] font-semibold text-white shadow-xl group-hover:block">
            Settings
        </span>
    </a>
</div>
