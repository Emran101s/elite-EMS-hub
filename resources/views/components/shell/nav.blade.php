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
    $areaMeta = \App\Support\NavPanel::areaMeta($current);
    $areaIcon = $current === 'settings'
        ? \App\Support\NavPanel::SETTINGS['icon']
        : (\App\Support\NavPanel::AREAS[$current]['icon'] ?? 'home');

    $metaToneClass = match ($areaMeta['tone'] ?? null) {
        'ok' => 'bg-success-soft text-success-ink',
        'warn' => 'bg-warning-soft text-warning-ink',
        'info' => 'bg-info-soft text-info-ink',
        default => 'bg-navy-50 text-navy-700',
    };
@endphp

{{--
    xl:sticky + a viewport-relative max-height is what makes the panel's own
    overflow-y-auto (and the fade cues below) ever actually engage — without
    a height cap this flex child just stretches to match the main column's
    height (a very tall page makes an equally tall, empty-at-the-bottom
    sidebar), and without sticky it scrolls away with the page on anything
    longer than one screen. xl:top-4 matches the shell root's own sm:p-4
    (1rem) padding, active at this breakpoint, so the pinned nav sits flush
    with the same edge the rest of the shell uses.
--}}
<div class="shellx-drawer fixed inset-y-0 left-0 z-40 flex -translate-x-full gap-2 p-2.5 xl:sticky xl:top-3 xl:z-auto xl:max-h-[calc(100vh-1.5rem)] xl:translate-x-0 xl:p-0"
     :class="nav ? 'translate-x-0' : '-translate-x-full'">

    {{-- ══ THE RAIL — which area ══
         Each area's icon sits in a hexagon, the same cell the Event Hub uses
         for its modules. The current area's hex fills gold; the rest stay
         faint until hovered. That carries one shape through the whole app —
         the nav and the modules it leads to are visibly the same system —
         and it marks the active area without the extra ring, fill and edge
         pill the old row needed, so the rail reads at a glance and gives
         ~60px of width back to the workspace. --}}
    <div class="flex w-[70px] shrink-0 flex-col gap-2">
        <nav class="shellx-rail flex min-h-0 flex-1 flex-col items-center gap-px rounded-[18px] p-1.5 shadow-[0_18px_40px_-24px_rgba(11,31,58,0.75)]"
             aria-label="Areas">

            <a href="{{ route('home') }}" class="mb-0.5 grid h-9 w-9 place-items-center" title="Elite Business Hub">
                <span class="shellx-hex grid h-[22px] w-[19px] place-items-center bg-gold-400/20">
                    <span class="block h-2 w-2 rotate-45 rounded-[1px] border-[1.5px] border-gold-400"></span>
                </span>
            </a>
            <span class="mb-1 h-px w-5 bg-white/10"></span>

            @foreach (\App\Support\NavPanel::AREAS as $key => $area)
                @continue (! \Illuminate\Support\Facades\Route::has($area['route']))
                @php $active = $key === $current; @endphp
                {{-- Labelled rail: every area names itself, so the icons stop
                     being a guessing game (truck = Operations, sparkles =
                     Intelligence, id-card = Commercial). title="" stays for the
                     accessible tooltip and the chrome test; the label is now
                     visible in the rail rather than only on hover. --}}
                <a href="{{ route($area['route']) }}" title="{{ $area['label'] }}"
                   @class([
                       'group relative flex w-full flex-col items-center gap-0.5 rounded-xl px-0.5 py-1 transition',
                       'text-gold-200' => $active,
                       'text-white/55 hover:text-white/90' => ! $active,
                   ])>
                    <span @class([
                        'shellx-hex grid h-[26px] w-[23px] shrink-0 place-items-center transition',
                        'bg-gold-400 text-navy-950' => $active,
                        'bg-white/[0.07] group-hover:bg-white/[0.14]' => ! $active,
                    ])>
                        <x-icon :name="$area['icon']" class="h-[15px] w-[15px]" />
                    </span>
                    <span class="block w-full text-center text-[8.5px] font-semibold leading-[1.15] tracking-tight [overflow-wrap:anywhere]">{{ $area['label'] }}</span>
                </a>
            @endforeach

            <span class="mt-auto text-[8px] font-bold uppercase tracking-[0.3em] text-white/20 [writing-mode:vertical-rl]">
                Elite
            </span>
        </nav>

        @if (\Illuminate\Support\Facades\Route::has(\App\Support\NavPanel::SETTINGS['route']))
            <a href="{{ route(\App\Support\NavPanel::SETTINGS['route']) }}" title="{{ \App\Support\NavPanel::SETTINGS['label'] }}"
               class="flex w-[70px] flex-col items-center justify-center gap-0.5 rounded-[18px] bg-navy-900 py-2 shadow-[0_18px_40px_-24px_rgba(11,31,58,0.75)] transition
                      {{ $current === 'settings' ? 'text-gold-400' : 'text-white/55 hover:text-white' }}">
                <span class="shellx-hex grid h-[24px] w-[21px] place-items-center {{ $current === 'settings' ? 'bg-gold-400 text-navy-950' : 'bg-white/[0.07]' }}">
                    <x-icon name="cog" class="h-[14px] w-[14px]" />
                </span>
                <span class="text-[8.5px] font-semibold leading-none tracking-tight">Settings</span>
            </a>
        @endif
    </div>

    {{-- ══ THE PANEL — what's inside the area ══
         Width caps to the viewport in the mobile drawer (100vw − the rail,
         gaps and drawer padding) so the wider labelled rail can never push
         the panel off the right edge; on xl it resolves to the full 280px. --}}
    <aside class="flex w-[min(248px,calc(100vw-104px))] shrink-0 flex-col overflow-hidden rounded-[18px] border border-line bg-white shadow-[0_20px_50px_-32px_rgba(11,31,58,0.45)] xl:w-[248px]">
        {{-- Area header — a real anchor, not just an eyebrow. The area's own
             emblem in a navy hexagon, its name, and the one live number worth
             knowing before you click anything. The count sits inline with the
             name rather than on its own line below it: same information, one
             row instead of two. --}}
        <div class="shellx-panel-head relative overflow-hidden px-3 py-2.5">
            <div class="relative flex items-center gap-2.5">
                <span class="shellx-hex grid h-[30px] w-[26px] shrink-0 place-items-center bg-gradient-to-br from-navy-800 to-navy-950 text-gold-300">
                    <x-icon :name="$areaIcon" class="h-[15px] w-[15px]" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-serif text-[15px] font-semibold leading-tight text-navy-900">{{ \App\Support\NavPanel::areaLabel($current) }}</p>
                    @if ($areaMeta)
                        <span class="mt-0.5 inline-flex items-center gap-1 rounded-full px-1.5 py-px text-[10px] font-bold {{ $metaToneClass }}">
                            <b class="tabular-nums">{{ $areaMeta['value'] }}</b><span class="font-semibold opacity-80">{{ $areaMeta['label'] }}</span>
                        </span>
                    @else
                        <p class="text-[10.5px] text-navy-300">Workspace area</p>
                    @endif
                </div>
                <button type="button" class="shellx-nav-close -mr-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg text-navy-300 hover:bg-page hover:text-navy-700 xl:hidden"
                        @click="nav = false" aria-label="Close navigation">
                    <x-icon name="dots" class="h-4 w-4 rotate-45" />
                </button>
            </div>
        </div>

        <div class="mx-3 h-px bg-line"></div>

        <div class="relative min-h-0 flex-1"
             x-data="{
                 atTop: true, atBottom: true,
                 check() {
                     const el = this.$refs.sections;
                     if (! el) return;
                     this.atTop = el.scrollTop <= 2;
                     this.atBottom = el.scrollHeight - el.scrollTop - el.clientHeight <= 2;
                 },
             }"
             x-init="$nextTick(() => check())">
            <nav x-ref="sections" @scroll="check()" class="scrollbar-none h-full overflow-y-auto px-2 pb-2 pt-2" aria-label="Sections">
                @forelse ($sections as $section)
                    <p class="px-1.5 pb-1 pt-0.5 text-[9.5px] font-bold uppercase tracking-[0.16em] text-navy-300">{{ $section['label'] }}</p>

                    @foreach ($section['items'] as $item)
                        <a href="{{ $item['href'] }}" wire:navigate
                           @class([
                               'group/nav flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left transition',
                               'shellx-row-active' => $item['active'],
                               'hover:bg-page/60' => ! $item['active'],
                           ])>
                            <span @class([
                                'shellx-hex grid h-[22px] w-[19px] shrink-0 place-items-center transition',
                                'bg-gold-400 text-navy-950' => $item['active'],
                                'bg-page text-navy-300 group-hover/nav:text-navy-600' => ! $item['active'],
                            ])>
                                <x-icon :name="$item['icon']" class="h-[13px] w-[13px]" />
                            </span>
                            <span class="min-w-0 flex-1 truncate text-[12px] {{ $item['active'] ? 'font-bold text-navy-900' : 'font-medium text-navy-700' }}">{{ $item['label'] }}</span>
                            @if ($item['count'] !== null && $item['count'] !== 0)
                                <span @class([
                                    'shrink-0 rounded-full px-1.5 text-[9.5px] font-bold leading-[1.45] tabular-nums',
                                    'bg-gold-500 text-navy-900' => $item['active'],
                                    'bg-navy-50 text-navy-600' => ! $item['active'],
                                ])>{{ $item['count'] }}</span>
                            @endif
                        </a>
                    @endforeach

                    @unless ($loop->last)
                        <div class="my-1.5 h-px bg-line"></div>
                    @endunless
                @empty
                    <p class="px-2 py-5 text-center text-[11.5px] text-muted">Nothing here yet.</p>
                @endforelse
            </nav>

            <div class="shellx-fade shellx-fade-top" :class="{ 'opacity-0': atTop }"></div>
            <div class="shellx-fade shellx-fade-bottom" :class="{ 'opacity-0': atBottom }"></div>
        </div>
    </aside>
</div>
