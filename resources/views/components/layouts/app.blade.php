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
    <div class="min-h-screen">

        {{-- Navigation: a row of pills across the top. The Command Spine sidebar
             is gone — it was the largest dark mass in the platform and it held
             292px hostage on every screen. --}}
        <x-top-nav />

        <header class="px-4 pb-4 pt-6 lg:px-7">
            @php
                // Home is the root, so it wears the brand eyebrow; every other
                // page gets a trail back to it, derived when none was passed.
                $crumbs ??= request()->routeIs('home') || ! $title
                    ? null
                    : [['label' => 'Command Center', 'href' => route('home')], ['label' => $title]];
            @endphp

            @if ($crumbs)
                {{-- $crumbs is an array of ['label' => …, 'href' => …?]; the last
                     one is where you are, so it never links anywhere. --}}
                <nav aria-label="Breadcrumb" class="mb-1.5 flex flex-wrap items-center gap-1.5 text-xs text-muted">
                    @foreach ($crumbs as $crumb)
                        @if (! $loop->first)<span class="text-navy-200">›</span>@endif
                        @if (($crumb['href'] ?? null) && ! $loop->last)
                            <a href="{{ $crumb['href'] }}" class="transition hover:text-navy-900">{{ $crumb['label'] }}</a>
                        @else
                            <span class="font-semibold text-navy-700">{{ $crumb['label'] }}</span>
                        @endif
                    @endforeach
                </nav>
            @else
                <div class="mb-1 flex items-center gap-2">
                    <span class="h-px w-6 bg-gold-400"></span>
                    <span class="eyebrow-gold">Elite Business Hub</span>
                </div>
            @endif

            @unless ($hideTitleRow)
                <h1 class="pf text-[26px] font-bold leading-tight text-navy-900 sm:text-[34px]">{{ $title ?? config('app.name') }}</h1>
                @if ($subtitle)
                    <p class="mt-1 text-[15px] text-muted">{{ $subtitle }}</p>
                @endif
            @endunless
        </header>

        <main class="px-4 pb-12 lg:px-7">
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
