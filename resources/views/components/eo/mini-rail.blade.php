@php
    use App\Support\NavPanel;
    use Illuminate\Support\Facades\Route;

    $current = NavPanel::currentArea();
@endphp

{{-- Soft Command MiniRail — slim dark icon rail; teal active, gold brand-only. --}}
<nav {{ $attributes->class(['eo-mini-rail']) }} aria-label="Areas">
    <a href="{{ route('home') }}" title="{{ config('app.name') }}"
       class="mt-6 grid h-11 w-11 place-items-center rounded-2xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-eo-teal/50">
        <span class="relative grid h-10 w-10 place-items-center rounded-2xl bg-gradient-to-b from-eo-gold-soft/20 to-transparent">
            <svg viewBox="0 0 24 24" class="h-6 w-6 text-eo-gold-soft" fill="currentColor" aria-hidden="true">
                <path d="M12 1.6l2.35 7.4 7.4 2.35-7.4 2.35-2.35 7.4-2.35-7.4L2.25 11.35l7.4-2.35z"/>
            </svg>
        </span>
    </a>

    <span class="grow" aria-hidden="true"></span>

    <div class="flex flex-col items-center gap-2">
        @foreach (NavPanel::AREAS as $key => $area)
            @continue (! Route::has($area['route']))
            @php $active = $current === $key; @endphp
            <a href="{{ route($area['route']) }}"
               title="{{ $area['label'] }}"
               aria-label="{{ $area['label'] }}"
               @if ($active) aria-current="page" @endif
               @class(['eo-mini-rail-item', 'is-active' => $active])>
                <x-icon :name="$area['icon']" class="h-[21px] w-[21px]" />
                <span class="pointer-events-none absolute left-full z-50 ml-3 hidden whitespace-nowrap rounded-xl bg-eo-navy px-2.5 py-1 text-[11px] font-semibold text-white shadow-eo-float group-hover:block xl:group-hover:block">
                    {{ $area['label'] }}
                </span>
            </a>
        @endforeach
    </div>

    <span class="grow-[1.4]" aria-hidden="true"></span>

    <div class="mb-6 flex flex-col items-center gap-2">
        <span class="eo-radar-mark" title="Mission Radar" aria-hidden="true"></span>
        <span class="text-center text-[9px] font-bold uppercase leading-[1.6] tracking-[0.22em] text-white/35">
            Elite<br>Orbit
        </span>
    </div>
</nav>
