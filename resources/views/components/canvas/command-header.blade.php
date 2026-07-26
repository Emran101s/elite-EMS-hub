@php
    $me = auth()->user();
    $first = str($me?->name ?? 'there')->before(' ')->toString();
    $initials = str($me?->name ?? 'EA')->explode(' ')->take(2)->map(fn ($p) => str($p)->substr(0, 1))->implode('');
    $hour = now()->hour;
    $part = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $counts = \App\Support\CommandCanvasData::headerCounts();
@endphp
@props([
    'user' => null,
    'role' => null,
    'greeting' => null,
])
{{-- Header / Command Bar — light, spacious, the only place two buttons compete.

     Every control here goes somewhere. The two menus are <details> elements, so
     they open, close and take keyboard focus without a line of framework code;
     the shell script only closes the other one and handles Escape. --}}
<header class="flex flex-wrap items-center gap-4 bg-white px-4 py-3 2xl:flex-nowrap 2xl:gap-6 2xl:px-6">

    {{-- Brand block: the one navy square, anchoring the whole screen --}}
    <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-3 rounded-2xl bg-cc-navy px-4 py-3 cc-lift-2">
        <span class="grid h-9 w-9 place-items-center rounded-lg bg-cc-gold">
            <span class="block h-4 w-4 rotate-45 rounded-[3px] border-2 border-cc-navy"></span>
        </span>
        <span class="leading-none">
            <span class="block text-[15px] font-extrabold tracking-[0.22em] text-white">ELITE</span>
            <span class="mt-1 block text-[8px] font-semibold tracking-[0.3em] text-cc-gold">BUSINESS HUB</span>
        </span>
    </a>

    {{-- Greeting --}}
    <div class="min-w-0 basis-full xl:basis-auto xl:flex-1 2xl:flex-none">
        <p class="text-[10px] font-bold uppercase tracking-[0.24em] text-cc-ink-3">Command Canvas</p>
        <h1 class="mt-1 truncate text-xl font-extrabold tracking-tight text-cc-navy xl:text-[26px]">{{ $greeting ?? $part.', '.$first }} <span class="ml-0.5">👋</span></h1>
        <p class="mt-0.5 truncate text-[12.5px] text-cc-ink-2">You are in command. Everything is connected.</p>
    </div>

    {{-- Global search — submits to the events index, which reads ?q= --}}
    <form action="{{ route('events.index') }}" method="GET"
          class="order-last flex h-12 w-full items-center gap-3 rounded-xl border border-cc-line bg-cc-mist px-4 transition focus-within:border-cc-blue focus-within:bg-white focus-within:ring-4 focus-within:ring-cc-blue/10 2xl:order-none 2xl:max-w-[400px] 2xl:flex-1">
        <button type="submit" class="grid shrink-0 place-items-center text-cc-ink-3 transition hover:text-cc-navy" aria-label="Search">
            <x-canvas.icon name="search" :size="17" />
        </button>
        <input data-command-search type="search" name="q" value="{{ request('q') }}"
               placeholder="Search events, people, venues, tasks, documents..."
               class="min-w-0 flex-1 bg-transparent text-[13.5px] text-cc-ink placeholder:text-cc-ink-3 focus:outline-none">
        <kbd class="shrink-0 rounded-md border border-cc-line bg-white px-1.5 py-0.5 text-[10px] font-bold text-cc-ink-3">⌘ K</kbd>
    </form>

    {{-- Actions --}}
    <div class="ml-auto flex flex-wrap items-center justify-end gap-1.5">
        <a href="{{ route('ai.index') }}" class="flex h-11 items-center gap-2 rounded-xl bg-cc-navy px-4 text-[13px] font-bold text-white transition hover:bg-cc-navy-2 cc-lift-2">
            <x-canvas.icon name="ai" :size="16" class="text-cc-gold" /> AI Director
        </a>

        <details data-menu class="relative">
            <summary class="flex h-11 cursor-pointer list-none items-center gap-2 rounded-xl bg-cc-gold px-4 text-[13px] font-bold text-cc-navy transition hover:bg-cc-gold-2 cc-lift-2">
                <x-canvas.icon name="plus" :size="16" /> Create <x-canvas.icon name="chev" :size="14" />
            </summary>
            <div class="absolute right-0 z-50 mt-2 w-[212px] overflow-hidden rounded-2xl border border-cc-line bg-white py-1.5 cc-lift-3">
                @foreach ([
                    ['Event', 'events', route('events.create')],
                    ['Client', 'people', route('clients.index')],
                    ['Supplier', 'vault', route('suppliers.index')],
                    ['Venue', 'pin', route('venues.index')],
                    ['Team member', 'people', route('team.index')],
                ] as [$label, $icon, $href])
                    <a href="{{ $href }}" class="flex items-center gap-2.5 px-3.5 py-2 text-[12.5px] font-semibold text-cc-ink transition hover:bg-cc-mist hover:text-cc-navy">
                        <x-canvas.icon :name="$icon" :size="14" class="text-cc-ink-3" /> New {{ $label }}
                    </a>
                @endforeach
            </div>
        </details>

        {{-- Alerts jump to the Live Signals panel; tasks and schedule to their pages. --}}
        @foreach ([
            ['bell', $counts['alerts'], route('home').'#signals', 'Alerts'],
            ['tasks', $counts['tasks'], route('tasks.index'), 'My tasks'],
            ['cal', null, route('events.index'), 'Schedule'],
        ] as [$icon, $count, $href, $label])
            <a href="{{ $href }}" title="{{ $label }}" aria-label="{{ $label }}"
               class="relative grid h-11 w-11 place-items-center rounded-xl border border-cc-line bg-white text-cc-ink-2 transition hover:border-cc-gold hover:text-cc-navy">
                <x-canvas.icon :name="$icon" :size="18" />
                @if ($count)
                    <span class="absolute -right-1 -top-1 grid h-[18px] min-w-[18px] place-items-center rounded-full bg-cc-risk px-1 text-[10px] font-bold text-white ring-2 ring-white">{{ $count }}</span>
                @endif
            </a>
        @endforeach

        <details data-menu class="relative">
            <summary class="flex cursor-pointer list-none items-center gap-2.5 rounded-xl border border-cc-line bg-white py-1.5 pl-1.5 pr-3 transition hover:border-cc-gold">
                <span class="grid h-9 w-9 place-items-center rounded-lg bg-cc-navy text-[12px] font-bold text-cc-gold">{{ $initials }}</span>
                <span class="hidden text-left leading-tight lg:block">
                    <span class="block text-[12.5px] font-bold text-cc-navy">{{ $user ?? $me?->name }}</span>
                    <span class="block text-[10.5px] text-cc-ink-3">{{ $role ?? $me?->roleLabel() }}</span>
                </span>
                <x-canvas.icon name="chev" :size="14" class="text-cc-ink-3" />
            </summary>
            <div class="absolute right-0 z-50 mt-2 w-[212px] overflow-hidden rounded-2xl border border-cc-line bg-white py-1.5 cc-lift-3">
                @foreach ([
                    ['My tasks', 'tasks', route('tasks.index')],
                    ['Team', 'people', route('team.index')],
                    ['Company', 'settings', route('company.index')],
                    ['Settings', 'settings', route('settings.index')],
                ] as [$label, $icon, $href])
                    <a href="{{ $href }}" class="flex items-center gap-2.5 px-3.5 py-2 text-[12.5px] font-semibold text-cc-ink transition hover:bg-cc-mist hover:text-cc-navy">
                        <x-canvas.icon :name="$icon" :size="14" class="text-cc-ink-3" /> {{ $label }}
                    </a>
                @endforeach
                <form method="POST" action="{{ route('logout') }}" class="mt-1 border-t border-cc-line pt-1">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2.5 px-3.5 py-2 text-left text-[12.5px] font-semibold text-cc-risk transition hover:bg-cc-risk/10">
                        <x-canvas.icon name="logout" :size="14" /> Sign out
                    </button>
                </form>
            </div>
        </details>
    </div>
</header>
