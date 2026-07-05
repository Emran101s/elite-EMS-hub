@props(['title' => null, 'subtitle' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title . ' — ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-page font-sans text-ink antialiased">
    <div class="min-h-screen">

        {{-- Sidebar --}}
        <aside class="fixed inset-y-0 left-0 z-20 hidden w-60 flex-col border-r border-line bg-white lg:flex">
            <div class="px-6 pb-5 pt-6">
                <a href="{{ route('home') }}"><x-brand /></a>
            </div>

            <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 pb-4" aria-label="Main">
                @foreach (config('modules.nav') as $key => $module)
                    @php $active = request()->routeIs($module['route']) || request()->routeIs(str_replace('.index', '.*', $module['route'])); @endphp
                    <a href="{{ route($module['route']) }}"
                       @class([
                           'flex h-12 items-center gap-3 rounded-xl px-3 text-sm font-medium transition',
                           'bg-gold-50 text-gold-700 ring-1 ring-gold-200' => $active,
                           'text-navy-600 hover:bg-navy-50 hover:text-navy-900' => ! $active,
                       ])
                       @if ($active) aria-current="page" @endif>
                        <x-icon :name="$module['icon']" class="h-5 w-5 shrink-0" />
                        {{ $module['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="border-t border-line p-4">
                <div class="card p-4 text-center">
                    <p class="text-[0.65rem] font-bold tracking-widest text-gold-600">✦ AI ASSISTANT</p>
                    <p class="mt-2 text-xs text-muted">Ask anything about your operations</p>
                    <a href="{{ route('ai.index') }}" class="btn-navy mt-3 w-full text-xs">✦ Ask AI</a>
                </div>
            </div>
        </aside>

        {{-- Main column --}}
        <div class="lg:pl-60">
            <header class="flex items-center justify-between gap-4 px-6 pb-4 pt-7 lg:px-8">
                <div class="min-w-0">
                    <h1 class="truncate text-[30px] font-bold leading-tight text-navy-900">{{ $title ?? config('app.name') }}</h1>
                    @if ($subtitle)
                        <p class="mt-1 text-[15px] text-muted">{{ $subtitle }}</p>
                    @endif
                </div>

                <div class="flex shrink-0 items-center gap-4">
                    <label class="relative hidden md:block">
                        <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-muted">
                            <x-icon name="search" class="h-4.5 w-4.5" />
                        </span>
                        <input type="search" placeholder="Search events, clients, venues…" class="input h-11 w-80 !rounded-full pl-11 xl:w-96" />
                    </label>

                    @php
                        // Real signals until a notification center lands: pending approvals + open/escalated risks.
                        $alertCount = \App\Models\EventApproval::where('status', 'pending')->count()
                            + \App\Models\EventRisk::whereIn('status', ['open', 'escalated'])->count();
                        $messageCount = 5; // placeholder until the messaging module ships
                    @endphp

                    <button type="button" class="relative p-1 text-navy-500 transition hover:text-navy-900" aria-label="Notifications">
                        <x-icon name="bell" class="h-6 w-6" />
                        @if ($alertCount > 0)
                            <span class="absolute -right-1.5 -top-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-warn px-1 text-[10px] font-bold text-white ring-2 ring-page">{{ $alertCount }}</span>
                        @endif
                    </button>
                    <button type="button" class="relative p-1 text-navy-500 transition hover:text-navy-900" aria-label="Messages">
                        <x-icon name="chat" class="h-6 w-6" />
                        <span class="absolute -right-1.5 -top-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-warn px-1 text-[10px] font-bold text-white ring-2 ring-page">{{ $messageCount }}</span>
                    </button>

                    <details class="group relative">
                        <summary class="flex cursor-pointer list-none items-center gap-3 rounded-full py-1 pl-1 pr-2 transition hover:bg-white [&::-webkit-details-marker]:hidden">
                            <x-user-avatar :user="auth()->user()" size="h-11 w-11" />
                            <span class="hidden text-left sm:block">
                                <span class="block text-[15px] font-bold text-navy-900">{{ auth()->user()?->name }}</span>
                                <span class="block text-xs text-muted">Super Admin</span>
                            </span>
                            <x-icon name="chevron" class="hidden h-4 w-4 text-navy-400 transition group-open:rotate-180 sm:block" />
                        </summary>
                        <div class="absolute right-0 z-30 mt-2 w-48 overflow-hidden rounded-xl border border-line bg-white shadow-lg">
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
            </header>

            <main class="px-6 pb-12 pt-1 lg:px-8">
                @if (session('status'))
                    <div class="mb-5 rounded-xl bg-track/10 px-4 py-3 text-sm font-medium text-emerald-700 ring-1 ring-track/30">
                        {{ session('status') }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
