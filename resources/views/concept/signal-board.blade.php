@php
    // ── The Signal Board ────────────────────────────────────────────────────
    // A prototype of one visual language for the whole platform. Deliberately
    // self-contained: its own document, its own CSS. Nothing here is imported
    // by a real screen, so it can be judged and then kept or deleted whole.
    $live = $channels->filter(fn ($c) => $c['score'] !== null);
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Signal Board — Elite Business Hub</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=playfair-display:600,700,800|jetbrains-mono:400,500,700|inter:400,500,600,700&display=swap" rel="stylesheet">
    <style>
        /* ── Tokens ──────────────────────────────────────────────────────────
           Navy is type, hairlines and small solids. There is no navy slab on
           this page — that is the whole point of the palette. */
        :root {
            --paper:      #F4F6FA;
            --panel:      #FFFFFF;
            --sunk:       #EDF1F7;
            --line:       #E1E7F0;
            --line-soft:  #EEF2F8;

            --navy:       #0B1F3A;
            --navy-2:     #1E3352;
            --ink-2:      #4A5C7A;
            --ink-3:      #8093B2;

            --gold:       #B8942C;   /* reads as text */
            --gold-lit:   #D4AF37;   /* fills, traces, nodes */
            --gold-soft:  #FBF7EA;

            --ok:    #22A45D;
            --warn:  #E0A008;
            --risk:  #DC3B3B;
            --idle:  #B6C2D6;

            --serif: 'Playfair Display', Georgia, serif;
            --sans:  'Inter', system-ui, sans-serif;
            --mono:  'JetBrains Mono', ui-monospace, monospace;

            --rail: 212px;
        }

        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; }
        body {
            background: var(--paper);
            font-family: var(--sans);
            color: var(--navy);
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; text-decoration: none; }
        button { font: inherit; color: inherit; background: none; border: 0; cursor: pointer; }

        .eyebrow {
            font-size: 9.5px; font-weight: 700; letter-spacing: .18em;
            text-transform: uppercase; color: var(--ink-3);
        }
        .num { font-family: var(--mono); font-variant-numeric: tabular-nums; }

        /* ── The trace: the mark that ties every screen together ─────────── */
        .trace { position: relative; padding-left: 18px; }
        .trace::before {
            content: ''; position: absolute; left: 3px; top: 6px; bottom: 6px;
            width: 1px; background: linear-gradient(180deg, var(--gold-lit), transparent);
        }
        .trace::after {
            content: ''; position: absolute; left: 0; top: 4px;
            width: 7px; height: 7px; background: var(--gold-lit);
            transform: rotate(45deg); border-radius: 1px;
        }

        /* ── Rail (left navigation) ──────────────────────────────────────── */
        .rail {
            position: fixed; inset: 0 auto 0 0; width: var(--rail);
            background: var(--panel); border-right: 1px solid var(--line);
            display: flex; flex-direction: column; z-index: 20;
        }
        .rail__brand {
            height: 64px; display: flex; align-items: center; gap: 10px;
            padding: 0 18px; border-bottom: 1px solid var(--line);
        }
        .mark {
            width: 26px; height: 26px; display: grid; place-items: center;
            background: var(--navy); border-radius: 7px; flex: none;
        }
        .mark i { width: 9px; height: 9px; border: 2px solid var(--gold-lit); transform: rotate(45deg); border-radius: 1px; }
        .rail__nav { padding: 14px 12px; position: relative; }
        /* the spine */
        .rail__nav::before {
            content: ''; position: absolute; left: 25px; top: 22px; bottom: 22px;
            width: 2px; background: var(--line);
        }
        .navlink {
            position: relative; display: flex; align-items: center; gap: 12px;
            height: 38px; padding: 0 10px; border-radius: 9px;
            font-size: 13px; font-weight: 500; color: var(--ink-2);
            transition: background .15s, color .15s;
        }
        .navlink:hover { background: var(--sunk); color: var(--navy); }
        .navlink i {
            position: relative; z-index: 1; width: 9px; height: 9px; flex: none;
            background: var(--idle); transform: rotate(45deg); border-radius: 1px;
            outline: 3px solid var(--panel); transition: background .15s, transform .15s;
        }
        .navlink:hover i { background: var(--gold-lit); transform: rotate(45deg) scale(1.15); }
        .navlink[aria-current] { color: var(--navy); font-weight: 700; }
        .navlink[aria-current] i {
            background: var(--gold-lit); width: 12px; height: 12px;
            box-shadow: 0 0 0 3px rgba(212,175,55,.22);
        }

        /* live channel list in the rail */
        .rail__live { margin-top: auto; border-top: 1px solid var(--line); padding: 14px 12px 16px; }
        .chip-live {
            display: flex; align-items: center; gap: 8px; width: 100%;
            padding: 7px 10px; border-radius: 9px; text-align: left;
            transition: background .15s;
        }
        .chip-live:hover, .chip-live.is-hot { background: var(--gold-soft); }
        .chip-live b { font-size: 11.5px; font-weight: 600; display: block;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .chip-live span { font-size: 9.5px; color: var(--ink-3); }

        /* ── Ribbon (top) ────────────────────────────────────────────────── */
        .shell { margin-left: var(--rail); }
        .ribbon {
            position: sticky; top: 0; z-index: 15; height: 64px;
            display: flex; align-items: center; gap: 18px; padding: 0 24px;
            background: rgba(255,255,255,.86); backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--line);
        }
        .cmd {
            flex: 1; max-width: 460px; height: 38px; display: flex; align-items: center; gap: 9px;
            padding: 0 14px; background: var(--sunk); border: 1px solid transparent;
            border-radius: 999px; color: var(--ink-3); font-size: 12.5px;
            transition: border-color .15s, background .15s;
        }
        .cmd:focus-within { background: var(--panel); border-color: var(--gold-lit); }
        .cmd input { flex: 1; border: 0; background: none; outline: none; font: inherit; color: var(--navy); }
        .cmd kbd {
            font-family: var(--mono); font-size: 9.5px; padding: 2px 5px;
            border: 1px solid var(--line); border-radius: 4px; background: var(--panel);
        }
        /* the ribbon carries state, not just buttons */
        .readout { display: flex; align-items: center; gap: 20px; margin-left: auto; }
        .readout > div { text-align: right; }
        .readout .v { font-family: var(--serif); font-size: 17px; font-weight: 700; line-height: 1; }

        /* ── Signal row ──────────────────────────────────────────────────── */
        .board { padding: 22px 24px 40px; }
        .signals {
            display: grid; grid-template-columns: repeat(5, minmax(0,1fr));
            background: var(--panel); border: 1px solid var(--line); border-radius: 16px;
            overflow: hidden;
        }
        .signal { padding: 16px 18px; border-right: 1px solid var(--line-soft); }
        .signal:last-child { border-right: 0; }
        .signal .v {
            font-family: var(--serif); font-size: 30px; font-weight: 700; line-height: 1;
            margin: 7px 0 9px;
        }
        /* segmented meter — the instrument, reused everywhere */
        .meter { display: flex; gap: 2px; height: 5px; }
        .meter s { flex: 1; background: var(--line); border-radius: 1px; }
        .meter s.on { background: var(--gold-lit); }
        .meter.ok s.on { background: var(--ok); }
        .meter.warn s.on { background: var(--warn); }
        .meter.risk s.on { background: var(--risk); }
        .meter.idle s.on { background: var(--idle); }

        /* ── The field ───────────────────────────────────────────────────── */
        .grid2 { display: grid; gap: 16px; margin-top: 16px; grid-template-columns: minmax(0,1fr); }
        @media (min-width: 1180px) { .grid2 { grid-template-columns: minmax(0,1fr) 320px; } }

        .panel { background: var(--panel); border: 1px solid var(--line); border-radius: 16px; }
        .panel__head {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; padding: 15px 18px; border-bottom: 1px solid var(--line-soft);
        }
        .panel__head h2 { font-family: var(--serif); font-size: 16px; font-weight: 700; margin: 0; }

        .field { position: relative; height: 430px; overflow: hidden; border-radius: 0 0 16px 16px;
            background:
              radial-gradient(ellipse 55% 55% at 50% 50%, rgba(212,175,55,.09), transparent 70%),
              linear-gradient(180deg, #FCFDFF, #F2F5FA); }
        .field .dots {
            position: absolute; inset: 0; opacity: .55;
            background-image: radial-gradient(rgba(11,31,58,.055) 1px, transparent 1px);
            background-size: 24px 24px;
        }
        .ring { position: absolute; left: 50%; top: 50%; transform: translate(-50%,-50%);
            border: 1px dashed rgba(11,31,58,.10); border-radius: 50%; }
        .core {
            position: absolute; left: 50%; top: 50%; transform: translate(-50%,-50%);
            width: 108px; height: 108px; border-radius: 50%;
            background: var(--navy); color: #fff; display: grid; place-content: center;
            text-align: center; box-shadow: 0 14px 34px -12px rgba(11,31,58,.55);
            border: 2px solid var(--gold-lit);
        }
        .core .v { font-family: var(--serif); font-size: 26px; font-weight: 700; line-height: 1; }
        .core .l { font-size: 8px; letter-spacing: .16em; color: var(--gold-lit); margin-top: 5px; }

        .node {
            position: absolute; transform: translate(-50%,-50%);
            display: grid; place-items: center; width: 86px;
            transition: transform .25s cubic-bezier(.2,.8,.2,1), opacity .25s;
        }
        .node__dot {
            width: 54px; height: 54px; border-radius: 50%; background: var(--panel);
            border: 2px solid var(--line); display: grid; place-items: center;
            font-family: var(--serif); font-size: 15px; font-weight: 700;
            box-shadow: 0 6px 16px -8px rgba(11,31,58,.35);
            transition: border-color .2s, transform .2s, box-shadow .2s;
        }
        .node__name {
            margin-top: 7px; font-size: 10px; font-weight: 600; text-align: center;
            max-width: 86px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .node.is-hot { transform: translate(-50%,-50%) scale(1.12); z-index: 3; }
        .node.is-hot .node__dot { border-color: var(--gold-lit); box-shadow: 0 0 0 5px rgba(212,175,55,.18); }
        .node.is-dim { opacity: .28; }

        /* ── Channel strips ──────────────────────────────────────────────── */
        .striproll { overflow-x: auto; }
        .strips { display: flex; flex-direction: column; min-width: 760px; }
        .strip {
            display: grid; align-items: center; gap: 14px;
            grid-template-columns: 34px minmax(140px,1fr) repeat(4, 74px) 92px 74px;
            padding: 13px 18px; border-bottom: 1px solid var(--line-soft);
            text-align: left; width: 100%;
            transition: background .14s;
        }
        .strip:last-child { border-bottom: 0; }
        .strip:hover, .strip.is-hot { background: var(--gold-soft); }
        .strip.is-sel { background: var(--gold-soft); box-shadow: inset 3px 0 0 var(--gold-lit); }
        .strip__cap {
            width: 34px; height: 34px; border-radius: 9px; background: var(--sunk);
            display: grid; place-items: center; font-family: var(--serif);
            font-size: 12px; font-weight: 700; color: var(--navy-2);
        }
        .strip__name { font-size: 13.5px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .strip__sub { font-size: 10.5px; color: var(--ink-3); margin-top: 2px;
            display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .strip__m .k { font-size: 9px; color: var(--ink-3); letter-spacing: .06em; text-transform: uppercase; }
        .strip__m .n { font-family: var(--mono); font-size: 11.5px; font-weight: 500; margin: 3px 0 4px; }
        .colhead {
            display: grid; gap: 14px; align-items: center; min-width: 760px;
            grid-template-columns: 34px minmax(140px,1fr) repeat(4, 74px) 92px 74px;
            padding: 9px 18px; border-bottom: 1px solid var(--line);
            background: var(--sunk); border-radius: 0;
        }
        .colhead span { font-size: 9px; font-weight: 700; letter-spacing: .14em;
            text-transform: uppercase; color: var(--ink-3); }

        .led { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
        .led.ok { background: var(--ok); } .led.warn { background: var(--warn); }
        .led.risk { background: var(--risk); } .led.neutral { background: var(--idle); }

        /* ── Inspector ───────────────────────────────────────────────────── */
        .kv { display: flex; justify-content: space-between; gap: 10px; padding: 9px 0;
            border-bottom: 1px solid var(--line-soft); font-size: 12px; }
        .kv:last-child { border-bottom: 0; }
        .kv b { font-family: var(--mono); font-weight: 500; }

        .scrub { display: flex; align-items: center; gap: 12px; padding: 12px 18px;
            border-top: 1px solid var(--line-soft); }
        .scrub input { flex: 1; accent-color: var(--gold-lit); }

        .pill { display: inline-flex; align-items: center; gap: 6px; padding: 3px 9px;
            border-radius: 999px; background: var(--sunk); font-size: 9.5px; font-weight: 700;
            letter-spacing: .08em; text-transform: uppercase; color: var(--ink-2); }
        .btn-gold { display: inline-flex; align-items: center; gap: 7px; height: 36px;
            padding: 0 16px; border-radius: 10px; background: var(--gold-lit);
            color: var(--navy); font-size: 12.5px; font-weight: 700; }
        .btn-gold:hover { filter: brightness(1.05); }
    </style>
</head>
<body>

{{-- ════════════ RAIL ════════════ --}}
<aside class="rail">
    <div class="rail__brand">
        <span class="mark"><i></i></span>
        <span>
            <span style="display:block;font-size:12px;font-weight:800;letter-spacing:.2em">ELITE</span>
            <span style="display:block;font-size:7.5px;letter-spacing:.24em;color:var(--gold)">BUSINESS HUB</span>
        </span>
    </div>

    <nav class="rail__nav">
        @foreach ([['Command', true], ['Events', false], ['Projects', false], ['Tasks', false],
                   ['Finance', false], ['Suppliers', false], ['Reports', false], ['Settings', false]] as [$label, $on])
            <a href="#" class="navlink" @if ($on) aria-current="page" @endif><i></i>{{ $label }}</a>
        @endforeach
    </nav>

    <div class="rail__live">
        <p class="eyebrow" style="padding:0 10px 7px">Live channels</p>
        @foreach ($channels->take(5) as $c)
            <button type="button" class="chip-live" data-hot="{{ $c['id'] }}">
                <span class="led {{ $c['group'] }}"></span>
                <span style="min-width:0;flex:1">
                    <b>{{ $c['name'] }}</b>
                    <span>{{ $c['score'] !== null ? $c['score'].'% · '.$c['status'] : $c['status'] }}</span>
                </span>
            </button>
        @endforeach
    </div>
</aside>

<div class="shell">

    {{-- ════════════ RIBBON ════════════ --}}
    <header class="ribbon">
        <label class="cmd">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/></svg>
            <input placeholder="Route to an event, a supplier, a document…">
            <kbd>⌘K</kbd>
        </label>

        <div class="readout">
            <div>
                <p class="eyebrow">Channels</p>
                <p class="v num">{{ $channels->count() }}</p>
            </div>
            <div>
                <p class="eyebrow">Signal</p>
                <p class="v num">{{ $live->count() ? (int) round($live->avg('score')) : '—' }}%</p>
            </div>
            <div>
                <p class="eyebrow">Alerts</p>
                <p class="v num" style="color:{{ $alerts->count() ? 'var(--risk)' : 'inherit' }}">{{ $alerts->count() }}</p>
            </div>
            <a href="#" class="btn-gold">＋ New event</a>
        </div>
    </header>

    <main class="board">

        {{-- ════════════ SIGNAL ROW — no navy slab ════════════ --}}
        @php
            $spendPct = $spend['estimated'] > 0 ? min(100, (int) round($spend['actual'] / $spend['estimated'] * 100)) : 0;
            $signals = [
                ['Events live', $stats['events'], $channels->count() ? 100 : 0, 'ok'],
                ['Portfolio', '$'.\Illuminate\Support\Number::abbreviate($stats['budget'] / 100, 1), $spendPct, 'warn'],
                ['Spend', $spendPct.'%', $spendPct, $spendPct >= 90 ? 'risk' : 'warn'],
                ['Open tasks', $stats['openTasks'], min(100, $stats['openTasks'] * 3), 'warn'],
                ['At risk', $stats['atRisk'], $stats['atRisk'] * 25, $stats['atRisk'] ? 'risk' : 'idle'],
            ];
        @endphp
        <section class="signals">
            @foreach ($signals as [$label, $value, $pct, $tone])
                <div class="signal">
                    <p class="eyebrow">{{ $label }}</p>
                    <p class="v">{{ $value }}</p>
                    <div class="meter {{ $tone }}">
                        @for ($i = 0; $i < 12; $i++)
                            <s @class(['on' => $i < round($pct / 100 * 12)])></s>
                        @endfor
                    </div>
                </div>
            @endforeach
        </section>

        <div class="grid2">
            <div style="display:flex;flex-direction:column;gap:16px;min-width:0">

                {{-- ════════════ THE FIELD ════════════ --}}
                <section class="panel">
                    <div class="panel__head">
                        <div class="trace">
                            <h2>The Field</h2>
                            <p style="margin:2px 0 0;font-size:11.5px;color:var(--ink-3)">Hover a channel to trace it · click to inspect</p>
                        </div>
                        <span class="pill"><span class="led ok"></span>{{ $channels->count() }} routed</span>
                    </div>

                    <div class="field" id="field">
                        <div class="dots"></div>
                        <div class="ring" style="width:400px;height:400px"></div>
                        <div class="ring" style="width:270px;height:270px"></div>

                        <svg style="position:absolute;inset:0;width:100%;height:100%" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                            @foreach ($channels as $c)
                                <line data-trace="{{ $c['id'] }}" x1="50" y1="50" x2="{{ $c['x'] }}" y2="{{ $c['y'] }}"
                                      stroke="#B8942C" stroke-opacity=".22" stroke-width=".25"
                                      stroke-dasharray="1.4 1.1" vector-effect="non-scaling-stroke" />
                            @endforeach
                        </svg>

                        <div class="core">
                            <span class="v num">{{ $live->count() ? (int) round($live->avg('score')) : '—' }}%</span>
                            <span class="l">SIGNAL</span>
                        </div>

                        @foreach ($channels as $c)
                            <a href="{{ $c['href'] }}" class="node" data-node="{{ $c['id'] }}"
                               style="left:{{ $c['x'] }}%;top:{{ $c['y'] }}%">
                                <span class="node__dot" style="color:var(--navy)">
                                    {{ $c['score'] !== null ? $c['score'] : '—' }}
                                </span>
                                <span class="node__name">{{ $c['name'] }}</span>
                            </a>
                        @endforeach
                    </div>

                    {{-- the time scrubber: drag through the calendar, the field responds --}}
                    <div class="scrub">
                        <span class="eyebrow" style="white-space:nowrap">Horizon</span>
                        <input type="range" id="horizon" min="30" max="360" step="30" value="360">
                        <span class="num" id="horizonLabel" style="font-size:11.5px;color:var(--ink-2);white-space:nowrap">all dates</span>
                    </div>
                </section>

                {{-- ════════════ CHANNEL STRIPS ════════════ --}}
                <section class="panel" style="overflow:hidden">
                    <div class="panel__head">
                        <div class="trace">
                            <h2>Channels</h2>
                            <p style="margin:2px 0 0;font-size:11.5px;color:var(--ink-3)">Every event on the same four meters — read down a column</p>
                        </div>
                    </div>

                    <div class="striproll">
                    <div class="colhead">
                        <span></span><span>Channel</span>
                        <span>Budget</span><span>Tasks</span><span>Suppliers</span><span>Risk</span>
                        <span>Window</span><span>Signal</span>
                    </div>

                    <div class="strips">
                        @foreach ($channels as $c)
                            <button type="button" class="strip" data-strip="{{ $c['id'] }}"
                                    data-payload='@json($c)'>
                                <span class="strip__cap">{{ str($c['name'])->substr(0, 2)->upper() }}</span>

                                <span style="min-width:0">
                                    <span class="strip__name">{{ $c['name'] }}</span>
                                    <span class="strip__sub">{{ $c['stage'] }} · {{ $c['where'] }}</span>
                                </span>

                                @foreach ($c['meters'] as [$key, $val])
                                    <span class="strip__m">
                                        <span class="k">{{ $key }}</span>
                                        <span class="n">{{ $val === null ? '—' : $val }}</span>
                                        <span class="meter {{ $val === null ? 'idle' : ($val >= 80 ? 'ok' : ($val >= 50 ? 'warn' : 'risk')) }}">
                                            @for ($i = 0; $i < 8; $i++)
                                                <s @class(['on' => $val !== null && $i < round($val / 100 * 8)])></s>
                                            @endfor
                                        </span>
                                    </span>
                                @endforeach

                                <span>
                                    <span class="num" style="font-size:11.5px">{{ $c['starts']?->format('d M Y') ?? '—' }}</span>
                                    <span class="strip__sub">{{ $c['days'] !== null ? ($c['days'] >= 0 ? $c['days'].' days out' : abs($c['days']).' days ago') : 'no date' }}</span>
                                </span>

                                <span style="display:flex;align-items:center;gap:7px">
                                    <span class="led {{ $c['group'] }}"></span>
                                    <span class="num" style="font-size:13px;font-weight:700">{{ $c['score'] !== null ? $c['score'].'%' : '—' }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                    </div>
                </section>
            </div>

            {{-- ════════════ INSPECTOR — replaces the accordion ════════════ --}}
            <aside style="display:flex;flex-direction:column;gap:16px;min-width:0;align-self:start;position:sticky;top:80px">
                <section class="panel" id="inspector" style="padding:18px">
                    <p class="eyebrow">Inspector</p>
                    <div id="inspectorBody">
                        <p style="margin:14px 0 0;font-size:12.5px;color:var(--ink-3);line-height:1.6">
                            Select a channel to route it here. Nothing expands, nothing moves —
                            the detail always lands in the same place.
                        </p>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel__head"><div class="trace"><h2>Signals</h2></div></div>
                    <div style="padding:6px 18px 16px">
                        @forelse ($alerts as $a)
                            <div style="display:flex;gap:10px;padding:11px 0;border-bottom:1px solid var(--line-soft)">
                                <span class="led {{ $a['severity'] === 'risk' ? 'risk' : ($a['severity'] === 'warn' ? 'warn' : 'ok') }}" style="margin-top:5px;flex:none"></span>
                                <span style="min-width:0">
                                    <span style="display:block;font-size:12px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $a['title'] }}</span>
                                    <span style="display:block;font-size:10.5px;color:var(--ink-3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $a['detail'] }}</span>
                                </span>
                            </div>
                        @empty
                            <p style="font-size:12px;color:var(--ink-3);padding:12px 0">All channels clear.</p>
                        @endforelse
                    </div>
                </section>
            </aside>
        </div>
    </main>
</div>

<script>
/* Dependency-free. Three behaviours, all of them information: cross-highlight,
   inspect-in-place, and a horizon that filters the field by date. */
(() => {
    const q = s => document.querySelectorAll(s);
    const money = (cents, sym) => sym + ' ' + (Math.abs(cents) >= 100000
        ? (cents / 100000).toFixed(1).replace(/\.0$/, '') + 'K' : (cents / 100).toLocaleString());

    /* ── cross-highlight: strip ⇄ node ⇄ rail chip ─────────────────────── */
    const setHot = (id, on) => {
        q(`[data-strip="${id}"], [data-node="${id}"], [data-hot="${id}"]`).forEach(el => el.classList.toggle('is-hot', on));
        const trace = document.querySelector(`[data-trace="${id}"]`);
        if (trace) {
            trace.setAttribute('stroke-opacity', on ? '.85' : '.22');
            trace.setAttribute('stroke-width', on ? '.5' : '.25');
        }
        q('.node').forEach(n => n.classList.toggle('is-dim', on && n.dataset.node !== String(id)));
    };

    ['[data-strip]', '[data-node]', '[data-hot]'].forEach(sel => q(sel).forEach(el => {
        const id = el.dataset.strip || el.dataset.node || el.dataset.hot;
        el.addEventListener('mouseenter', () => setHot(id, true));
        el.addEventListener('mouseleave', () => setHot(id, false));
    }));

    /* ── inspect: the detail always lands in the same panel ────────────── */
    const body = document.getElementById('inspectorBody');
    q('[data-strip]').forEach(el => el.addEventListener('click', () => {
        q('.strip').forEach(s => s.classList.remove('is-sel'));
        el.classList.add('is-sel');
        const c = JSON.parse(el.dataset.payload);
        body.innerHTML = `
            <h3 style="font-family:var(--serif);font-size:19px;font-weight:700;margin:10px 0 3px;line-height:1.2">${c.name}</h3>
            <p style="margin:0 0 14px;font-size:11.5px;color:var(--ink-3)">${c.stage} · ${c.where}</p>
            <div class="kv"><span>Signal</span><b>${c.score === null ? '— not scored' : c.score + '% · ' + c.status}</b></div>
            <div class="kv"><span>Window</span><b>${c.days === null ? '—' : (c.days >= 0 ? c.days + ' days out' : Math.abs(c.days) + ' days ago')}</b></div>
            <div class="kv"><span>Participants</span><b>${c.pax ? c.pax.toLocaleString() : '—'}</b></div>
            <div class="kv"><span>Open tasks</span><b>${c.open}${c.overdue ? ' · ' + c.overdue + ' overdue' : ''}</b></div>
            <div class="kv"><span>Open risks</span><b>${c.risks}</b></div>
            <div class="kv"><span>Budget</span><b>${c.budget ? money(c.budget, c.currency) : '—'}</b></div>
            <a href="${c.href}" class="btn-gold" style="width:100%;justify-content:center;margin-top:15px">Open control room</a>`;
    }));

    /* ── horizon: drag time, the field answers ─────────────────────────── */
    const horizon = document.getElementById('horizon');
    const label = document.getElementById('horizonLabel');
    const days = {};
    q('[data-strip]').forEach(el => { const c = JSON.parse(el.dataset.payload); days[c.id] = c.days; });

    horizon.addEventListener('input', () => {
        const max = +horizon.value;
        label.textContent = max >= 360 ? 'all dates' : 'next ' + max + ' days';
        Object.entries(days).forEach(([id, d]) => {
            const out = max < 360 && (d === null || d < 0 || d > max);
            document.querySelector(`[data-node="${id}"]`)?.classList.toggle('is-dim', out);
            const s = document.querySelector(`[data-strip="${id}"]`);
            if (s) s.style.opacity = out ? '.35' : '1';
            const t = document.querySelector(`[data-trace="${id}"]`);
            if (t) t.setAttribute('stroke-opacity', out ? '.06' : '.22');
        });
    });

    /* ⌘K focuses the command line */
    addEventListener('keydown', e => {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault(); document.querySelector('.cmd input').focus();
        }
    });
})();
</script>
</body>
</html>
