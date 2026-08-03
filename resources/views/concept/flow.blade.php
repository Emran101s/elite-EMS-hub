@php
    // ── The Flow ────────────────────────────────────────────────────────────
    // Second pass, taken from the reference boards: no dark panel anywhere,
    // navigation as a row of pills instead of a sidebar, and connection drawn
    // rather than implied. Self-contained — no real screen imports this.
    $live  = $channels->filter(fn ($c) => $c['score'] !== null);
    $lanes = [
        ['key' => 0, 'title' => 'Pipeline',      'note' => 'bidding · not committed', 'dot' => '#C3CBD8'],
        ['key' => 1, 'title' => 'In production', 'note' => 'signed · being built',    'dot' => '#D4AF37'],
        ['key' => 2, 'title' => 'Delivering',    'note' => 'live · on the ground',    'dot' => '#1FA463'],
    ];
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>The Flow — Elite Business Hub</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=playfair-display:600,700|jetbrains-mono:400,500|inter:400,500,600,700&display=swap" rel="stylesheet">
    <style>
        /* ── Tokens ───────────────────────────────────────────────────────────
           Navy behaves the way black behaves in the references: rare, small,
           and always the thing you are meant to look at. Gold is the one bright
           accent. There is no dark panel on this page. */
        :root {
            --ground:  #ECEEF1;
            --card:    #FFFFFF;
            --frost:   rgba(255,255,255,.66);
            --line:    #E3E6EC;
            --line-2:  #EDEFF3;

            --navy:    #0B1F3A;
            --ink-2:   #46577A;
            --ink-3:   #8C9AB4;

            --gold:    #B8942C;
            --gold-lit:#D4AF37;

            --ok:   #1FA463;
            --warn: #E0A008;
            --risk: #DC3B3B;
            --idle: #C3CBD8;

            --serif:'Playfair Display', Georgia, serif;
            --sans: 'Inter', system-ui, sans-serif;
            --mono: 'JetBrains Mono', ui-monospace, monospace;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            font-family: var(--sans); color: var(--navy);
            background:
                radial-gradient(1100px 600px at 78% -12%, #FFFFFF, transparent 62%),
                radial-gradient(760px 520px at 4% 108%, #E4E8EE, transparent 60%),
                var(--ground);
            background-attachment: fixed;
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; text-decoration: none; }
        button { font: inherit; color: inherit; background: none; border: 0; cursor: pointer; }
        .eyebrow { font-size: 9.5px; font-weight: 700; letter-spacing: .17em; text-transform: uppercase; color: var(--ink-3); }
        .num { font-family: var(--mono); font-variant-numeric: tabular-nums; }

        /* ── Pill navigation, on top. The dark sidebar is gone. ───────────── */
        .topbar {
            position: sticky; top: 0; z-index: 40;
            display: flex; align-items: center; gap: 22px; padding: 16px 28px;
            background: linear-gradient(180deg, rgba(236,238,241,.96), rgba(236,238,241,.72));
            backdrop-filter: blur(14px);
        }
        .brand { display: flex; align-items: center; gap: 10px; flex: none; }
        .brand .mk { width: 30px; height: 30px; border-radius: 9px; background: var(--navy); display: grid; place-items: center; }
        .brand .mk i { width: 10px; height: 10px; border: 2px solid var(--gold-lit); transform: rotate(45deg); border-radius: 1px; }
        .brand b { font-size: 12.5px; font-weight: 800; letter-spacing: .2em; display: block; line-height: 1; }
        .brand em { font-style: normal; font-size: 7.5px; letter-spacing: .24em; color: var(--gold); }

        .pills { display: flex; align-items: center; gap: 3px; flex-wrap: wrap; }
        .pill {
            height: 38px; padding: 0 18px; border-radius: 999px; font-size: 13px;
            font-weight: 500; color: var(--ink-2); display: inline-flex; align-items: center;
            transition: background .16s, color .16s;
        }
        .pill:hover { background: rgba(255,255,255,.75); color: var(--navy); }
        .pill.on { background: var(--navy); color: #fff; font-weight: 600; }

        .tools { margin-left: auto; display: flex; align-items: center; gap: 8px; }
        .icob {
            position: relative; width: 40px; height: 40px; border-radius: 50%;
            background: var(--card); display: grid; place-items: center; color: var(--ink-2);
            box-shadow: 0 2px 10px -4px rgba(11,31,58,.2); transition: transform .15s, color .15s;
        }
        .icob:hover { transform: translateY(-1px); color: var(--navy); }
        .icob b {
            position: absolute; top: -2px; right: -2px; min-width: 16px; height: 16px;
            border-radius: 999px; background: var(--risk); color: #fff; font-size: 9px;
            font-weight: 700; display: grid; place-items: center; padding: 0 4px;
            border: 2px solid var(--ground);
        }
        .me { display: flex; align-items: center; gap: 9px; height: 40px; padding: 0 14px 0 5px;
            border-radius: 999px; background: var(--card); box-shadow: 0 2px 10px -4px rgba(11,31,58,.2); }
        .av { width: 30px; height: 30px; border-radius: 50%; display: grid; place-items: center;
            background: var(--navy); color: var(--gold-lit); font-size: 10.5px; font-weight: 700; flex: none; }

        /* ── Page ────────────────────────────────────────────────────────── */
        .wrap { padding: 6px 28px 40px; }
        .hello { display: flex; align-items: flex-end; gap: 20px; flex-wrap: wrap; margin: 10px 0 22px; }
        .hello h1 { font-family: var(--serif); font-size: 38px; font-weight: 700; margin: 0; letter-spacing: -.01em; }
        .hello p { margin: 5px 0 0; font-size: 14px; color: var(--ink-2); }

        .stack { display: flex; align-items: center; margin-left: auto; }
        .stack .av { border: 2.5px solid var(--ground); margin-left: -9px; }
        .stack .av:first-child { margin-left: 0; }
        .more { background: var(--card); color: var(--ink-2); }

        /* ── Metric cards ────────────────────────────────────────────────── */
        .metrics { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(178px, 1fr)); }
        .metric { background: var(--card); border-radius: 20px; padding: 16px 18px;
            box-shadow: 0 1px 2px rgba(11,31,58,.04), 0 10px 26px -18px rgba(11,31,58,.35); }
        .metric .v { font-family: var(--serif); font-size: 30px; font-weight: 700; line-height: 1; margin: 8px 0 10px; }
        .bars { display: flex; gap: 2px; height: 4px; }
        .bars s { flex: 1; background: var(--line); border-radius: 999px; }
        .bars s.on { background: var(--gold-lit); }
        .bars.ok s.on { background: var(--ok); } .bars.warn s.on { background: var(--warn); }
        .bars.risk s.on { background: var(--risk); } .bars.idle s.on { background: var(--idle); }

        /* ── The Flow ────────────────────────────────────────────────────── */
        .stage { margin-top: 16px; display: grid; gap: 16px; grid-template-columns: minmax(0,1fr); }
        @media (min-width: 1220px) { .stage { grid-template-columns: minmax(0,1fr) 318px; } }

        .flow { position: relative; border-radius: 26px; padding: 20px 22px 10px;
            background: var(--frost); backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,.7);
            box-shadow: 0 1px 2px rgba(11,31,58,.04), 0 26px 60px -40px rgba(11,31,58,.45); }
        .flow__head { display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 16px; }
        .flow__head h2 { font-family: var(--serif); font-size: 19px; font-weight: 700; margin: 0; }
        .flow__head p { margin: 2px 0 0; font-size: 12px; color: var(--ink-3); }

        #wires { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: visible; }

        .lanes { position: relative; z-index: 1; display: grid; gap: 26px; grid-template-columns: repeat(3, minmax(0,1fr)); }
        @media (max-width: 900px) { .lanes { grid-template-columns: minmax(0,1fr); } }
        .lane__head { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
        .lane__dot { width: 9px; height: 9px; border-radius: 50%; flex: none; }
        .lane__head b { font-size: 12px; font-weight: 700; }
        .lane__head em { font-style: normal; font-size: 10px; color: var(--ink-3); }
        .lane__n { margin-left: auto; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px;
            background: var(--card); font-size: 10.5px; font-weight: 700; display: grid; place-items: center; }

        .ecard { display: block; width: 100%; text-align: left; margin-bottom: 12px;
            background: var(--card); border-radius: 20px; padding: 15px 16px;
            box-shadow: 0 1px 2px rgba(11,31,58,.05), 0 12px 30px -22px rgba(11,31,58,.5);
            transition: transform .18s cubic-bezier(.2,.8,.2,1), box-shadow .18s, opacity .18s; }
        .ecard:hover { transform: translateY(-3px); box-shadow: 0 1px 2px rgba(11,31,58,.05), 0 24px 44px -24px rgba(11,31,58,.55); }
        .ecard.on { background: var(--navy); color: #fff; }
        .ecard.on .sub, .ecard.on .k { color: rgba(255,255,255,.55); }
        .ecard.on .av { border-color: var(--navy); }
        .ecard.dim { opacity: .35; }

        .ecard__top { display: flex; align-items: flex-start; gap: 10px; }
        .ecard__title { display: block; font-size: 14px; font-weight: 700; line-height: 1.25; }
        .sub { display: block; font-size: 10.5px; color: var(--ink-3); margin-top: 3px; }
        .score { margin-left: auto; flex: none; width: 42px; height: 42px; border-radius: 50%;
            display: grid; place-items: center; font-family: var(--mono); font-size: 12px;
            font-weight: 500; border: 2px solid var(--line); }
        .ecard__meters { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 13px 0 12px; }
        .k { display: block; font-size: 8.5px; letter-spacing: .07em; text-transform: uppercase; color: var(--ink-3); margin-bottom: 4px; }
        .ecard__foot { display: flex; align-items: center; gap: 0; }
        .ecard__foot .av { width: 24px; height: 24px; font-size: 8.5px; border: 2px solid var(--card); margin-left: -7px; }
        .ecard__foot .av:first-child { margin-left: 0; }
        .when { margin-left: auto; font-family: var(--mono); font-size: 10.5px; color: var(--ink-3); }

        /* ── Runway ──────────────────────────────────────────────────────── */
        .rail { margin-top: 16px; background: var(--card); border-radius: 22px; padding: 14px 20px 18px;
            box-shadow: 0 1px 2px rgba(11,31,58,.04), 0 14px 34px -26px rgba(11,31,58,.4); }
        .rail__track { position: relative; height: 58px; margin-top: 10px; }
        .rail__line { position: absolute; left: 0; right: 0; top: 26px; height: 2px; background: var(--line); border-radius: 2px; }
        .tick { position: absolute; top: 38px; transform: translateX(-50%); font-size: 9px; color: var(--ink-3); white-space: nowrap; }
        .pin { position: absolute; top: 13px; transform: translateX(-50%); width: 27px; height: 27px;
            border-radius: 50%; background: var(--card); border: 2px solid var(--line);
            display: grid; place-items: center; font-size: 8.5px; font-weight: 700; color: var(--ink-2);
            box-shadow: 0 3px 10px -4px rgba(11,31,58,.35); transition: transform .18s, border-color .18s; }
        .pin:hover, .pin.is-hot { transform: translateX(-50%) scale(1.28); border-color: var(--gold-lit); z-index: 3; }
        .pin.now { background: var(--navy); color: #fff; border-color: var(--navy); font-size: 7.5px; }

        /* ── Inspector ───────────────────────────────────────────────────── */
        .side { display: flex; flex-direction: column; gap: 16px; align-self: start; position: sticky; top: 84px; }
        .panel { background: var(--card); border-radius: 22px; padding: 18px;
            box-shadow: 0 1px 2px rgba(11,31,58,.04), 0 16px 40px -30px rgba(11,31,58,.45); }
        .kv { display: flex; justify-content: space-between; gap: 10px; padding: 9px 0;
            border-bottom: 1px solid var(--line-2); font-size: 12px; }
        .kv:last-of-type { border-bottom: 0; }
        .kv b { font-family: var(--mono); font-weight: 500; }
        .cta { display: flex; align-items: center; justify-content: center; gap: 8px; height: 42px;
            border-radius: 999px; background: var(--navy); color: #fff; font-size: 12.5px;
            font-weight: 600; margin-top: 14px; }
        .cta:hover { background: #14294a; }
        .alert { display: flex; gap: 10px; padding: 11px 0; border-bottom: 1px solid var(--line-2); }
        .alert:last-child { border-bottom: 0; }
        .alert i { width: 7px; height: 7px; border-radius: 50%; margin-top: 5px; flex: none; }
        .alert b { display: block; font-size: 11.5px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .alert em { font-style: normal; display: block; font-size: 10px; color: var(--ink-3); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    </style>
</head>
<body>

{{-- ═══════════ PILL NAV ═══════════ --}}
<header class="topbar">
    <a href="#" class="brand">
        <span class="mk"><i></i></span>
        <span><b>ELITE</b><em>BUSINESS HUB</em></span>
    </a>

    <nav class="pills">
        @foreach (['Command' => true, 'Events' => false, 'Projects' => false, 'Tasks' => false,
                   'Finance' => false, 'Suppliers' => false, 'Reports' => false] as $label => $on)
            <a href="#" @class(['pill', 'on' => $on])>{{ $label }}</a>
        @endforeach
    </nav>

    <div class="tools">
        <button class="icob" aria-label="Search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/></svg>
        </button>
        <button class="icob" aria-label="Messages">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16v12H8l-4 4z"/></svg><b>5</b>
        </button>
        <button class="icob" aria-label="Alerts">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8.5a6 6 0 1 0-12 0c0 5.5-2 7-2 7h16s-2-1.5-2-7"/><path d="M13.7 20a2 2 0 0 1-3.4 0"/></svg><b>{{ $alerts->count() }}</b>
        </button>
        <span class="me"><span class="av">EA</span><span style="font-size:12.5px;font-weight:600">Emran</span></span>
    </div>
</header>

<div class="wrap">

    <div class="hello">
        <div>
            <p class="eyebrow">Command</p>
            <h1>Welcome back, Emran</h1>
            <p>{{ $channels->count() }} events in flight · {{ $alerts->count() }} things want you today</p>
        </div>
        <div class="stack">
            @foreach ($channels->flatMap(fn ($c) => $c['team'])->unique('initials')->take(6) as $m)
                <span class="av" title="{{ $m['name'] }}">{{ $m['initials'] }}</span>
            @endforeach
        </div>
    </div>

    {{-- ═══════════ METRICS ═══════════ --}}
    @php
        $spendPct = $spend['estimated'] > 0 ? min(100, (int) round($spend['actual'] / $spend['estimated'] * 100)) : 0;
        $metrics = [
            ['Events in flight', $stats['events'], 100, 'ok'],
            ['Portfolio value', '$'.\Illuminate\Support\Number::abbreviate($stats['budget'] / 100, 1), 100, ''],
            ['Spend of estimate', $spendPct.'%', $spendPct, $spendPct >= 90 ? 'risk' : 'warn'],
            ['Open tasks', $stats['openTasks'], min(100, $stats['openTasks'] * 3), 'warn'],
            ['Needs attention', $stats['atRisk'], $stats['atRisk'] * 25, $stats['atRisk'] ? 'risk' : 'idle'],
        ];
    @endphp
    <section class="metrics">
        @foreach ($metrics as [$label, $value, $pct, $tone])
            <div class="metric">
                <p class="eyebrow">{{ $label }}</p>
                <p class="v">{{ $value }}</p>
                <div class="bars {{ $tone }}">
                    @for ($i = 0; $i < 14; $i++)<s @class(['on' => $i < round($pct / 100 * 14)])></s>@endfor
                </div>
            </div>
        @endforeach
    </section>

    <div class="stage">
        <div style="min-width:0">
            {{-- ═══════════ THE FLOW ═══════════ --}}
            <section class="flow">
                <div class="flow__head">
                    <div>
                        <h2>The Flow</h2>
                        <p>Every event on its way to the floor — hover one to trace its route</p>
                    </div>
                    <button class="icob" aria-label="New event"
                            style="background:var(--gold-lit);color:var(--navy);box-shadow:0 6px 18px -8px rgba(212,175,55,.9)">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg>
                    </button>
                </div>

                {{-- drawn after layout, from the real card positions --}}
                <svg id="wires" aria-hidden="true"></svg>

                <div class="lanes" id="lanes">
                    @foreach ($lanes as $lane)
                        @php $inLane = $channels->where('lane', $lane['key']); @endphp
                        <div data-lane="{{ $lane['key'] }}">
                            <div class="lane__head">
                                <span class="lane__dot" style="background:{{ $lane['dot'] }}"></span>
                                <b>{{ $lane['title'] }}</b>
                                <em>{{ $lane['note'] }}</em>
                                <span class="lane__n">{{ $inLane->count() }}</span>
                            </div>

                            @forelse ($inLane as $c)
                                <button type="button" class="ecard" data-card="{{ $c['id'] }}" data-payload='@json($c)'>
                                    <span class="ecard__top">
                                        <span style="min-width:0">
                                            <span class="ecard__title">{{ $c['name'] }}</span>
                                            <span class="sub">{{ $c['stage'] }} · {{ $c['where'] }}</span>
                                        </span>
                                        <span class="score" style="border-color:{{ $c['score'] === null ? 'var(--line)' : ['ok' => 'var(--ok)', 'warn' => 'var(--warn)', 'risk' => 'var(--risk)', 'neutral' => 'var(--idle)'][$c['group']] }}">{{ $c['score'] ?? '—' }}</span>
                                    </span>

                                    <span class="ecard__meters">
                                        @foreach ($c['meters'] as [$key, $val])
                                            <span>
                                                <span class="k">{{ $key }}</span>
                                                <span class="bars {{ $val === null ? 'idle' : ($val >= 80 ? 'ok' : ($val >= 50 ? 'warn' : 'risk')) }}">
                                                    @for ($i = 0; $i < 5; $i++)<s @class(['on' => $val !== null && $i < round($val / 100 * 5)])></s>@endfor
                                                </span>
                                            </span>
                                        @endforeach
                                    </span>

                                    <span class="ecard__foot">
                                        @forelse ($c['team'] as $m)
                                            <span class="av" title="{{ $m['name'] }}">{{ $m['initials'] }}</span>
                                        @empty
                                            <span class="sub" style="margin:0">no team yet</span>
                                        @endforelse
                                        @if ($c['teamMore'])<span class="av more">+{{ $c['teamMore'] }}</span>@endif
                                        <span class="when">{{ $c['days'] !== null ? ($c['days'] >= 0 ? $c['days'].'d' : abs($c['days']).'d ago') : '—' }}</span>
                                    </span>
                                </button>
                            @empty
                                <p class="sub" style="padding:14px 2px">Nothing here yet.</p>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- ═══════════ RUNWAY ═══════════ --}}
            @php
                $dated = $channels->filter(fn ($c) => $c['days'] !== null && $c['days'] >= 0)->sortBy('days')->values();
                $span  = max($dated->max('days') ?? 1, 1);
            @endphp
            <section class="rail">
                <div style="display:flex;align-items:center;justify-content:space-between">
                    <p class="eyebrow" style="margin:0">Runway</p>
                    <p class="num" style="margin:0;font-size:11px;color:var(--ink-3)">next {{ $span }} days</p>
                </div>
                <div class="rail__track">
                    <span class="rail__line"></span>
                    <span class="pin now" style="left:2.5%">NOW</span>
                    @foreach ($dated as $c)
                        @php $at = min(96, max(8, $c['days'] / $span * 88 + 8)); @endphp
                        <button class="pin" data-pin="{{ $c['id'] }}" style="left:{{ $at }}%"
                                title="{{ $c['name'] }} — {{ $c['starts']?->format('j M Y') }}">{{ str($c['name'])->substr(0, 2)->upper() }}</button>
                        <span class="tick" style="left:{{ $at }}%">{{ $c['starts']?->format('j M') }}</span>
                    @endforeach
                </div>
            </section>
        </div>

        {{-- ═══════════ INSPECTOR ═══════════ --}}
        <aside class="side">
            <section class="panel">
                <p class="eyebrow">Inspector</p>
                <div id="body">
                    <p style="margin:14px 0 0;font-size:12.5px;color:var(--ink-3);line-height:1.65">
                        Pick any event. It opens here — nothing expands, nothing moves.
                    </p>
                </div>
            </section>

            <section class="panel">
                <p class="eyebrow" style="margin:0 0 4px">Wants you today</p>
                @forelse ($alerts as $a)
                    <div class="alert">
                        <i style="background:{{ $a['severity'] === 'risk' ? 'var(--risk)' : ($a['severity'] === 'warn' ? 'var(--warn)' : 'var(--ok)') }}"></i>
                        <span style="min-width:0"><b>{{ $a['title'] }}</b><em>{{ $a['detail'] }}</em></span>
                    </div>
                @empty
                    <p class="sub" style="padding:12px 0">All clear.</p>
                @endforelse
            </section>
        </aside>
    </div>
</div>

<script>
/* Dependency-free. The wires are measured from real card positions, so they
   stay true at any width; the rest is cross-highlight and inspect-in-place. */
(() => {
    const svg = document.getElementById('wires');
    const flow = svg.parentElement;

    function wires() {
        const box = flow.getBoundingClientRect();
        svg.setAttribute('viewBox', `0 0 ${box.width} ${box.height}`);
        svg.setAttribute('width', box.width);
        svg.setAttribute('height', box.height);
        let out = `<defs>
            <marker id="arw" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                <path d="M0 1 L9 5 L0 9 z" fill="#0B1F3A" fill-opacity=".38"/>
            </marker>
        </defs>`;

        const heads = [...document.querySelectorAll('.lane__head')].map(h => {
            const r = h.getBoundingClientRect();
            return { x: r.left - box.left, y: r.top - box.top + r.height / 2, w: r.width };
        });

        /* the route an event travels from bid to floor, drawn in the gaps */
        const cols = [...document.querySelectorAll('[data-lane]')].map(el => {
            const r = el.getBoundingClientRect();
            return { l: r.left - box.left, r: r.right - box.left, t: r.top - box.top };
        });
        for (let i = 0; i < cols.length - 1; i++) {
            const y = cols[i].t + 58;
            const x1 = cols[i].r + 4, x2 = cols[i + 1].l - 4;
            out += `<path d="M${x1} ${y} C${x1 + 8} ${y - 16}, ${x2 - 8} ${y - 16}, ${x2} ${y}"
                     fill="none" stroke="#0B1F3A" stroke-opacity=".3" stroke-width="1.6"
                     stroke-dasharray="5 4" marker-end="url(#arw)"/>`;
        }

        /* every card taps into its own lane */
        document.querySelectorAll('[data-card]').forEach(el => {
            const r = el.getBoundingClientRect();
            const h = heads[+el.closest('[data-lane]').dataset.lane];
            if (!h) return;
            const x = r.left - box.left + 20, y = r.top - box.top;
            out += `<path data-wire="${el.dataset.card}"
                     d="M${h.x + 4} ${h.y + 7} C${h.x + 4} ${(h.y + y) / 2}, ${x} ${(h.y + y) / 2}, ${x} ${y - 3}"
                     fill="none" stroke="#B8942C" stroke-opacity=".22" stroke-width="1.2"/>`;
        });

        svg.innerHTML = out;
    }
    wires();
    addEventListener('resize', wires);

    /* cross-highlight: card ⇄ its wire ⇄ its pin on the runway */
    const hot = (id, on) => {
        document.querySelectorAll(`[data-pin="${id}"]`).forEach(p => p.classList.toggle('is-hot', on));
        const w = document.querySelector(`[data-wire="${id}"]`);
        if (w) { w.setAttribute('stroke-opacity', on ? '.95' : '.22'); w.setAttribute('stroke-width', on ? '2.2' : '1.2'); }
        document.querySelectorAll('[data-card]').forEach(c => c.classList.toggle('dim', on && c.dataset.card !== String(id)));
    };
    ['[data-card]', '[data-pin]'].forEach(sel => document.querySelectorAll(sel).forEach(el => {
        const id = el.dataset.card || el.dataset.pin;
        el.addEventListener('mouseenter', () => hot(id, true));
        el.addEventListener('mouseleave', () => hot(id, false));
    }));

    /* inspect: the detail always lands in the same panel */
    const body = document.getElementById('body');
    const money = (c, s) => s + ' ' + (Math.abs(c) >= 100000
        ? (c / 100000).toFixed(1).replace(/\.0$/, '') + 'K' : (c / 100).toLocaleString());

    document.querySelectorAll('[data-card]').forEach(el => el.addEventListener('click', () => {
        document.querySelectorAll('[data-card]').forEach(c => c.classList.remove('on'));
        el.classList.add('on');
        const c = JSON.parse(el.dataset.payload);
        body.innerHTML = `
            <h3 style="font-family:var(--serif);font-size:20px;font-weight:700;margin:10px 0 3px;line-height:1.2">${c.name}</h3>
            <p style="margin:0 0 14px;font-size:11.5px;color:var(--ink-3)">${c.stage} · ${c.where}</p>
            <div class="kv"><span>Signal</span><b>${c.score === null ? '— not scored' : c.score + '% · ' + c.status}</b></div>
            <div class="kv"><span>Runway</span><b>${c.days === null ? '—' : (c.days >= 0 ? c.days + ' days' : Math.abs(c.days) + ' days ago')}</b></div>
            <div class="kv"><span>Participants</span><b>${c.pax ? c.pax.toLocaleString() : '—'}</b></div>
            <div class="kv"><span>Open tasks</span><b>${c.open}${c.overdue ? ' · ' + c.overdue + ' late' : ''}</b></div>
            <div class="kv"><span>Open risks</span><b>${c.risks}</b></div>
            <div class="kv"><span>Budget</span><b>${c.budget ? money(c.budget, c.currency) : '—'}</b></div>
            <a href="${c.href}" class="cta">Open control room →</a>`;
    }));
})();
</script>
</body>
</html>
