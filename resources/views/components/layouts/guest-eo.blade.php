@props([
    'title' => null,
    // Registration has two-column rows and needs the room; check-in and
    // sign-in want a narrow card. The page says which it is.
    'width' => 'max-w-md',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title . ' — ' : '' }}{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class=" min-h-screen bg-page text-ink antialiased">
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-10">
        {{-- Soft Command glow — teal and gold, the same accent pair as the rest of the platform. --}}
        <div class="pointer-events-none absolute -top-40 left-1/2 h-96 w-[42rem] -translate-x-1/2 rounded-full bg-gold-50/15 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-52 left-1/4 h-96 w-96 rounded-full bg-gold-500/10 blur-3xl"></div>

        <div class="relative w-full {{ $width }}">
            <div class="mb-8 flex justify-center">
                <span class="inline-flex items-center gap-2.5">
                    <span class="relative grid h-10 w-10 place-items-center rounded-2xl bg-gradient-to-b from-gold-300/25 to-transparent">
                        <svg viewBox="0 0 24 24" class="h-6 w-6 text-gold-600" fill="currentColor" aria-hidden="true">
                            <path d="M12 1.6l2.35 7.4 7.4 2.35-7.4 2.35-2.35 7.4-2.35-7.4L2.25 11.35l7.4-2.35z"/>
                        </svg>
                    </span>
                    <span class="flex flex-col leading-none">
                        <span class="text-lg font-bold tracking-[0.24em] text-ink">ELITE</span>
                        <span class="mt-1 text-[0.55rem] font-semibold tracking-[0.3em] text-gold-700">BUSINESS&nbsp;HUB</span>
                    </span>
                </span>
            </div>

            {{ $slot }}

            <p class="mt-6 text-center text-xs text-muted">© {{ date('Y') }} Elite Business Hub</p>
        </div>
    </div>
</body>
</html>
