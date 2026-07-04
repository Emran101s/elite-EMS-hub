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
        <aside class="fixed inset-y-0 left-0 z-20 hidden w-64 flex-col border-r border-line bg-white lg:flex">
            <div class="px-6 pb-5 pt-6">
                <a href="{{ route('home') }}"><x-brand /></a>
            </div>

            <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 pb-4" aria-label="Main">
                @foreach (config('modules.nav') as $key => $module)
                    @php $active = request()->routeIs($module['route']) || request()->routeIs(str_replace('.index', '.*', $module['route'])); @endphp
                    <a href="{{ route($module['route']) }}"
                       @class([
                           'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
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
        <div class="lg:pl-64">
            <header class="flex items-center justify-between gap-4 px-6 pb-2 pt-6 lg:px-8">
                <div class="min-w-0">
                    <h1 class="truncate text-2xl font-bold text-navy-900">{{ $title ?? config('app.name') }}</h1>
                    @if ($subtitle)
                        <p class="mt-1 text-sm text-muted">{{ $subtitle }}</p>
                    @endif
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    <label class="relative hidden md:block">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted">
                            <x-icon name="search" class="h-4 w-4" />
                        </span>
                        <input type="search" placeholder="Search anything…" class="input w-64 pl-9" />
                    </label>

                    <button type="button" class="relative rounded-xl border border-line bg-white p-2.5 text-navy-600 transition hover:text-navy-900" aria-label="Notifications">
                        <x-icon name="bell" class="h-5 w-5" />
                    </button>
                    <button type="button" class="relative rounded-xl border border-line bg-white p-2.5 text-navy-600 transition hover:text-navy-900" aria-label="Messages">
                        <x-icon name="chat" class="h-5 w-5" />
                    </button>

                    <details class="group relative">
                        <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl border border-line bg-white py-1.5 pl-1.5 pr-3 transition hover:border-navy-200 [&::-webkit-details-marker]:hidden">
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-navy-900 text-sm font-bold text-gold-400">
                                {{ auth()->user()?->initials() }}
                            </span>
                            <span class="hidden text-left sm:block">
                                <span class="block text-sm font-semibold text-navy-900">{{ auth()->user()?->name }}</span>
                                <span class="block text-xs text-muted">Super Admin</span>
                            </span>
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

            <main class="px-6 py-6 lg:px-8">
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
