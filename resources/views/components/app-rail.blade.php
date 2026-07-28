@php
    use App\Support\NavPanel;
    use Illuminate\Support\Facades\Route;

    $current = NavPanel::currentArea();
@endphp

{{--
    The rail: which area you are in.

    Every area is on it, which is the point — five modules used to sit behind a
    More menu, and a menu is a place things go to be forgotten. 56px of dark
    against a light panel, so the screen still gets one dark accent rather than
    the 292px wall this replaced.
--}}
<div class="flex shrink-0 flex-col gap-3">
    <nav class="flex min-h-0 flex-1 flex-col items-center gap-1 rounded-[22px] bg-navy-900 p-2.5 shadow-[0_18px_40px_-24px_rgba(11,31,58,0.75)]"
         aria-label="Areas">

        <a href="{{ route('home') }}" class="mb-1 grid h-11 w-11 place-items-center rounded-2xl" title="{{ config('app.name') }}">
            <span class="block h-3.5 w-3.5 rotate-45 rounded-[2px] border-2 border-gold-400"></span>
        </a>
        <span class="mb-2 h-px w-6 bg-white/10"></span>

        @foreach (NavPanel::AREAS as $key => $area)
            @continue (! Route::has($area['route']))
            @php $active = $current === $key; @endphp
            <a href="{{ route($area['route']) }}" title="{{ $area['label'] }}"
               @if ($active) aria-current="page" @endif
               @class([
                   'group relative grid h-11 w-11 place-items-center rounded-2xl transition',
                   'bg-white/10 text-white' => $active,
                   'text-white/45 hover:bg-white/[0.07] hover:text-white/85' => ! $active,
               ])>
                {{-- The gold tick is the only colour on the rail, so where you
                     are reads before you have finished looking. --}}
                @if ($active)
                    <span class="absolute -left-2.5 h-5 w-1 rounded-full bg-gold-400"></span>
                @endif
                <x-icon :name="$area['icon']" class="h-[18px] w-[18px]" />

                {{-- The rail is icons only, so every one says its name on hover. --}}
                <span class="pointer-events-none absolute left-full z-50 ml-3 hidden whitespace-nowrap rounded-lg bg-navy-900 px-2.5 py-1 text-[11px] font-semibold text-white shadow-lg group-hover:block">
                    {{ $area['label'] }}
                </span>
            </a>
        @endforeach

        <span class="mt-auto pb-1 text-[8.5px] font-bold uppercase tracking-[0.3em] text-white/20 [writing-mode:vertical-rl]">
            Elite · Nav
        </span>
    </nav>

    {{-- Settings sits apart: it is not a place you work, it is where you go to
         change how the places you work behave. --}}
    @php $inSettings = $current === 'settings'; @endphp
    <a href="{{ route('settings.index') }}" title="Settings"
       @if ($inSettings) aria-current="page" @endif
       @class([
           'group relative grid h-12 w-[60px] place-items-center rounded-[22px] bg-navy-900 shadow-[0_18px_40px_-24px_rgba(11,31,58,0.75)] transition',
           'text-white' => $inSettings,
           'text-white/55 hover:text-white' => ! $inSettings,
       ])>
        @if ($inSettings)
            <span class="absolute -left-2.5 h-5 w-1 rounded-full bg-gold-400"></span>
        @endif
        <x-icon name="cog" class="h-[18px] w-[18px]" />
        <span class="pointer-events-none absolute left-full z-50 ml-3 hidden whitespace-nowrap rounded-lg bg-navy-900 px-2.5 py-1 text-[11px] font-semibold text-white shadow-lg group-hover:block">
            Settings
        </span>
    </a>
</div>
