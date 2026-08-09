@props(['title' => 'Soft Command Gallery'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — Elite Orbit Soft Command</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="eo-gallery-shell">
    <div class="border-b border-eo-line/80 bg-eo-navy text-white">
        <div class="eo-gallery-canvas flex flex-wrap items-center justify-between gap-3 py-4">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-eo-gold-soft">Phase 1 · Design system</p>
                <p class="mt-1 text-[18px] font-semibold tracking-tight">Elite Orbit Soft Command</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="eo-pill bg-white/10 text-eo-gold-soft">Teal = action</span>
                <span class="eo-pill bg-white/10 text-white/80">Gold = brand</span>
                <a href="{{ route('design.soft-command-shell') }}" class="eo-btn-primary eo-btn-sm">Phase 2 shell →</a>
                <a href="{{ route('home') }}" class="eo-btn-ghost eo-btn-sm border-white/20 bg-transparent text-white hover:bg-white/10">← App</a>
            </div>
        </div>
    </div>

    <main class="eo-gallery-canvas space-y-10 pb-16">
        {{ $slot }}
    </main>
</body>
</html>
