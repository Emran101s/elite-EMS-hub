@php
    use App\Support\NavPanel;
    use Illuminate\Support\Facades\Route;

    $area = NavPanel::currentArea();
    $sections = NavPanel::panel();
    $user = auth()->user();

    // Areas the fixed panel does not cover append their own sections, or the
    // library, the CRM and Settings would lose their only door.
    $extra = in_array($area, NavPanel::PANEL_COVERS, true)
        ? collect()
        : NavPanel::sections($area);

    // The pill counts what is genuinely running, not every record — a number
    // this prominent has to survive being checked.
    $activeEvents = NavPanel::tree('events')->sum(fn ($g) => $g['events']->count());
@endphp

{{--
    THE EXPANDED PANEL — the map of the platform.

    Fixed, not derived from the rail: the reference draws the whole product at
    once, so the panel says what exists and the rail says where you are in it.

    Dark, because it belongs to the rail beside it: together they are the one
    instrument in a product that is otherwise white. The work to the right stays
    light, which is what keeps this a light app with a dark spine rather than a
    dark app.

    Gold appears in exactly four places — the section rules, the selected row,
    the connector from the rail, and the dock's underglow — so it still means
    "here" by the time you reach the bottom.
--}}
<aside class="orbit-panel relative hidden w-[328px] shrink-0 flex-col overflow-hidden xl:flex"
       aria-label="Main navigation">

    {{-- the decorative field: arcs and dust, 4–12% so it reads as texture --}}
    <span aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
        <span class="absolute -left-28 top-20 aspect-square w-[340px] rounded-full border border-orbit-gold/[0.06]"></span>
        <span class="absolute -right-40 bottom-40 aspect-square w-[420px] rounded-full border border-orbit-cyan/[0.05]"></span>
        <span class="absolute -right-28 bottom-56 aspect-square w-[300px] rounded-full border border-orbit-gold/[0.05]"></span>
        @foreach ([[14, 30], [78, 18], [40, 66], [88, 52], [24, 84], [62, 92]] as [$x, $y])
            <span class="absolute h-[2px] w-[2px] rounded-full bg-orbit-gold-lit/25" style="left: {{ $x }}%; top: {{ $y }}%"></span>
        @endforeach
    </span>

    {{-- ══ WHO YOU ARE ══ --}}
    <div class="relative shrink-0 px-4 pb-2.5 pt-4">
        <details class="group/user relative" data-menu>
            <summary class="orbit-glass orbit-glass-hover flex cursor-pointer list-none items-center gap-3 rounded-[20px] px-3.5 py-3 text-left transition duration-200 ease-out hover:-translate-y-px [&::-webkit-details-marker]:hidden">
                <span class="relative shrink-0">
                    <x-user-avatar :user="$user" size="h-11 w-11" />
                    <span aria-hidden="true" class="absolute inset-0 rounded-full ring-[1.5px] ring-orbit-gold/50"></span>
                </span>

                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-1.5">
                        <span class="truncate text-[14px] font-bold text-white">{{ $user?->name }}</span>
                        {{-- the crown: whose workspace this is, not a rank --}}
                        <svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0 text-orbit-gold-lit" fill="currentColor" aria-hidden="true">
                            <path d="M3 8l4 3 5-6 5 6 4-3v9a1 1 0 01-1 1H4a1 1 0 01-1-1V8z"/>
                        </svg>
                    </span>
                    <span class="block truncate text-[11.5px] font-semibold text-orbit-gold/90">{{ $user?->title ?: 'Founder & CEO' }}</span>
                    <span class="block truncate text-[10.5px] text-orbit-dim">{{ $user?->email }}</span>
                </span>

                <x-icon name="chevron" class="h-4 w-4 shrink-0 text-white/35 transition duration-200 group-open/user:rotate-180" />
            </summary>

            <div class="orbit-glass absolute inset-x-0 z-40 mt-1.5 overflow-hidden rounded-2xl py-1">
                <a href="{{ route('settings.index') }}" class="block px-4 py-2 text-[12.5px] font-medium text-white/80 transition hover:bg-white/10 hover:text-white">Settings</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full px-4 py-2 text-left text-[12.5px] font-medium text-white/80 transition hover:bg-white/10 hover:text-white">Sign out</button>
                </form>
            </div>
        </details>
    </div>

    {{-- ══ WHAT IS RUNNING ══ --}}
    @if ($activeEvents > 0 && Route::has('events.index'))
        <div class="relative shrink-0 px-4 pb-3">
            <a href="{{ route('events.index') }}"
               class="orbit-glass orbit-glass-hover flex items-center gap-2.5 rounded-[16px] px-3.5 py-2.5 transition duration-200 ease-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orbit-gold/60">
                <span class="orbit-pulse h-2.5 w-2.5 shrink-0 rounded-full bg-orbit-online"></span>
                <span class="min-w-0 flex-1 truncate text-[13px] font-semibold text-white">
                    {{ $activeEvents }} Active {{ \Illuminate\Support\Str::plural('Event', $activeEvents) }}
                </span>
                <x-icon name="chevron" class="h-4 w-4 shrink-0 -rotate-90 text-white/35" />
            </a>
        </div>
    @endif

    {{-- ══ THE MAP ══ --}}
    <nav class="scrollbar-none relative min-h-0 flex-1 overflow-y-auto px-4 pb-4" aria-label="Sections">
        @foreach ($sections->concat($extra) as $section)
            {{-- Section rule: the label, a hairline running to the edge, and a
                 gold dot to stop it. Cheaper than a divider, and it says where
                 the group ends as well as where it starts. --}}
            <div class="mb-1 mt-3 flex items-center gap-3 px-2 first:mt-0.5">
                <span class="shrink-0 text-[10px] font-bold uppercase tracking-[0.24em] text-orbit-gold">{{ $section['label'] }}</span>
                <span aria-hidden="true" class="h-px flex-1 bg-gradient-to-r from-orbit-gold/30 via-orbit-gold/12 to-transparent"></span>
                <span aria-hidden="true" class="h-[3px] w-[3px] shrink-0 rounded-full bg-orbit-gold/70"></span>
            </div>

            @foreach ($section['items'] as $item)
                @php
                    // One row, two fates. A built page is a link; a page still
                    // to come is the same row with nowhere to go — drawn the
                    // same on purpose, so the shape of the product is legible
                    // before all of it exists.
                    $built = filled($item['href']);
                    $tag = $built ? 'a' : 'span';
                    $creates = $built && str_contains($item['href'], '/create');
                @endphp

                <{{ $tag }}
                    @if ($built) href="{{ $item['href'] }}" @endif
                    @if ($item['active']) aria-current="page" @endif
                    @unless ($built) title="Coming soon" aria-disabled="true" @endunless
                    @class([
                        'group relative flex h-[42px] items-center gap-3 rounded-[16px] px-3 transition duration-200 ease-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orbit-gold/60',
                        'orbit-selected' => $item['active'],
                        'hover:bg-white/[0.06]' => ! $item['active'] && $built,
                        'cursor-default' => ! $built,
                    ])>

                    <x-icon :name="$item['icon']" @class([
                        'h-5 w-5 shrink-0 transition duration-200 ease-out',
                        'text-orbit-gold-lit' => $item['active'],
                        'text-white/50 group-hover:text-white/80' => ! $item['active'],
                    ]) />

                    <span @class([
                        'min-w-0 flex-1 truncate text-[14px]',
                        'font-semibold text-white' => $item['active'],
                        'font-medium text-white/80 group-hover:text-white' => ! $item['active'],
                    ])>{{ $item['label'] }}</span>

                    @if ($item['active'])
                        {{-- the row you are on is the one with a menu --}}
                        <x-icon name="dots" class="h-4 w-4 shrink-0 text-white/55" />
                    @elseif ($creates)
                        <span aria-hidden="true" class="shrink-0 text-[17px] leading-none text-white/30 transition duration-200 group-hover:text-orbit-gold-lit">＋</span>
                    @elseif ($item['count'])
                        <span class="shrink-0 text-[11px] font-bold tabular-nums text-white/35">{{ $item['count'] }}</span>
                    @endif
                </{{ $tag }}>
            @endforeach
        @endforeach
    </nav>

    {{-- ══ THE COMMAND DOCK ══
         Settings, help and the way out. Pinned, because the three things you
         reach for without reading are the three that must never move. --}}
    <div class="relative shrink-0 px-4 pb-4 pt-1">
        <div class="orbit-glass relative overflow-hidden rounded-[20px] p-1.5">
            <span aria-hidden="true" class="pointer-events-none absolute -bottom-8 -left-8 h-24 w-24 rounded-full bg-[radial-gradient(circle,rgba(216,184,79,0.16),transparent_70%)]"></span>

            <a href="{{ route('settings.index') }}"
               class="group relative flex h-10 items-center gap-3 rounded-[14px] px-3 transition duration-200 ease-out hover:bg-white/[0.07] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orbit-gold/60">
                <x-icon name="cog" class="h-[18px] w-[18px] shrink-0 text-white/50 transition group-hover:text-white/80" />
                <span class="flex-1 text-[13.5px] font-medium text-white/80 transition group-hover:text-white">Settings</span>
                <x-icon name="chevron" class="h-4 w-4 shrink-0 -rotate-90 text-white/30" />
            </a>

            <span title="Coming soon" aria-disabled="true"
                  class="group relative flex h-10 cursor-default items-center gap-3 rounded-[14px] px-3">
                <x-icon name="question" class="h-[18px] w-[18px] shrink-0 text-white/50" />
                <span class="flex-1 text-[13.5px] font-medium text-white/80">Help &amp; Support</span>
                <x-icon name="chevron" class="h-4 w-4 shrink-0 -rotate-90 text-white/30" />
            </span>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="group relative flex h-10 w-full items-center gap-3 rounded-[14px] px-3 transition duration-200 ease-out hover:bg-white/[0.07] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orbit-gold/60">
                    <x-icon name="power" class="h-[18px] w-[18px] shrink-0 text-white/50 transition group-hover:text-white/80" />
                    <span class="flex-1 text-left text-[13.5px] font-medium text-white/80 transition group-hover:text-white">Sign out</span>
                    <x-icon name="chevron" class="h-4 w-4 shrink-0 -rotate-90 text-white/30" />
                </button>
            </form>
        </div>
    </div>
</aside>
