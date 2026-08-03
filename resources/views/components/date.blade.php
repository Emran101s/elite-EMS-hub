@props(['value', 'style' => 'short', 'empty' => '—'])

{{--
    One spelling per kind of date, everywhere.

    Thirty-eight different ->format() strings were doing five or six jobs
    between them — the same compact date shown as "6 Sep 2026" on one screen,
    "06 Sep 2026" on another, "Sep 6, 2026" on a third. Three named styles,
    picked to match what the platform already settled on as its convention
    (day-first, the middle-dot separator used everywhere a date and a time sit
    together):

      short     "6 Sep 2026"                     the everyday reading
      document  "6 September 2026"                an invoice or proposal's issued/due date
      long      "Sunday, 6 September 2026"        a certificate, a formal date line
      withTime  "6 Sep 2026 · 14:30"              an event log, a timestamp
--}}
@php
    $carbon = $value instanceof \Illuminate\Support\Carbon
        ? $value
        : ($value ? \Illuminate\Support\Carbon::parse($value) : null);

    $format = match ($style) {
        'document' => 'j F Y',
        'long' => 'l, j F Y',
        'withTime' => 'j M Y · H:i',
        default => 'j M Y',
    };
@endphp
@if ($carbon)<span {{ $attributes }}>{{ $carbon->format($format) }}</span>@else<span {{ $attributes }}>{{ $empty }}</span>@endif
