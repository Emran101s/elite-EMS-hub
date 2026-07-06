@php
    // Command Spine items — label, route, active matcher.
    $spine = [
        ['label' => 'Command Center', 'route' => 'home', 'active' => request()->routeIs('home')],
        ['label' => 'Operations Hub', 'href' => route('home').'#operations-hub', 'active' => false, 'jump' => true],
        ['label' => 'Events', 'route' => 'events.index', 'active' => request()->routeIs('events.*')],
        ['label' => 'Projects', 'route' => 'projects.index', 'active' => request()->routeIs('projects.*')],
        ['label' => 'Tasks', 'route' => 'tasks.index', 'active' => request()->routeIs('tasks.*')],
        ['label' => 'CRM', 'route' => 'crm.index', 'active' => request()->routeIs('crm.*')],
        ['label' => 'Finance', 'route' => 'finance.index', 'active' => request()->routeIs('finance.*')],
        ['label' => 'Suppliers', 'route' => 'suppliers.index', 'active' => request()->routeIs('suppliers.*')],
        ['label' => 'Venues', 'route' => 'venues.index', 'active' => request()->routeIs('venues.*')],
        ['label' => 'Sponsors', 'route' => 'sponsors.index', 'active' => request()->routeIs('sponsors.*')],
        ['label' => 'Team', 'route' => 'team.index', 'active' => request()->routeIs('team.*')],
        ['label' => 'Reports', 'route' => 'reports.index', 'active' => request()->routeIs('reports.*')],
        ['label' => 'Settings', 'route' => 'settings.index', 'active' => request()->routeIs('settings.*')],
    ];
@endphp

