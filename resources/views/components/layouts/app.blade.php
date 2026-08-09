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

{{-- Soft Command App Shell (Phase 2 approved) — live product chrome. --}}
<div class="eo-app-shell">
    <x-eo.mini-rail />
    <x-eo.context-sidebar />

    <section class="eo-workspace-shell">
        <x-eo.top-command-bar :crumbs="$crumbs" :title="$title" />

        <div class="eo-workspace-body">
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
        </div>
    </section>
</div>

<x-confirm-host />
</body>
</html>
