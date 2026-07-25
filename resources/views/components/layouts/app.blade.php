@props(['title' => null, 'subtitle' => null, 'crumbs' => null, 'hideTitleRow' => false, 'railNav' => null])

@php
    // Platform-wide primary navigation — lives in the floating right rail.
    $nav = [
        ['Command Center', 'home', 'home', request()->routeIs('home')],
        ['Events', 'events.index', 'calendar', request()->routeIs('events.*')],
        ['Projects', 'projects.index', 'grid', request()->routeIs('projects.*')],
        ['Tasks', 'tasks.index', 'clipboard', request()->routeIs('tasks.*')],
        ['Finance', 'finance.index', 'currency', request()->routeIs('finance.*')],
        ['Sponsors', 'sponsors.index', 'star', request()->routeIs('sponsors.*')],
        ['Reports', 'reports.index', 'chart', request()->routeIs('reports.*')],
        ['Settings', 'settings.index', 'cog', request()->routeIs('settings.*')],
    ];
    $alertCount = \App\Models\EventApproval::where('status', 'pending')->count()
        + \App\Models\EventRisk::whereIn('status', ['open', 'escalated'])->count();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ \App\Support\ThemePolicy::for($module ?? null) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' — ' : '' }}{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=spectral:500,600,700,800&display=swap" rel="stylesheet">
    <link href="https://fonts.bunny.net/css?family=amiri:400,700&display=swap" rel="stylesheet">
    {{-- ORBIT type (Inter · Instrument Serif · JetBrains Mono) is self-hosted from
         @fontsource, bundled through resources/js/app.js — no CDN, no fallback. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-page font-sans text-ink antialiased">
    {{-- ORBIT icon defs — once per document; <x-orbit.icon> references them by id. --}}
    <x-orbit.sprite />

    <div class="min-h-screen">

        {{-- ══ 1. PREMIUM EXECUTIVE HEADER — dark navy, gold accent ══ --}}
        <header class="sticky top-0 z-40 border-b border-white/[0.06] bg-gradient-to-r from-navy-950 via-navy-900 to-navy-950">
            {{-- gold orbit accent --}}
            <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-gold-400/50 to-transparent"></div>
            <div class="pointer-events-none absolute -right-24 -top-24 h-56 w-56 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.12),transparent_65%)]"></div>

            <div class="relative flex h-[68px] items-center gap-4 px-4 lg:px-7">
                {{-- brand — or the event's own identity when inside an event --}}
                @isset($identity)
                    {{ $identity }}
                @else
                    <a href="{{ route('home') }}" class="shrink-0"><x-brand dark /></a>
                @endisset

                <div class="hidden h-8 w-px bg-white/10 md:block"></div>

                {{-- global search (⌘K palette trigger) --}}
                <div class="hidden min-w-0 flex-1 justify-center md:flex">
                    <div class="w-full max-w-md">
                        <livewire:command-palette />
                    </div>
                </div>

                {{-- action cluster --}}
                <div class="ml-auto flex shrink-0 items-center gap-1.5">
                    {{-- glowing gold create --}}
                    <a href="{{ route('events.create') }}"
                       class="mr-1 flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-gold-300 to-gold-500 text-lg font-bold text-navy-950 shadow-[0_0_0_4px_rgba(212,175,55,0.14),0_8px_20px_-6px_rgba(212,175,55,0.7)] transition hover:brightness-110"
                       title="Create a new event">＋</a>

                    <button type="button" class="relative flex h-10 w-10 items-center justify-center rounded-xl text-white/60 ring-1 ring-white/10 transition hover:bg-white/[0.06] hover:text-white" aria-label="Notifications">
                        <x-icon name="bell" class="h-5 w-5" />
                        @if ($alertCount > 0)
                            <span class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-gold-500 px-1 text-[0.55rem] font-bold text-navy-950 ring-2 ring-navy-950">{{ $alertCount }}</span>
                        @endif
                    </button>
                    <button type="button" class="hidden h-10 w-10 items-center justify-center rounded-xl text-white/60 ring-1 ring-white/10 transition hover:bg-white/[0.06] hover:text-white sm:flex" aria-label="Calendar">
                        <x-icon name="calendar" class="h-5 w-5" />
                    </button>
                    <button type="button" class="hidden h-10 w-10 items-center justify-center rounded-xl text-white/60 ring-1 ring-white/10 transition hover:bg-white/[0.06] hover:text-white sm:flex" aria-label="Messages">
                        <x-icon name="chat" class="h-5 w-5" />
                    </button>
                    <a href="{{ route('ai.index') }}" class="flex h-10 w-10 items-center justify-center rounded-xl text-gold-300 ring-1 ring-gold-400/30 transition hover:bg-gold-400/10 hover:text-gold-200" aria-label="AI Insights" title="AI Insights">
                        <x-icon name="sparkles" class="h-5 w-5" />
                    </a>

                    <div class="ml-1.5 hidden h-8 w-px bg-white/10 sm:block"></div>

                    {{-- profile --}}
                    <details class="group relative" x-data @click.outside="$el.open = false">
                        <summary class="flex cursor-pointer list-none items-center gap-2.5 rounded-full py-1 pl-1 pr-2 transition hover:bg-white/[0.06] [&::-webkit-details-marker]:hidden">
                            <x-user-avatar :user="auth()->user()" size="h-9 w-9" />
                            <span class="hidden text-left lg:block">
                                <span class="block text-[0.8rem] font-bold leading-tight text-white">{{ auth()->user()?->name }}</span>
                                <span class="block text-[0.6rem] text-white/50">{{ auth()->user()?->roleLabel() }}</span>
                            </span>
                            <x-icon name="chevron" class="hidden h-4 w-4 text-white/40 transition group-open:rotate-180 lg:block" />
                        </summary>
                        <div class="absolute right-0 z-40 mt-2 w-48 overflow-hidden rounded-xl border border-white/10 bg-navy-950 shadow-2xl">
                            <a href="{{ route('settings.index') }}" class="block px-4 py-2.5 text-sm text-white/75 transition hover:bg-white/5 hover:text-white">Settings</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 border-t border-white/10 px-4 py-2.5 text-left text-sm text-red-300 transition hover:bg-white/5">
                                    <x-icon name="logout" class="h-4 w-4" /> Log out
                                </button>
                            </form>
                        </div>
                    </details>
                </div>
            </div>
        </header>

        {{-- ══ 2. FLOATING RIGHT MODULE RAIL — primary navigation, every page.
             Icons only, labels fly out on hover; floats a margin off the right
             edge with a gap from the content (which is padded to clear it). ══ --}}
        @php
            // Event pages pass their module list as $railNav; global pages use the
            // platform nav (route names → hrefs here).
            $rail = $railNav ?? collect($nav)->map(fn ($n) => [$n[0], route($n[1]), $n[2], $n[3]])->all();
        @endphp
        <nav class="cc-panel fixed right-3 top-[84px] z-40 flex max-h-[calc(100vh-6rem)] w-[13rem] flex-col lg:right-5" aria-label="Primary">
            <div class="cc-head shrink-0">
                <div class="pointer-events-none absolute -right-6 -top-10 h-28 w-28 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.28),transparent_70%)]"></div>
                <x-icon name="grid" class="relative h-4 w-4 text-gold-400" />
                <span class="relative text-2xs font-bold uppercase tracking-[0.18em] text-white">Modules</span>
            </div>
            <div class="min-h-0 flex-1 space-y-0.5 overflow-y-auto p-2 scrollbar-none">
                @foreach ($rail as [$label, $href, $icon, $active])
                    <a href="{{ $href }}" @if ($active) aria-current="page" @endif
                       @class([
                           'flex items-center gap-2.5 rounded-xl px-3 py-2 text-[0.8rem] font-semibold transition',
                           'bg-navy-900 text-white shadow-[0_6px_16px_-6px_rgba(11,31,58,0.6)]' => $active,
                           'text-navy-600 hover:bg-navy-50 hover:text-navy-900' => ! $active,
                       ])>
                        <x-icon :name="$icon" @class(['h-4 w-4 shrink-0', 'text-gold-400' => $active, 'text-navy-400' => ! $active]) />
                        <span class="truncate">{{ $label }}</span>
                    </a>
                @endforeach
            </div>
        </nav>

        {{-- ══ 3. CONTENT — full-width fluid, padded on the right to clear the module box ══ --}}
        <main class="px-4 pb-14 pt-4 pr-4 lg:px-8 lg:pr-[15.5rem]">
            {{-- breadcrumb — always present for navigation context --}}
            @if ($title && ! request()->routeIs('home'))
                <div class="{{ $hideTitleRow ? 'mb-3' : 'mb-4' }}">
                    <x-crumbs :items="$crumbs ?? [['label' => 'Command Center', 'href' => route('home')], ['label' => $title]]" />

                    {{-- big title block — the hub renders its own identity, so it opts out --}}
                    @unless ($hideTitleRow)
                        <div class="mb-1 mt-1 flex items-center gap-2">
                            <span class="h-px w-6 bg-gold-400"></span>
                            <span class="eyebrow-gold">Elite Business Hub</span>
                        </div>
                        <h1 class="pf truncate text-[28px] font-bold leading-tight text-navy-900">{{ $title }}</h1>
                        @if ($subtitle)
                            <p class="mt-1 text-[15px] text-muted">{{ $subtitle }}</p>
                        @endif
                    @endunless
                </div>
            @endif

            @if (session('status'))
                <div class="mb-5 rounded-xl bg-track/10 px-4 py-3 text-sm font-medium text-emerald-700 ring-1 ring-track/30">
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
</body>
</html>
