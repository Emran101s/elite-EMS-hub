{{--
    The load-in sheet: what goes into this room, how many, and which days.

    Written for somebody holding it next to a flight case, not for a client —
    so it carries tick boxes and no prices. The priced version of the same list
    is sheet 2 of the floor plan.
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php
        use Illuminate\Support\Str;

        $navy = $theme['primary'] ?? '#0B1F3A';
        $gold = $theme['accent'] ?? '#D4AF37';

        $statusMeta = [
            'needed' => ['Needed', '#475569', '#EEF2F7'],
            'requested' => ['Requested', '#92400E', '#FEF3C7'],
            'confirmed' => ['Confirmed', '#1E40AF', '#DBEAFE'],
            'onsite' => ['On-site', '#166534', '#DCFCE7'],
        ];

        $days = $room->chargedDays();
        $units = $lines->sum('qty');
        $ready = $lines->whereIn('status', ['confirmed', 'onsite'])->sum('qty');
        $readiness = $units > 0 ? (int) round($ready / $units * 100) : 0;

        // Grouped by where a line has got to, so the sheet opens on the things
        // still outstanding rather than on an alphabet.
        $byStatus = collect(\App\Models\EventRoom::EQUIPMENT_STATUSES)
            ->mapWithKeys(fn ($s) => [$s => $lines->where('status', $s)->values()])
            ->filter(fn ($g) => $g->isNotEmpty());

        $counts = collect(\App\Models\EventRoom::EQUIPMENT_STATUSES)
            ->mapWithKeys(fn ($s) => [$s => $lines->where('status', $s)->sum('qty')]);
    @endphp

    <style>{!! $css !!}</style>
    <style>
        @page { size: A4 portrait; margin: 13mm 12mm 15mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff; color: #0F172A;
               font-family: ui-sans-serif, system-ui, 'Helvetica Neue', Arial, sans-serif;
               -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        /* ── masthead ── */
        .mh { background: {{ $navy }}; color: #fff; border-radius: 8px; padding: 13px 16px; display: flex; align-items: center; gap: 14px; }
        .mh .eb { font-size: 7px; font-weight: 800; letter-spacing: 2.4px; text-transform: uppercase; color: {{ $gold }}; }
        .mh h1 { margin: 3px 0 0; font-size: 18px; font-weight: 800; line-height: 1; }
        .mh .sub { margin-top: 4px; font-size: 8.5px; color: rgba(255,255,255,0.6); }
        .mh .fig { margin-left: auto; text-align: right; flex: none; }
        .mh .fig b { display: block; color: {{ $gold }}; font-size: 24px; font-weight: 800; line-height: 1; }
        .mh .fig span { display: block; margin-top: 3px; font-size: 6.5px; font-weight: 700;
                        letter-spacing: 1.3px; text-transform: uppercase; color: rgba(255,255,255,0.5); }

        /* ── the tally strip ── */
        .tally { display: flex; gap: 8px; margin-top: 10px; }
        .tally div { flex: 1; border: 1px solid #E4E9F1; border-radius: 7px; padding: 8px 10px; }
        .tally b { display: block; font-size: 17px; font-weight: 800; line-height: 1.1; font-variant-numeric: tabular-nums; }
        .tally span { display: block; margin-top: 2px; font-size: 6.5px; font-weight: 800;
                      letter-spacing: 1.2px; text-transform: uppercase; color: #94A3B8; }
        .bar { height: 6px; border-radius: 4px; background: #EDF1F7; overflow: hidden; margin-top: 10px; }
        .bar > i { display: block; height: 6px; background: {{ $gold }}; }

        /* ── the list ── */
        .grp { margin-top: 13px; border: 1px solid #E4E9F1; border-radius: 7px; overflow: hidden;
               break-inside: avoid; page-break-inside: avoid; }
        .grp > h2 { margin: 0; padding: 7px 12px; font-size: 7.5px; font-weight: 800;
                    letter-spacing: 2px; text-transform: uppercase; display: flex; align-items: center; }
        .grp > h2 em { margin-left: auto; font-style: normal; font-size: 7px; letter-spacing: 0.6px;
                       text-transform: none; opacity: 0.72; }
        table { width: 100%; border-collapse: collapse; }
        th { font-size: 6.5px; font-weight: 800; letter-spacing: 1.2px; text-transform: uppercase; color: #94A3B8;
             text-align: left; padding: 6px 12px; background: #F8FAFC; border-bottom: 1px solid #E9EDF4; }
        td { font-size: 10.5px; padding: 8px 12px; border-bottom: 1px solid #F1F5F9; vertical-align: middle; }
        tr:last-child td { border-bottom: 0; }
        .chk { width: 26px; }
        .box { display: block; width: 13px; height: 13px; border: 1.4px solid #94A3B8; border-radius: 3px; }
        .item { font-weight: 700; color: {{ $navy }}; }
        .qty { width: 52px; text-align: right; font-weight: 800; color: {{ $navy }};
               font-variant-numeric: tabular-nums; white-space: nowrap; }
        .when { width: 108px; font-size: 9px; color: #64748B; white-space: nowrap; }
        .when b { color: {{ $navy }}; }
        .note { font-size: 9px; color: #64748B; }
        .short { display: inline-block; border-radius: 99px; background: #FEF3C7; color: #92400E;
                 padding: 1px 6px; font-size: 7px; font-weight: 800; letter-spacing: 0.3px; }

        .empty { margin-top: 13px; border: 1px dashed #CBD5E1; border-radius: 8px; padding: 34px 20px;
                 text-align: center; color: #94A3B8; font-size: 11px; line-height: 1.6; }

        /* ── sign-off ── */
        .off { margin-top: 13px; border: 1px solid #E4E9F1; border-radius: 7px; overflow: hidden;
               break-inside: avoid; page-break-inside: avoid; }
        .off > h2 { margin: 0; background: {{ $navy }}; color: {{ $gold }}; padding: 7px 12px;
                    font-size: 7.5px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; }
        .off .row { display: flex; }
        .off .row > div { flex: 1; padding: 12px 12px 9px; border-right: 1px solid #EEF2F8; }
        .off .row > div:last-child { border-right: 0; }
        .off .line { height: 26px; border-bottom: 1px solid #C8D2E0; }
        .off .k { margin-top: 5px; font-size: 6.5px; font-weight: 800; letter-spacing: 1.4px;
                  text-transform: uppercase; color: #94A3B8; }

        .foot { margin-top: 12px; padding-top: 8px; border-top: 1px solid #E4E9F1;
                display: flex; font-size: 8px; color: #94A3B8; }
    </style>
</head>
<body>

<div class="mh">
    <div style="min-width:0;">
        <div class="eb">Elite Business Hub · Load-in Sheet</div>
        <h1>{{ $room->name }}</h1>
        <div class="sub">{{ $event->name }} · {{ str($room->type)->replace('_', ' ')->title() }}
            @if ($event->starts_on) · {{ $event->starts_on->format('d M Y') }}@endif
            · room held {{ $days }} {{ str('day')->plural($days) }}</div>
    </div>
    <div class="fig">
        <b>{{ $readiness }}%</b>
        <span>Confirmed or on-site</span>
    </div>
</div>

<div class="tally">
    @foreach ($statusMeta as $key => [$label, $fg, $bg])
        <div style="background:{{ $bg }};">
            <b style="color:{{ $fg }};">{{ $counts[$key] ?? 0 }}</b>
            <span style="color:{{ $fg }}; opacity:0.7;">{{ $label }}</span>
        </div>
    @endforeach
</div>
<div class="bar"><i style="width: {{ $readiness }}%"></i></div>

@if ($lines->isEmpty())
    <div class="empty">
        Nothing listed for this venue yet.<br>
        Add equipment in the venue's <b>Equipment</b> tab and it appears here, on the floor plan schedule, and in the budget.
    </div>
@else
    @foreach ($byStatus as $status => $group)
        @php [$label, $fg, $bg] = $statusMeta[$status]; @endphp
        <div class="grp">
            <h2 style="background:{{ $bg }}; color:{{ $fg }};">
                {{ $label }}
                <em>{{ $group->sum('qty') }} {{ str('unit')->plural($group->sum('qty')) }} · {{ $group->count() }} {{ str('line')->plural($group->count()) }}</em>
            </h2>
            <table>
                <thead>
                    <tr>
                        <th class="chk"></th>
                        <th>Item</th>
                        <th class="qty" style="text-align:right;">Qty</th>
                        <th class="when">Needed</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group as $line)
                        <tr>
                            <td class="chk"><span class="box"></span></td>
                            <td class="item">{{ $line['name'] }}</td>
                            <td class="qty">{{ $line['qty'] }}</td>
                            <td class="when">
                                <b>{{ $line['days'] }}</b> {{ str('day')->plural($line['days']) }}
                                {{-- The short runs are what get forgotten on a de-rig. --}}
                                @if ($line['days'] < $days)
                                    <span class="short">of {{ $days }}</span>
                                @elseif ($line['days'] > $days)
                                    <span class="short" style="background:#FEE2E2; color:#991B1B;">room held {{ $days }}</span>
                                @endif
                            </td>
                            <td class="note">{{ $line['notes'] ?: '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
@endif

<div class="off">
    <h2>Checked in on site</h2>
    <div class="row">
        @foreach (['Checked by', 'Signature', 'Date &amp; time'] as $field)
            <div>
                <div class="line"></div>
                <div class="k">{!! $field !!}</div>
            </div>
        @endforeach
    </div>
</div>

<div class="foot">
    <span>{{ $event->name }} · {{ $room->name }} · load-in sheet</span>
    <span style="margin-left:auto;">Issued {{ now()->format('d M Y') }} · prices on the floor plan schedule</span>
</div>

</body>
</html>
