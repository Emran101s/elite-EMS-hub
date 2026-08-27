@props(['title' => null, 'subtitle' => null, 'crumbs' => null, 'hideTitleRow' => false, 'rightPanel' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' — ' : '' }}{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">
    {{-- Arabic face for bilingual contract and brief documents. --}}
    <link href="https://fonts.bunny.net/css?family=amiri:400,700&display=swap" rel="stylesheet">
    {{-- Playfair kept for legacy PDF/print surfaces that still reference it. --}}
    <link href="https://fonts.bunny.net/css?family=playfair-display:500,600,700,800,900&display=swap" rel="stylesheet">
    {{-- Instrument Sans (app.css's --font-sans token, the shell's chrome
         face) is self-hosted via vite.config.js's fonts plugin, not linked
         here — @vite(app.css) below already pulls in its @font-face rules. --}}
    @vite(['resources/css/app.css', 'resources/css/shell.css', 'resources/css/command-center.css', 'resources/css/event-hub.css', 'resources/css/hub-content.css', 'resources/js/app.js'])
    <x-clarity />
</head>
<body class="font-sans bg-page text-ink antialiased">

@php
    $crumbs ??= request()->routeIs('home') || ! $title
        ? null
        : collect([\App\Support\NavPanel::areaLabel(\App\Support\NavPanel::currentArea()), $title])
            ->unique()->map(fn (string $label) => ['label' => $label])->values()->all();
@endphp

{{-- Global shell, Phase 1 — navy/gold, floating nav + header, light
     workspace. `nav` drives the left nav below xl (1280px), where it's a
     slide-over rather than a static column. One state on the shell root
     because the trigger lives in the top bar and the nav is its sibling —
     and one INSTANCE of the nav, not a second copy for small screens: it
     reads NavPanel::sections(), which runs real counts, and rendering it
     twice would run them twice on every page. --}}
<div class="flex min-h-screen gap-3 p-3 sm:gap-4 sm:p-4" x-data="{ nav: false }" @keydown.escape.window="nav = false">
    <x-shell.nav />

    {{-- Only reachable while the drawer is open; at xl it never shows. --}}
    <button type="button" x-cloak x-show="nav" x-transition.opacity
            class="fixed inset-0 z-30 bg-navy-950/50 backdrop-blur-sm xl:hidden"
            @click="nav = false" tabindex="-1" aria-hidden="true"></button>

    <section class="flex min-w-0 flex-1 flex-col gap-3">
        <x-shell.top-bar :crumbs="$crumbs" :title="$title" />

        <div class="flex min-h-0 flex-1 gap-3">
            <x-shell.workspace>
                @unless ($hideTitleRow)
                    <x-eo.page-header :title="$title ?? config('app.name')" :subtitle="$subtitle" />
                @endunless

                @if (session('status'))
                    <x-eo.alert-card tone="ok" class="mb-5">{{ session('status') }}</x-eo.alert-card>
                @endif

                @if (session('error'))
                    <x-eo.alert-card tone="risk" class="mb-5">{{ session('error') }}</x-eo.alert-card>
                @endif

                <div class="pb-6">{{ $slot }}</div>
            </x-shell.workspace>

            <x-shell.right-panel :show="$rightPanel" />
        </div>
    </section>
</div>

<x-confirm-host />
</body>
</html>
