@php
    use App\Support\NavPanel;

    $area = NavPanel::currentArea();
    $sections = NavPanel::sections($area);
    $tree = NavPanel::tree($area);
    $user = auth()->user();

    // The status pill counts what is genuinely running, not every record —
    // "12 Active Events" has to survive being checked.
    $activeEvents = $tree->sum(fn ($g) => $g['events']->count());
@endphp

{{--
    THE EXPANDED PANEL — what is inside the area you are in.

    Dark, because it belongs to the rail beside it: together they are the one
    instrument in a product that is otherwise white. The workspace to the right
    stays light, which is what keeps the balance at roughly four parts light to
    one dark rather than turning this into a dark app.

    Everything on it is glass over the same navy: the profile card, the status
    pill, the selected row, the dock. Gold appears in exactly three places —
    the section rules, the selected row, and the connector from the rail — so
    it still means "here" by the time you reach the bottom.
--}}
<aside class="orbit-panel orbit-field relative hidden w-[318px] shrink-0 flex-col overflow-hidden rounded-[26px] shadow-[0_24px_60px_-28px_rgba(3,10,25,0.9)] xl:flex"
       aria-label="{{ NavPanel::AREAS[$area]['label'] ?? 'Navigation' }}">

    {{-- the decorative field: arcs and dust, 4–12% so it is texture --}}
    <span aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
        <span class="absolute -left-24 top-16 aspect-square w-[320px] rounded-full border border-orbit-gold/[0.06]"></span>
        <span class="absolute -right-32 bottom-24 aspect-square w-[380px] rounded-full border border-orbit-cyan/[0.05]"></span>
        @foreach ([[14, 30], [78, 18], [40, 66], [88, 52], [24, 84]] as [$x, $y])
            <span class="absolute h-[2px] w-[2px] rounded-full bg-orbit-gold-lit/25" style="left: {{ $x }}%; top: {{ $y }}%"></span>
        @endforeach
    </span>

    {{-- ══ WHO YOU ARE ══ --}}
    <div class="relative shrink-0 p-3.5 pb-2.5">
        <details class="group/user relative" data-menu>
            <summary class="orbit-glass orbit-glass-hover flex cursor-pointer list-none items-center gap-3 rounded-[22px] px-3.5 py-3 text-left transition duration-200 ease-out hover:-translate-y-px [&::-webkit-details-marker]:hidden">
                <span class="relative shrink-0">
                    <x-user-avatar :user="$user" size="h-10 w-10" />
                    <span aria-hidden="true" class="absolute inset-0 rounded-full ring-1 ring-orbit-gold/40"></span>
                </span>

                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-1.5">
                        <span class="truncate text-[13.5px] font-bold text-white">{{ $user?->name }}</span>
                        {{-- the crown: the owner of the workspace, not a rank --}}
                        <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 shrink-0 text-orbit-gold-lit" fill="currentColor" aria-hidden="true">
                            <path d="M3 8l4 3 5-6 5 6 4-3v9a1 1 0 01-1 1H4a1 1 0 01-1-1V8z"/>
                        </svg>
                    </span>
                    <span class="block truncate text-[11px] font-semibold text-orbit-gold/90">{{ $user?->title ?: 'Founder & CEO' }}</span>
                    <span class="block truncate text-[10.5px] text-orbit-dim">{{ $user?->email }}</span>
                </span>

                <x-icon name="chevron" class="h-3.5 w-3.5 shrink-0 text-white/35 transition duration-200 group-open/user:rotate-180" />
            </summary>

            <div class="orbit-glass absolute inset-x-3.5 z-40 mt-1.5 overflow-hidden rounded-2xl py-1">
                <a href="{{ route('settings.index') }}" class="block px-4 py-2 text-[12.5px] font-medium text-white/80 transition hover:bg-white/10 hover:text-white">Settings</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full px-4 py-2 text-left text-[12.5px] font-medium text-white/80 transition hover:bg-white/10 hover:text-white">Sign out</button>
                </form>
            </div>
        </details>
    </div>

    {{-- ══ WHAT IS RUNNING ══ --}}
    @if ($activeEvents > 0 && \Illuminate\Support\Facades\Route::has('events.index'))
        <div class="relative shrink-0 px-3.5 pb-3">
            <a href="{{ route('events.index') }}"
               class="orbit-glass orbit-glass-hover flex items-center gap-2.5 rounded-[18px] px-3.5 py-2.5 transition duration-200 ease-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orbit-gold/60">
                <span class="orbit-pulse h-2 w-2 shrink-0 rounded-full bg-orbit-online"></span>
                <span class="min-w-0 flex-1 truncate text-[12.5px] font-semibold text-white">
                    {{ $activeEvents }} Active {{ \Illuminate\Support\Str::plural('Event', $activeEvents) }}
                </span>
                <x-icon name="chevron" class="h-3.5 w-3.5 shrink-0 -rotate-90 text-white/35" />
            </a>
        </div>
    @endif

    {{-- ══ THE AREA ══ --}}
    <div class="scrollbar-none relative min-h-0 flex-1 overflow-y-auto px-3.5 pb-3">

        @foreach ($sections as $section)
            {{-- Section rule: the label, a hairline that runs to the edge, and a
                 gold dot to stop it. Cheaper than a divider and it says where
                 the group ends. --}}
            <div class="mb-1.5 mt-3 flex items-center gap-2.5 px-1">
                <span class="shrink-0 text-[9.5px] font-bold uppercase tracking-[0.22em] text-orbit-gold">{{ $section['label'] }}</span>
                <span aria-hidden="true" class="h-px flex-1 bg-gradient-to-r from-orbit-gold/28 to-transparent"></span>
                <span aria-hidden="true" class="h-1 w-1 shrink-0 rounded-full bg-orbit-gold/60"></span>
            </div>

            @foreach ($section['items'] as $item)
                <a href="{{ $item['href'] }}"
                   @if ($item['active']) aria-current="page" @endif
                   @class([
                       'group relative mb-1 flex h-[46px] w-full items-center gap-3 rounded-[17px] px-3 transition duration-200 ease-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orbit-gold/60',
                       'orbit-selected' => $item['active'],
                       'hover:bg-white/[0.06]' => ! $item['active'],
                   ])>
                    <x-icon :name="$item['icon']" @class([
                        'h-[17px] w-[17px] shrink-0 transition duration-200 ease-out',
                        'text-orbit-gold-lit' => $item['active'],
                        'text-white/40 group-hover:translate-x-0.5 group-hover:text-white/80' => ! $item['active'],
                    ]) />

                    <span @class([
                        'min-w-0 flex-1 truncate text-[13px] transition duration-200 ease-out',
                        'font-bold text-white' => $item['active'],
                        'font-medium text-white/70 group-hover:translate-x-0.5 group-hover:text-white' => ! $item['active'],
                    ])>{{ $item['label'] }}</span>

                    @isset($item['count'])
                        <span class="shrink-0 text-[11px] font-bold tabular-nums text-white/35">{{ $item['count'] }}</span>
                    @endisset

                    {{-- A row that makes something says so on the right. --}}
                    @if (str_contains($item['href'], '/create') || str_contains($item['href'], '/new'))
                        <span aria-hidden="true" class="shrink-0 text-[15px] leading-none text-white/30 transition duration-200 group-hover:text-orbit-gold-lit">＋</span>
                    @endif

                    {{-- The selected row gets the action affordance; a row you
                         are not on does not need one. --}}
                    @if ($item['active'])
                        <span aria-hidden="true" class="shrink-0 text-[13px] leading-none text-white/40">⋯</span>
                    @endif
                </a>
            @endforeach
        @endforeach

        {{-- ══ THE PORTFOLIO ══ --}}
        @if ($tree->isNotEmpty())
            <div class="mb-1.5 mt-4 flex items-center gap-2.5 px-1">
                <span class="shrink-0 text-[9.5px] font-bold uppercase tracking-[0.22em] text-orbit-gold">Portfolio</span>
                <span aria-hidden="true" class="h-px flex-1 bg-gradient-to-r from-orbit-gold/28 to-transparent"></span>
                @if (\Illuminate\Support\Facades\Route::has('events.create'))
                    <a href="{{ route('events.create') }}" title="New event" aria-label="New event"
                       class="grid h-5 w-5 shrink-0 place-items-center rounded-full text-white/40 transition duration-200 hover:bg-white/10 hover:text-orbit-gold-lit">＋</a>
                @else
                    <span aria-hidden="true" class="h-1 w-1 shrink-0 rounded-full bg-orbit-gold/60"></span>
                @endif
            </div>

            <div class="orbit-glass rounded-[20px] p-2">
                @foreach ($tree as $group)
                    {{-- A short portfolio opens itself. Collapsing three groups
                         to save room the panel does not need only hides the
                         work and leaves a dark hole where the tree should be. --}}
                    <details class="group/branch" @if ($loop->first || $activeEvents <= 12) open @endif>
                        <summary class="flex cursor-pointer list-none items-center gap-2.5 rounded-xl px-2 py-1.5 transition duration-200 hover:bg-white/[0.07] [&::-webkit-details-marker]:hidden">
                            <span aria-hidden="true" class="text-[9px] text-white/30 transition duration-200 group-open/branch:rotate-90">▶</span>
                            <span aria-hidden="true" class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $group['color'] }}; box-shadow: 0 0 8px {{ $group['color'] }}66"></span>
                            <span class="min-w-0 flex-1 truncate text-[12px] font-bold text-white/85">{{ $group['label'] }}</span>
                            <span class="shrink-0 text-[10.5px] font-bold tabular-nums text-white/35">{{ $group['events']->count() }}</span>
                        </summary>

                        {{-- One hairline the children hang off, which is what
                             stops a tree reading as a flat list. --}}
                        <div class="ms-[13px] border-s border-white/10 ps-2.5">
                            @foreach ($group['events'] as $event)
                                <a href="{{ route('events.hub', $event) }}"
                                   class="group/ev flex items-center gap-2.5 rounded-xl px-2 py-1.5 transition duration-200 hover:bg-white/[0.07]">
                                    <x-icon name="folder" class="h-3.5 w-3.5 shrink-0 text-white/30 transition group-hover/ev:text-orbit-gold/80" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-[12px] font-medium text-white/80">{{ $event->name }}</span>
                                        <span class="block truncate text-[10px] text-orbit-dim">{{ $event->client?->name ?? 'No client' }}</span>
                                    </span>
                                    @if ($event->open_tasks)
                                        <span class="shrink-0 text-[10.5px] font-bold tabular-nums text-white/35">{{ $event->open_tasks }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ══ THE COMMAND DOCK ══
         Floating clear of the bottom edge, because a dock flush to the frame
         reads as the end of a list rather than a thing of its own. --}}
    <div class="relative shrink-0 p-3.5 pt-2">
        <div class="orbit-glass relative overflow-hidden rounded-[24px] p-1.5">
            <span aria-hidden="true" class="pointer-events-none absolute -bottom-6 -left-6 h-20 w-20 rounded-full bg-[radial-gradient(circle,rgba(216,184,79,0.22),transparent_70%)]"></span>

            @php
                $dock = [
                    ['Settings', 'cog', route('settings.index'), null],
                    ['Help & Support', 'chat', null, null],
                ];
            @endphp

            @foreach ($dock as [$label, $icon, $href, $_])
                @if ($href)
                    <a href="{{ $href }}"
                       class="group relative flex items-center gap-3 rounded-2xl px-3 py-2.5 transition duration-200 ease-out hover:bg-white/[0.08] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orbit-gold/60">
                        <x-icon :name="$icon" class="h-[16px] w-[16px] shrink-0 text-white/40 transition group-hover:text-orbit-gold-lit" />
                        <span class="min-w-0 flex-1 truncate text-[12.5px] font-medium text-white/75 transition group-hover:text-white">{{ $label }}</span>
                        <x-icon name="chevron" class="h-3 w-3 shrink-0 -rotate-90 text-white/25" />
                    </a>
                @else
                    <button type="button" disabled
                            class="group relative flex w-full cursor-not-allowed items-center gap-3 rounded-2xl px-3 py-2.5 opacity-45"
                            title="Coming soon">
                        <x-icon :name="$icon" class="h-[16px] w-[16px] shrink-0 text-white/40" />
                        <span class="min-w-0 flex-1 truncate text-left text-[12.5px] font-medium text-white/75">{{ $label }}</span>
                        <x-icon name="chevron" class="h-3 w-3 shrink-0 -rotate-90 text-white/25" />
                    </button>
                @endif
            @endforeach

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="group relative flex w-full items-center gap-3 rounded-2xl px-3 py-2.5 transition duration-200 ease-out hover:bg-white/[0.08] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orbit-gold/60">
                    <x-icon name="logout" class="h-[16px] w-[16px] shrink-0 text-white/40 transition group-hover:text-orbit-gold-lit" />
                    <span class="min-w-0 flex-1 truncate text-left text-[12.5px] font-medium text-white/75 transition group-hover:text-white">Sign out</span>
                    <x-icon name="chevron" class="h-3 w-3 shrink-0 -rotate-90 text-white/25" />
                </button>
            </form>
        </div>
    </div>
</aside>
