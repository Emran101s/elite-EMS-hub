<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $event->name }} — Badges</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=playfair-display:700,800|instrument-sans:400,600,700,800&display=swap" rel="stylesheet">
    @include('events.partials.badge-css')
    @php [$pw, $ph] = App\Support\Badge::dimensions($event); @endphp
    <style>@page { size: A4 {{ $pw > $ph ? 'landscape' : 'portrait' }}; margin: 0; }</style>
</head>
<body>
    {{--
        Badges laid out to fill the page, cut lines and all. The same partial
        the designer previews, so what was designed on screen is what comes out
        of the printer.
    --}}
    <div class="badge-sheet">
        @foreach ($attendees as $attendee)
            @include('events.partials.badge', [
                'event' => $event,
                'attendee' => $attendee,
                'template' => $template,
                'qr' => $qrs[$attendee->id] ?? null,
                'logoSrc' => $logoSrc,
            ])
        @endforeach
    </div>
</body>
</html>
