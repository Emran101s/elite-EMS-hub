<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=playfair-display:600,700,800,900&display=swap" rel="stylesheet">
    <style>{!! $css !!}</style>
    <style>
        @page { size: A4 landscape; margin: 0; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        html, body { background: #fff; }
        .sheet { width: 1123px; margin: 0 auto; }
        .avoid { break-inside: avoid; }
        .phase-band { break-after: avoid; }
        .pf { font-family: 'Playfair Display', Georgia, serif; }
    </style>
</head>
<body class="bg-white font-sans text-ink antialiased">
<div class="sheet p-6">

    {{-- ══ Countdown header ══ --}}
    <div class="avoid mb-4 flex items-stretch gap-3">
        <div class="relative flex-1 overflow-hidden rounded-2xl bg-gradient-to-br from-navy-900 to-[#16294A] px-5 py-4 text-white">
            <div class="pointer-events-none absolute -right-6 -top-8 h-28 w-28 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.35),transparent_70%)]"></div>
            <div class="relative flex flex-wrap items-end gap-x-8 gap-y-2">
                <div>
                    <p class="text-[0.6rem] font-bold uppercase tracking-[0.2em] text-gold-300">Countdown to Event Day</p>
                    @if ($stats['daysToEvent'] === null)
                        <p class="mt-1 text-lg font-bold">No event date set</p>
                    @elseif ($stats['daysToEvent'] > 0)
                        <p class="mt-0.5 flex items-baseline gap-2"><span class="pf text-3xl font-black leading-none text-gold-400">{{ $stats['daysToEvent'] }}</span><span class="text-sm font-semibold">days to go</span></p>
                    @elseif ($stats['daysToEvent'] === 0)
                        <p class="mt-1 text-2xl font-black text-gold-400">It's Event Day</p>
                    @else
                        <p class="mt-1 text-lg font-bold">Event was {{ abs($stats['daysToEvent']) }} days ago</p>
                    @endif
                    @if ($stats['eventDay'])<p class="text-[0.68rem] text-white/60">{{ $stats['eventDay']->format('l, j F Y') }}</p>@endif
                </div>
                <div class="min-w-[200px] flex-1">
                    <div class="mb-1 flex items-center justify-between text-[0.68rem]">
                        <span class="text-white/70">{{ $stats['done'] }} / {{ $stats['total'] }} done</span>
                        <span class="font-bold text-gold-300">{{ $stats['progress'] }}%</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-white/15">
                        <div class="h-full rounded-full bg-gold-400" style="width: {{ $stats['progress'] }}%"></div>
                    </div>
                    @if ($stats['overdue'] > 0)
                        <p class="mt-1.5 text-[0.66rem] font-semibold text-red-300">⚠ {{ $stats['overdue'] }} overdue {{ \Illuminate\Support\Str::plural('task', $stats['overdue']) }}</p>
                    @endif
                </div>
                <div class="text-right">
                    <p class="text-[0.55rem] font-bold uppercase tracking-[0.24em] text-gold-300">◆ Elite Business Hub</p>
                    <p class="pf mt-1 max-w-[280px] truncate text-base font-bold">{{ $event->name }}</p>
                    <p class="text-[0.6rem] text-white/50">Project Plan · {{ now()->format('j F Y') }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Legend ══ --}}
    <div class="mb-2 flex flex-wrap items-center gap-x-4 gap-y-1 px-1 text-[0.66rem] text-navy-600">
        @foreach (\App\Models\EventPlanItem::STATUS_BAR as [$label, $hex])
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background: {{ $hex }}"></span>{{ $label }}</span>
        @endforeach
        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rotate-45 rounded-[2px] bg-navy-400"></span>Milestone</span>
        <span class="ml-auto text-muted">Phase → Workstream → Task → Subtask</span>
    </div>

    {{-- ══ Gantt ══ --}}
    <div class="overflow-hidden rounded-2xl border border-line bg-white">
        {{-- column heads + month axis --}}
        <div class="avoid flex items-stretch border-b border-line bg-white">
            <div class="flex w-[320px] shrink-0 items-center px-4 py-3 text-[0.56rem] font-bold uppercase tracking-[0.16em] text-navy-400">Task</div>
            <div class="relative h-11 min-w-0 flex-1">
                @foreach ($roadmap['ticks'] as $t)
                    <span class="absolute top-2.5 -translate-x-1/2 text-center text-[0.6rem] font-bold uppercase tracking-wide text-navy-400" style="left: {{ $t['left'] }}%">{{ $t['label'] }}<span class="block text-[0.5rem] font-medium text-navy-300">{{ $t['sub'] }}</span></span>
                @endforeach
                @if ($roadmap['todayIn'])
                    <span class="absolute top-0.5 z-10 -translate-x-1/2 rounded-full bg-navy-900 px-2 py-0.5 text-[0.52rem] font-bold tracking-wide text-white" style="left: {{ $roadmap['todayLeft'] }}%">TODAY</span>
                @endif
                @if ($roadmap['eventLeft'] !== null)
                    <span class="absolute top-0.5 z-10 -translate-x-1/2 rounded-full bg-gradient-to-r from-gold-400 to-gold-500 px-2 py-0.5 text-[0.52rem] font-black tracking-wide text-navy-900" style="left: {{ $roadmap['eventLeft'] }}%">★ EVENT</span>
                @endif
            </div>
            <div class="flex w-[66px] shrink-0 items-center justify-center py-3 text-[0.56rem] font-bold uppercase tracking-wide text-navy-400">Start</div>
            <div class="flex w-[66px] shrink-0 items-center justify-center py-3 text-[0.56rem] font-bold uppercase tracking-wide text-navy-400">Due</div>
            <div class="flex w-[92px] shrink-0 items-center justify-center py-3 text-[0.56rem] font-bold uppercase tracking-wide text-navy-400">Status</div>
        </div>

        @foreach ($planTree as $group)
            <div class="phase-band flex items-center gap-2 border-b-2 border-gold-400 px-4 pb-1.5 pt-5">
                <span class="pf text-[1rem] font-bold text-navy-900">{{ $group['phase']->name }}</span>
                <span class="text-[0.62rem] font-medium text-navy-300">· {{ $group['total'] }} {{ \Illuminate\Support\Str::plural('task', $group['total']) }}</span>
                <div class="ml-auto flex items-center gap-2.5">
                    <div class="h-1.5 w-24 overflow-hidden rounded-full bg-navy-100"><div class="h-full rounded-full bg-gradient-to-r from-gold-400 to-gold-500" style="width: {{ $group['pct'] }}%"></div></div>
                    <span class="text-[0.58rem] font-bold text-navy-400">{{ $group['done'] }}/{{ $group['total'] }}</span>
                </div>
            </div>

            @forelse ($group['tasks'] as $node)
                @include('events.partials.plan-pdf-row', ['node' => $node, 'depth' => 0, 'roadmap' => $roadmap])
            @empty
                <div class="border-b border-line px-4 py-3 text-[0.7rem] italic text-navy-300">No tasks in this phase.</div>
            @endforelse
        @endforeach
    </div>

    <p class="mt-3 text-center text-[0.55rem] uppercase tracking-[0.24em] text-navy-300">Elite Business Hub · {{ $event->name }} · Generated {{ now()->format('j M Y') }}</p>
</div>
</body>
</html>
