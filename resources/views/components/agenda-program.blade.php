@props([
    'days' => [],        // [ ['label'=>, 'date'=>, 'program'=>['blocks'=>,'ambient'=>]], … ]
    'heading' => null,
    'subheading' => null,
    'legend' => true,
])

<div class="apg">
    <style>
        .apg{ --navy:var(--chrome); --navy2:var(--chrome-2); --gold:var(--gold); --gold-line:var(--gold-3); --gold-soft:var(--gold-tint);
            --ink:var(--ink); --muted:var(--ink-3); --line:var(--rim); --paper:var(--plate); --track:var(--abyss); --trackline:var(--rim);
            font-family:'Spectral',Georgia,'Times New Roman',serif; color:var(--ink); }
        .apg *{ box-sizing:border-box; }
        .apg-sans{ font-family:'Inter',system-ui,-apple-system,sans-serif; }

        .apg-head{ background:linear-gradient(135deg,var(--navy),var(--navy2)); color:var(--chrome-ink); border-radius:16px;
            padding:22px 26px; position:relative; overflow:hidden; }
        .apg-head:after{ content:""; position:absolute; right:-40px; top:-60px; width:210px; height:210px; border-radius:50%;
            background:radial-gradient(circle,var(--gold-glow),transparent 70%); }
        .apg-eyebrow{ font-family:'Inter',system-ui,sans-serif; font-size:9.5px; font-weight:700; letter-spacing:.3em;
            text-transform:uppercase; color:var(--gold-lit); }
        .apg-h1{ font-size:22px; font-weight:800; margin:7px 0 5px; letter-spacing:.2px; }
        .apg-sub{ font-size:12.5px; color:var(--chrome-ink-2); max-width:640px; line-height:1.55; position:relative; z-index:1; }
        .apg-legend{ display:flex; flex-wrap:wrap; gap:14px; margin-top:14px; position:relative; z-index:1; }
        .apg-legend span{ font-family:'Inter',system-ui,sans-serif; font-size:10px; font-weight:600; color:var(--chrome-ink-2);
            display:flex; align-items:center; gap:6px; }
        .apg-dot{ width:9px; height:9px; border-radius:3px; display:inline-block; }
        .apg-m{ background:var(--gold); } .apg-p{ background:var(--ion-lit); } .apg-t{ background:var(--ink-4); }
        .apg-n{ background:var(--vital-lit); } .apg-c{ background:var(--critical-lit); }

        /* A day is usually taller than a page — let it flow, but never orphan its
           heading and never split a time block or card across pages. */
        .apg-day{ margin-top:22px; }
        .apg-daytag{ display:flex; align-items:baseline; gap:12px; flex-wrap:wrap; border-bottom:2px solid var(--gold);
            padding-bottom:8px; margin-bottom:2px; break-after:avoid; break-inside:avoid; }
        .apg-date{ font-family:'Inter',system-ui,sans-serif; font-size:10.5px; font-weight:800; letter-spacing:.13em;
            text-transform:uppercase; color:var(--gold); }
        .apg-dayname{ font-size:18px; font-weight:700; color:var(--navy); }

        .apg-blk{ display:grid; grid-template-columns:70px 1fr; border-bottom:1px solid var(--line); break-inside:avoid; }
        .apg-blk:last-child{ border-bottom:0; }
        .apg-time{ padding:12px 11px 12px 0; font-family:'Inter',system-ui,sans-serif; font-size:11px; font-weight:700;
            color:var(--muted); border-right:1px solid var(--line); text-align:right; }
        .apg-time small{ display:block; font-weight:500; font-size:9px; margin-top:2px; opacity:.7; }
        .apg-body{ padding:11px 0 11px 15px; display:flex; flex-wrap:wrap; gap:7px; }

        .apg-card{ flex:1 1 195px; min-width:160px; border:1px solid var(--trackline); background:var(--track);
            border-radius:10px; padding:8px 11px; break-inside:avoid; }
        .apg-card .apg-k{ font-family:'Inter',system-ui,sans-serif; font-size:8px; font-weight:700; letter-spacing:.11em;
            text-transform:uppercase; color:var(--muted); display:flex; align-items:center; justify-content:space-between; gap:8px; }
        .apg-card .apg-kr{ display:flex; align-items:center; gap:5px; flex-shrink:0; }
        .apg-card .apg-own{ font-variant-numeric:tabular-nums; letter-spacing:.04em; color:var(--ink);
            background:rgba(0,0,0,.05); border-radius:4px; padding:1px 5px; white-space:nowrap; }
        .apg-card .apg-st{ color:var(--gold); background:var(--gold-tint); border-radius:4px; padding:1px 5px; white-space:nowrap; }
        /* Dark comes from data-theme, not the OS: the tokens above already invert. */

        .apg-draft{ display:flex; align-items:center; gap:10px; margin-bottom:14px; padding:9px 14px;
            border:1px solid var(--gold-line); border-left:3px solid var(--gold); border-radius:9px;
            background:var(--gold-soft); font-family:'Inter',system-ui,sans-serif; font-size:11px; color:var(--ink); }
        .apg-draft b{ font-weight:700; }
        .apg-draft-tag{ font-size:9px; font-weight:800; letter-spacing:.16em; text-transform:uppercase;
            color:var(--chrome-ink); background:var(--gold); border-radius:4px; padding:2px 7px; white-space:nowrap; }
        .apg-card .apg-ti{ font-size:13.5px; font-weight:600; line-height:1.3; margin-top:3px; color:var(--ink); }
        .apg-card .apg-sp{ font-family:'Inter',system-ui,sans-serif; font-size:9.5px; font-weight:600; color:var(--navy); margin-top:3px; line-height:1.4; }
        .apg-card .apg-r{ font-family:'Inter',system-ui,sans-serif; font-size:9.5px; color:var(--muted); margin-top:3px; }
        .apg-full{ flex-basis:100%; }
        .apg-card.apg-plen{ border-color:var(--rim-hi); } .apg-card.apg-plen .apg-k{ color:var(--ion); }
        .apg-card.apg-main{ flex:2 1 300px; background:linear-gradient(135deg,var(--gold-tint),var(--deck)); border-color:var(--gold-line); }
        .apg-card.apg-main .apg-k{ color:var(--gold); } .apg-card.apg-main .apg-ti{ font-weight:700; }
        .apg-card.apg-net .apg-k{ color:var(--vital); }
        .apg-card.apg-cer{ border-color:var(--critical-tint); } .apg-card.apg-cer .apg-k{ color:var(--critical); }

        .apg-amb{ margin-top:10px; display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
        .apg-amb .apg-lbl{ font-family:'Inter',system-ui,sans-serif; font-size:8.5px; font-weight:700; letter-spacing:.1em;
            text-transform:uppercase; color:var(--muted); margin-right:2px; }
        .apg-pill{ font-family:'Inter',system-ui,sans-serif; font-size:9.5px; color:var(--ink-2); background:var(--paper);
            border:1px solid var(--line); border-radius:999px; padding:3px 10px; }
        .apg-pill b{ color:var(--navy); font-weight:700; }
        .apg-empty{ font-family:'Inter',system-ui,sans-serif; font-size:11px; color:var(--muted); padding:14px 0; }
    </style>

    @php
        $unconfirmed = collect($days)->sum(fn ($d) => $d['program']['unconfirmed'] ?? 0);
        $sessionTotal = collect($days)->sum(fn ($d) => $d['program']['total'] ?? 0);
        // When everything is draft the ribbon says it once; per-card chips would
        // just be noise. Chips earn their place only on a mixed programme, where
        // they show *which* sessions aren't signed off.
        $allDraft = $unconfirmed > 0 && $unconfirmed === $sessionTotal;
    @endphp

    @if ($unconfirmed > 0)
        <div class="apg-draft">
            <span class="apg-draft-tag">Draft</span>
            <span><b>{{ $unconfirmed }} of {{ $sessionTotal }}</b> {{ \Illuminate\Support\Str::plural('session', $sessionTotal) }} not yet confirmed — not for distribution.</span>
        </div>
    @endif

    @if ($heading)
        <div class="apg-head">
            <div class="apg-eyebrow">Programme at a glance</div>
            <div class="apg-h1">{{ $heading }}</div>
            @if ($subheading)<div class="apg-sub">{{ $subheading }}</div>@endif
            @if ($legend)
                <div class="apg-legend">
                    <span><i class="apg-dot apg-m"></i> Main Stage</span>
                    <span><i class="apg-dot apg-p"></i> Plenary</span>
                    <span><i class="apg-dot apg-t"></i> Track</span>
                    <span><i class="apg-dot apg-n"></i> Networking</span>
                    <span><i class="apg-dot apg-c"></i> Ceremony</span>
                </div>
            @endif
        </div>
    @endif

    @foreach ($days as $d)
        <div class="apg-day">
            <div class="apg-daytag">
                <span class="apg-date">{{ $d['date'] }}</span>
                <span class="apg-dayname">{{ $d['label'] }}</span>
            </div>

            @forelse ($d['program']['blocks'] as $blk)
                <div class="apg-blk">
                    <div class="apg-time">{{ $blk['time'] }}<small>{{ $blk['end'] }}</small></div>
                    <div class="apg-body">
                        @foreach ($blk['cards'] as $c)
                            <div class="apg-card apg-{{ $c['kind'] }} {{ $blk['full'] ? 'apg-full' : '' }}">
                                <div class="apg-k">
                                    <span>{{ $c['mark'] ? $c['mark'].' ' : '' }}{{ $c['kicker'] }}{{ $c['kind'] === 'plen' && $c['room'] ? ' — '.$c['room'] : '' }}</span>
                                    {{-- Shown only when this session doesn't match its slot, so the header never misleads. --}}
                                    <span class="apg-kr">
                                        {{-- Unapproved sessions are marked; confirmed ones stay clean. --}}
                                        @unless ($c['settled'] || $allDraft)<span class="apg-st">{{ $c['status'] }}</span>@endunless
                                        @if ($c['ownTime'])<span class="apg-own">{{ $c['ownTime'] }}</span>@endif
                                    </span>
                                </div>
                                <div class="apg-ti">{{ $c['session']->title }}</div>
                                @if ($line = $c['session']->speakerLine())
                                    <div class="apg-sp">{{ $line }}</div>
                                @endif
                                @if ($c['session']->description)
                                    {{-- Cut on a word boundary — a programme should never break mid-word. --}}
                                    <div class="apg-r">{{ \Illuminate\Support\Str::limit($c['session']->description, 150, '…', preserveWords: true) }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="apg-empty">No timed sessions scheduled for this day yet.</div>
            @endforelse

            @if (! empty($d['program']['ambient']))
                <div class="apg-amb">
                    <span class="apg-lbl">Running throughout</span>
                    @foreach ($d['program']['ambient'] as $a)
                        <span class="apg-pill"><b>{{ $a['title'] }}</b>{{ $a['room'] ? ' · '.$a['room'] : '' }} · {{ $a['time'] }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</div>
