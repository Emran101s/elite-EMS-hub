@props([
    'title' => null,
    // Sign-in wants a narrow card; a registration form has two-column rows and
    // needs the room, so the page says which it is.
    'width' => 'max-w-md',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title . ' — ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-navy-950 font-sans text-ink antialiased">
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden px-4">
        {{-- Subtle blueprint-style glow --}}
        <div class="pointer-events-none absolute -top-40 left-1/2 h-96 w-[42rem] -translate-x-1/2 rounded-full bg-gold-500/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-52 left-1/4 h-96 w-96 rounded-full bg-navy-500/20 blur-3xl"></div>

        <div class="relative w-full {{ $width }}">
            <div class="mb-8 flex justify-center">
                <x-brand dark />
            </div>
            <div class="card p-8">
                {{ $slot }}
            </div>
            <p class="mt-6 text-center text-xs text-navy-300">© {{ date('Y') }} Elite Business Hub</p>
        </div>
    </div>
</body>
</html>
