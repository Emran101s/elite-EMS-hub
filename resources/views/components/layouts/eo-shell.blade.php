@props(['title' => 'Soft Command Shell', 'crumbs' => null])

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
<body class="eo-font antialiased">
    <x-eo.app-shell :title="$title" :crumbs="$crumbs">
        {{ $slot }}
    </x-eo.app-shell>
    <x-confirm-host />
</body>
</html>
