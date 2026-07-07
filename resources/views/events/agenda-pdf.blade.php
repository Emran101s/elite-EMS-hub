<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #0F172A; font-size: 11px; }
        .cover { background: {{ $theme['primary'] }}; color: #fff; padding: 28px 32px; }
        .cover .brand { font-size: 10px; letter-spacing: 3px; color: {{ $theme['accent'] }}; font-weight: bold; }
        .cover h1 { font-size: 26px; margin-top: 6px; }
        .cover .meta { margin-top: 8px; font-size: 11px; color: rgba(255,255,255,0.8); }
        .accent-bar { height: 5px; background: {{ $theme['accent'] }}; }
        .day { margin: 22px 32px 0; }
        .day-title { font-size: 14px; font-weight: bold; color: {{ $theme['primary'] }}; border-bottom: 2px solid {{ $theme['accent'] }}; padding-bottom: 5px; margin-bottom: 8px; }
        .day-date { font-size: 10px; color: #64748B; font-weight: normal; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: 1px; color: #64748B; padding: 5px 8px; border-bottom: 1px solid #E2E8F0; }
        td { padding: 7px 8px; border-bottom: 1px solid #F1F5F9; vertical-align: top; }
        .time { font-weight: bold; color: {{ $theme['primary'] }}; white-space: nowrap; }
        .title { font-weight: bold; }
        .sub { color: #64748B; font-size: 9px; }
        .pill { display: inline-block; font-size: 7px; font-weight: bold; text-transform: uppercase; padding: 2px 6px; border-radius: 8px; background: #F1F5F9; color: #475569; }
        .foot { margin: 24px 32px 0; padding-top: 10px; border-top: 1px solid #E2E8F0; font-size: 9px; color: #94A3B8; }
    </style>
</head>
<body>
    <div class="accent-bar"></div>
    <div class="cover">
        <div class="brand">ELITE BUSINESS HUB</div>
        <h1>{{ $event->name }} — Agenda</h1>
        <div class="meta">
            {{ str($event->type)->replace('_', ' ')->title() }}
            @if ($event->venue) &nbsp;·&nbsp; {{ $event->venue->name }} @endif
            &nbsp;·&nbsp; {{ $event->city }}, {{ $event->country }}
            &nbsp;·&nbsp; {{ $event->starts_at?->format('M j') }}–{{ $event->ends_at?->format('M j, Y') ?? $event->starts_at?->format('Y') }}
        </div>
    </div>

    @forelse ($event->agendaDays as $day)
        <div class="day">
            <div class="day-title">{{ $day->label }} <span class="day-date">— {{ $day->date?->format('l, F j, Y') }}</span></div>
            <table>
                <thead>
                    <tr><th style="width:80px">Time</th><th>Session</th><th style="width:110px">Room</th><th style="width:70px">Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($day->sessions as $s)
                        <tr>
                            <td class="time">{{ substr($s->starts_at, 0, 5) }}–{{ substr($s->ends_at, 0, 5) }}</td>
                            <td>
                                <div class="title">{{ $s->title }}</div>
                                <div class="sub">{{ str($s->type)->replace('_', ' ')->title() }}@if ($s->speaker) · {{ $s->speaker }} @endif @if ($s->track) · {{ $s->track }} @endif</div>
                            </td>
                            <td>{{ $s->room?->name ?? '—' }}</td>
                            <td><span class="pill">{{ str($s->status)->replace('_', ' ')->title() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="sub">No sessions scheduled.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @empty
        <div class="day"><p class="sub">No agenda has been built yet.</p></div>
    @endforelse

    <div class="foot">Generated {{ now()->format('M j, Y · H:i') }} · Elite Business Hub — Operations Command Center</div>
</body>
</html>