<div class="flex h-full w-full flex-col overflow-hidden rounded-[28px] border border-line bg-white shadow-[0_20px_50px_rgba(11,31,58,0.10)]">

    {{-- ══ 1. Logo Area ══ --}}
    <div class="flex h-[88px] shrink-0 items-center border-b border-line px-5">
        <a href="{{ route('home') }}"><x-brand /></a>
    </div>

    {{-- ══ 2. Command Spine Navigation (scrolls if the screen is short) ══ --}}
    <div class="scrollbar-none min-h-0 flex-1 overflow-y-auto px-4 py-4">
        <p class="mb-2 pl-1.5 text-[0.6rem] font-bold uppercase tracking-[0.18em] text-muted">Command Spine</p>
        <nav class="relative" aria-label="Primary">
            {{-- the spine line --}}
            <span class="pointer-events-none absolute bottom-4 left-[11px] top-4 w-0.5 bg-gradient-to-b from-gold-300/50 via-line to-line"></span>

            @foreach ($spine as $item)
                <a href="{{ $item['href'] ?? route($item['route']) }}"
                   class="group relative flex h-[40px] items-center gap-3"
                   @if ($item['active']) aria-current="page" @endif>
                    {{-- node --}}
                    <span class="relative z-10 flex h-6 w-6 shrink-0 items-center justify-center">
                        @if ($item['active'])
                            <span class="h-[18px] w-[18px] rotate-45 rounded-[4px] bg-gold-500 shadow-[0_0_0_4px_rgba(212,175,55,0.18),0_0_14px_rgba(212,175,55,0.65)]"></span>
                        @else
                            <span class="h-3 w-3 rotate-45 rounded-[3px] bg-navy-200 transition duration-200 group-hover:scale-125 group-hover:bg-gold-400"></span>
                        @endif
                    </span>
                    {{-- label --}}
                    <span @class([
                        'truncate transition',
                        'text-[15px] font-bold text-navy-900' => $item['active'],
                        'text-sm font-medium text-navy-600 group-hover:text-navy-900' => ! $item['active'],
                    ])>{{ $item['label'] }}</span>
                    @if ($item['jump'] ?? false)
                        <span class="ml-auto text-[0.6rem] text-navy-300 transition group-hover:text-gold-500">↧</span>
                    @endif
                </a>
            @endforeach
        </nav>
    </div>

    {{-- ══ 3. Event Radar (pinned — always visible) ══ --}}
    <div class="shrink-0 border-t border-line px-4 pb-1 pt-3">
        <div class="mb-1.5 flex items-center justify-between pl-1.5">
            <div>
                <p class="text-[0.6rem] font-bold uppercase tracking-[0.18em] text-muted">Event Radar</p>
                <p class="text-[0.6rem] text-muted">Live event health</p>
            </div>
            <span class="flex items-center gap-1 text-[0.55rem] font-semibold text-emerald-600">
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-track"></span> Live
            </span>
        </div>

        <div class="scrollbar-none max-h-[240px] space-y-0.5 overflow-y-auto">
            @forelse ($radar->take(6) as $event)
                    @php $dot = ['track' => 'bg-track', 'warn' => 'bg-warn', 'risk' => 'bg-risk'][$event->radar_group]; @endphp
                    <a href="{{ route('events.hub', $event) }}"
                       class="group flex h-12 items-center gap-2.5 rounded-xl px-2 transition hover:-translate-y-px hover:bg-page"
                       title="{{ $event->name }} — {{ $event->radar_score }}% · {{ $event->radar_status }}">
                        <span class="relative flex shrink-0">
                            <span class="h-2.5 w-2.5 rounded-full {{ $dot }}"></span>
                            <span class="absolute inline-flex h-2.5 w-2.5 animate-ping rounded-full {{ $dot }} opacity-60 [animation-duration:2.5s]"></span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-1">
                                <span class="truncate text-xs font-semibold text-navy-900 group-hover:text-gold-700">{{ $event->name }}</span>
                                @if ($event->radar_vip)<span class="text-[0.6rem] text-gold-500" title="VIP">★</span>@endif
                            </span>
                            <span class="block truncate text-[0.6rem] text-muted">{{ $event->radar_status }}</span>
                        </span>
                        <x-health-ring :percent="$event->radar_score" :group="$event->radar_group"
                                       size="h-7 w-7" textSize="text-[8px]" dark class="shrink-0" />
                    </a>
                @empty
                    <p class="px-2 text-[0.65rem] text-muted">No active events on the radar.</p>
                @endforelse
            </div>
        </div>

    {{-- ══ 4. AI Command Core Card ══ --}}
    <div class="shrink-0 p-3">
        <div class="relative h-[210px] overflow-hidden rounded-[24px] p-4 text-white shadow-[0_14px_35px_rgba(11,31,58,0.30)]"
             style="background: radial-gradient(circle at 30% 20%, #16294A, #0B1F3A 75%);">
            {{-- radar animation --}}
            <div class="core-glow pointer-events-none absolute -right-8 -top-8 h-32 w-32 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.4),transparent_70%)]"></div>
            <div class="pointer-events-none absolute -right-4 -top-4 h-20 w-20 animate-ping rounded-full border border-gold-400/25 [animation-duration:3.5s]"></div>

            <div class="relative flex items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/5 ring-1 ring-gold-400/40">
                    <svg class="h-5 w-5" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                        <rect x="20" y="3.5" width="23.3" height="23.3" rx="4" transform="rotate(45 20 3.5)" stroke="#D4AF37" stroke-width="2.5"/>
                        <rect x="20" y="12.5" width="10.6" height="10.6" rx="2" transform="rotate(45 20 12.5)" fill="#D4AF37"/>
                    </svg>
                </span>
                <div>
                    <p class="text-[0.62rem] font-bold tracking-[0.14em] text-gold-300">AI COMMAND CORE</p>
                    <p class="flex items-center gap-1.5 text-[0.6rem] text-white/70">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-track"></span> All Systems Operational
                    </p>
                </div>
            </div>

            <div class="relative mt-3.5 grid grid-cols-3 gap-1.5">
                @foreach ([
                    ['value' => $core['events'], 'label' => 'Events Active', 'tone' => 'text-white'],
                    ['value' => $core['risks'], 'label' => 'Risks Detected', 'tone' => $core['risks'] > 0 ? 'text-red-400' : 'text-white'],
                    ['value' => $core['approvals'], 'label' => 'Pending Approvals', 'tone' => $core['approvals'] > 0 ? 'text-gold-300' : 'text-white'],
                ] as $stat)
                    <div class="rounded-xl bg-white/5 px-2 py-2 text-center ring-1 ring-white/10">
                        <p class="text-base font-bold {{ $stat['tone'] }}">{{ $stat['value'] }}</p>
                        <p class="mt-0.5 text-[0.5rem] leading-tight text-white/60">{{ $stat['label'] }}</p>
                    </div>
                @endforeach
            </div>

            <a href="{{ route('ai.index') }}" class="relative mt-3.5 flex h-9 w-full items-center justify-center gap-2 rounded-xl bg-gold-500 text-xs font-bold text-navy-900 transition hover:bg-gold-400">
                ✦ Ask AI
            </a>
        </div>
    </div>
</div>
