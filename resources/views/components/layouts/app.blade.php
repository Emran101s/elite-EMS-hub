@props(['title' => null, 'subtitle' => null, 'crumbs' => null, 'hideTitleRow' => false, 'railNav' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' — ' : '' }}{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=playfair-display:500,600,700,800,900&display=swap" rel="stylesheet">
    {{-- Arabic face for the bilingual contract and brief documents. --}}
    <link href="https://fonts.bunny.net/css?family=amiri:400,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-page font-sans text-ink antialiased">

{{--
    Two tiers: a rail that says which area you are in, a panel that says what
    is inside it, and the work to the right of both.

    The pill row this replaces could only ever show the top level, which is how
    five modules ended up behind a More menu — and that menu then spent months
    opening into a clipping box, which nobody noticed because nobody could see
    it. Everything is on the rail now.

    h-screen rather than min-h-screen so the panel scrolls its own list instead
    of making the page tall and pushing Settings off the bottom.
--}}
<div class="flex h-screen overflow-hidden bg-shell-navy-3">

    <x-app-rail />
    <x-app-panel />

    {{--
        main is the scroll container, so it is also what every sticky header
        inside a page measures against. The top padding therefore lives on the
        first child rather than on main itself: padding on the scroller pushes
        a sticky element down by that much and leaves a slot above it for the
        page to scroll through, which is exactly what the hub's tab strip was
        doing.
    --}}
    <main class="scrollbar-none m-3 min-w-0 flex-1 overflow-y-auto rounded-[22px] bg-canvas px-4 pb-4 shadow-[0_30px_80px_-40px_rgba(0,0,0,0.85)] lg:m-4 lg:px-6 lg:pb-6">

        @php
            // The rail already says which area you are in, so a trail back to
            // the Command Center is a hop nobody needs. What is worth saying is
            // where you are inside the area. $crumbs is an array of
            // ['label' => …, 'href' => …?]; the last is where you are, so it
            // never links anywhere.
            $crumbs ??= request()->routeIs('home') || ! $title
                ? null
                : collect([\App\Support\NavPanel::areaLabel(\App\Support\NavPanel::currentArea()), $title])
                    // "Events › Events" is a trail to where you already are.
                    ->unique()->map(fn (string $label) => ['label' => $label])->values()->all();
        @endphp

        <div class="pt-4 lg:pt-6"><x-app-tools :crumbs="$crumbs" /></div>

        @unless ($hideTitleRow)
            <header class="mb-5">
                @unless ($crumbs)
                    <div class="mb-1 flex items-center gap-2">
                        <span class="h-px w-6 bg-gold-400"></span>
                        <span class="eyebrow-gold">Elite Business Hub</span>
                    </div>
                @endunless

                <h1 class="pf text-[26px] font-bold leading-tight text-navy-900 sm:text-[32px]">{{ $title ?? config('app.name') }}</h1>
                @if ($subtitle)
                    <p class="mt-1 text-[14px] text-muted">{{ $subtitle }}</p>
                @endif
            </header>
        @endunless

        @if (session('status'))
            <div class="mb-5 rounded-xl bg-track/10 px-4 py-3 text-sm font-medium text-emerald-700 ring-1 ring-track/30">
                {{ session('status') }}
            </div>
        @endif

        <div class="pb-6">{{ $slot }}</div>
    </main>
</div>
</body>
</html>
