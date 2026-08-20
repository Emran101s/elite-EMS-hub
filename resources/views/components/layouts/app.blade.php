@props(['title' => null, 'subtitle' => null, 'crumbs' => null, 'hideTitleRow' => false, 'railNav' => null])

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
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="eo-font bg-eo-navy-deep text-eo-text antialiased">

@php
    $crumbs ??= request()->routeIs('home') || ! $title
        ? null
        : collect([\App\Support\NavPanel::areaLabel(\App\Support\NavPanel::currentArea()), $title])
            ->unique()->map(fn (string $label) => ['label' => $label])->values()->all();
@endphp

{{-- Soft Command App Shell (Phase 2 approved) — live product chrome.

     `nav` drives the context sidebar below 1280px, where it is a slide-over
     rather than a column. One state on the shell because the trigger lives in
     the top bar and the panel is its sibling — and one INSTANCE of the sidebar,
     not a second copy for small screens: it runs a dozen counts to build the
     smart views, and rendering it twice would run them twice on every page. --}}
<div class="eo-app-shell" x-data="{ nav: false }" @keydown.escape.window="nav = false">
    <x-eo.mini-rail />
    <x-eo.context-sidebar />

    {{-- Only reachable while the drawer is open; above 1280px it never shows. --}}
    <button type="button" class="eo-nav-scrim" x-cloak x-show="nav" x-transition.opacity
            @click="nav = false" tabindex="-1" aria-hidden="true"></button>

    <section class="eo-workspace-shell">
        <x-eo.top-command-bar :crumbs="$crumbs" :title="$title" />

        <main class="eo-workspace-body">
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
        </main>
    </section>
</div>

<x-confirm-host />
</body>
</html>
