@php
    // The platform's navigation, as a row of pills. Replaces the Command Spine
    // sidebar: same destinations, none of the dark mass, and every page gets
    // back the 292px the sidebar used to hold.
    //
    // The two things the spine carried besides links — the Event Radar and the
    // AI Command Core — did not survive as panels, so they live here as an
    // icon-button dropdown and a gold Ask AI pill. Nothing was dropped.
    $nav = [
        ['Command Center', 'home', request()->routeIs('home')],
        ['Events', 'events.index', request()->routeIs('events.*')],
        ['Projects', 'projects.index', request()->routeIs('projects.*')],
        ['Tasks', 'tasks.index', request()->routeIs('tasks.*')],
        ['Finance', 'finance.index', request()->routeIs('finance.*')],
        ['Sponsors', 'sponsors.index', request()->routeIs('sponsors.*')],
        ['Reports', 'reports.index', request()->routeIs('reports.*')],
    ];

    $alertCount = \App\Models\EventApproval::where('status', 'pending')->count()
        + \App\Models\EventRisk::whereIn('status', ['open', 'escalated'])->count();
@endphp

<header class="sticky top-0 z-40 border-b border-line/70 bg-page/85 backdrop-blur-lg">
    <div class="flex flex-wrap items-center gap-x-5 gap-y-3 px-4 py-3 lg:px-7">

        {{-- brand --}}
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5">
            <span class="grid h-8 w-8 place-items-center rounded-[10px] bg-navy-900">
                <span class="block h-2.5 w-2.5 rotate-45 rounded-[1px] border-2 border-gold-400"></span>
            </span>
            <span class="leading-none">
                <span class="block text-[12.5px] font-extrabold tracking-[0.2em] text-navy-900">ELITE</span>
                <span class="mt-[3px] block text-[7.5px] font-semibold tracking-[0.24em] text-gold-600">BUSINESS HUB</span>
            </span>
        </a>

        {{-- the pills --}}
        <nav class="scrollbar-none order-last flex w-full min-w-0 items-center gap-0.5 overflow-x-auto lg:order-none lg:w-auto lg:flex-1" aria-label="Primary">
            @foreach ($nav as [$label, $route, $active])
                <a href="{{ route($route) }}"
                   @if ($active) aria-current="page" @endif
                   @class([
                       'inline-flex h-9 shrink-0 items-center whitespace-nowrap rounded-full px-3 text-[12.5px] transition',
                       'bg-navy-900 font-semibold text-white' => $active,
                       'font-medium text-navy-600 hover:bg-white hover:text-navy-900' => ! $active,
                   ])>{{ $label }}</a>
            @endforeach
        </nav>

        {{-- tools --}}
        <div class="ml-auto flex shrink-0 items-center gap-2">
            <livewire:command-palette />

            {{-- Event Radar, rehomed from the sidebar into a dropdown. --}}
            <x-event-radar />

            <a href="{{ route('ai.index') }}"
               class="hidden h-10 items-center gap-1.5 rounded-full bg-gold-500 px-3.5 text-[12.5px] font-bold text-navy-950 shadow-[0_6px_16px_-8px_rgba(212,175,55,0.9)] transition hover:bg-gold-400 sm:inline-flex">
                ✦<span class="hidden xl:inline">Ask AI</span>
            </a>

            <a href="{{ route('home') }}#live-alerts" title="Alerts"
               class="relative grid h-10 w-10 place-items-center rounded-full bg-white text-navy-600 shadow-[0_2px_10px_-4px_rgba(11,31,58,0.25)] transition hover:text-navy-900">
                <x-icon name="bell" class="h-[18px] w-[18px]" />
                @if ($alertCount > 0)
                    <span class="absolute -right-0.5 -top-0.5 grid h-[17px] min-w-[17px] place-items-center rounded-full bg-risk px-1 text-[9px] font-bold text-white ring-2 ring-page">{{ $alertCount }}</span>
                @endif
            </a>

            <details class="group relative">
                <summary class="flex h-10 cursor-pointer list-none items-center gap-2 rounded-full bg-white py-1 pl-1 pr-3 shadow-[0_2px_10px_-4px_rgba(11,31,58,0.25)] [&::-webkit-details-marker]:hidden">
                    <x-user-avatar :user="auth()->user()" size="h-8 w-8" />
                    <span class="hidden text-[12.5px] font-semibold text-navy-900 md:block">{{ str(auth()->user()?->name)->before(' ') }}</span>
                    <x-icon name="chevron" class="h-3.5 w-3.5 text-navy-400 transition group-open:rotate-180" />
                </summary>
                <div class="absolute right-0 z-40 mt-2 w-52 overflow-hidden rounded-2xl border border-line bg-white shadow-lg">
                    <p class="border-b border-line px-4 py-2.5">
                        <span class="block text-[13px] font-bold text-navy-900">{{ auth()->user()?->name }}</span>
                        <span class="block text-[11px] text-muted">{{ auth()->user()?->roleLabel() }}</span>
                    </p>
                    <a href="{{ route('team.index') }}" class="block px-4 py-2.5 text-sm text-navy-700 hover:bg-navy-50">Team</a>
                    <a href="{{ route('settings.index') }}" class="block px-4 py-2.5 text-sm text-navy-700 hover:bg-navy-50">Settings</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 border-t border-line px-4 py-2.5 text-left text-sm text-risk hover:bg-navy-50">
                            <x-icon name="logout" class="h-4 w-4" /> Log out
                        </button>
                    </form>
                </div>
            </details>
        </div>
    </div>
</header>
