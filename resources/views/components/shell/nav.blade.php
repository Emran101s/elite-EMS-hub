{{--
    The consolidated left nav — one floating unit built from two panes:
    a narrow navy rail (which AREA) and a wider white panel (what's inside
    it), both live off App\Support\NavPanel, not a hand-kept list. Built
    from resources/views/concept/nav.blade.php's proven visual language,
    wired to real data instead of that prototype's hardcoded area array.

    Below xl (1280px) this whole pane becomes a slide-over drawer, sharing
    the single `nav` Alpine boolean already declared on the shell root in
    layouts/app.blade.php — there is exactly one state for "is the nav
    open," not a separate one per breakpoint.
--}}

@php
    $current = \App\Support\NavPanel::currentArea();
    $sections = \App\Support\NavPanel::sections($current);
@endphp

<div class="shellx-drawer fixed inset-y-0 left-0 z-40 flex -translate-x-full gap-3 p-3 xl:static xl:z-auto xl:translate-x-0 xl:p-0"
     :class="nav ? 'translate-x-0' : '-translate-x-full'">

    {{-- ══ THE RAIL — which area ══ --}}
    <div class="flex w-[60px] shrink-0 flex-col gap-3">
        <nav class="shellx-rail flex min-h-0 flex-1 flex-col items-center gap-1 rounded-[22px] p-2.5 shadow-[0_18px_40px_-24px_rgba(11,31,58,0.75)]"
             aria-label="Areas">

            <a href="{{ route('home') }}" class="mb-1 grid h-11 w-11 place-items-center rounded-2xl" title="Elite Business Hub">
                <span class="block h-3.5 w-3.5 rotate-45 rounded-[2px] border-2 border-gold-400"></span>
            </a>
            <span class="mb-2 h-px w-6 bg-white/10"></span>

            @foreach (\App\Support\NavPanel::AREAS as $key => $area)
                @continue (! \Illuminate\Support\Facades\Route::has($area['route']))
                @php $active = $key === $current; @endphp
                <a href="{{ route($area['route']) }}" title="{{ $area['label'] }}"
                   @class([
                       'group relative grid h-11 w-11 place-items-center rounded-2xl transition',
                       'bg-white/10 text-white' => $active,
                       'text-white/45 hover:bg-white/[0.07] hover:text-white/85' => ! $active,
                   ])>
                    @if ($active)
                        <span class="absolute -left-2.5 h-5 w-1 rounded-full bg-gold-400"></span>
                    @endif
                    <x-icon :name="$area['icon']" class="h-[18px] w-[18px]" />

                    <span class="pointer-events-none absolute left-full z-50 ml-3 hidden whitespace-nowrap rounded-lg bg-navy-900 px-2.5 py-1 text-[11px] font-semibold text-white shadow-lg group-hover:block">
                        {{ $area['label'] }}
                    </span>
                </a>
            @endforeach

            <span class="mt-auto pb-1 text-[8.5px] font-bold uppercase tracking-[0.3em] text-white/20 [writing-mode:vertical-rl]">
                Elite
            </span>
        </nav>

        @if (\Illuminate\Support\Facades\Route::has(\App\Support\NavPanel::SETTINGS['route']))
            <a href="{{ route(\App\Support\NavPanel::SETTINGS['route']) }}" title="{{ \App\Support\NavPanel::SETTINGS['label'] }}"
               class="grid h-12 w-[60px] place-items-center rounded-[22px] shadow-[0_18px_40px_-24px_rgba(11,31,58,0.75)] transition
                      {{ $current === 'settings' ? 'bg-navy-900 text-gold-400' : 'bg-navy-900 text-white/60 hover:text-white' }}">
                <x-icon name="cog" class="h-[18px] w-[18px]" />
            </a>
        @endif
    </div>

    {{-- ══ THE PANEL — what's inside the area ══ --}}
    <aside class="flex w-[280px] shrink-0 flex-col overflow-hidden rounded-[22px] border border-line bg-white shadow-[0_20px_50px_-32px_rgba(11,31,58,0.45)]">
        <div class="flex items-center justify-between px-4 py-4">
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-gold-600">
                    {{ \App\Support\NavPanel::areaLabel($current) }}
                </p>
            </div>
            <button type="button" class="shellx-nav-close grid h-8 w-8 shrink-0 place-items-center rounded-lg text-navy-300 hover:bg-page hover:text-navy-700 xl:hidden"
                    @click="nav = false" aria-label="Close navigation">
                <x-icon name="dots" class="h-4 w-4 rotate-45" />
            </button>
        </div>

        <nav class="scrollbar-none min-h-0 flex-1 overflow-y-auto px-2.5 pb-3" aria-label="Sections">
            @forelse ($sections as $section)
                <p class="px-2 pb-1.5 pt-1 text-[10px] font-bold uppercase tracking-[0.16em] text-navy-300">{{ $section['label'] }}</p>

                @foreach ($section['items'] as $item)
                    <a href="{{ $item['href'] }}" wire:navigate
                       @class([
                           'flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-left transition',
                           'shellx-row-active' => $item['active'],
                           'hover:bg-page/60' => ! $item['active'],
                       ])>
                        <x-icon :name="$item['icon']" class="h-4 w-4 shrink-0 {{ $item['active'] ? 'text-gold-600' : 'text-navy-300' }}" />
                        <span class="min-w-0 flex-1 truncate text-[12.5px] {{ $item['active'] ? 'font-bold text-navy-900' : 'font-medium text-navy-700' }}">{{ $item['label'] }}</span>
                        @if ($item['count'] !== null)
                            <span class="shrink-0 text-[11px] font-semibold tabular-nums text-navy-300">{{ $item['count'] }}</span>
                        @endif
                    </a>
                @endforeach

                @unless ($loop->last)
                    <div class="my-2.5 h-px bg-line"></div>
                @endunless
            @empty
                <p class="px-2 py-6 text-center text-[11.5px] text-muted">Nothing here yet.</p>
            @endforelse
        </nav>
    </aside>
</div>
