<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>B · Dossier</title>
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=fraunces:400,500,600,700,800,900|inter-tight:400,500,600,700&display=swap" rel="stylesheet">
<style>
/* ═══ B · DOSSIER ═════════════════════════════════════════════════════
   The morning briefing, typeset. Reads like something printed for a board
   meeting: real margins, a rule you can hang information on, figures set as
   editorial numerals. Calm and expensive rather than loud.               */
*{box-sizing:border-box;margin:0}
:root{
  --paper:#FAF7F2; --card:#FFFFFF; --rule:#E4DED2; --rule-2:#CFC6B4;
  --ink:#1A1714; --ink-2:#5A5248; --ink-3:#8C8377;
  --seal:#7A2E24;      /* oxblood — the only strong colour */
  --good:#3F6B4A; --warn:#96682A;
}
body{background:var(--paper);color:var(--ink);font:400 16px/1.65 'Inter Tight',system-ui,sans-serif;
  -webkit-font-smoothing:antialiased}
.sheet{max-width:1180px;margin:0 auto;padding:64px 56px 110px}
@media(max-width:760px){.sheet{padding:40px 24px 80px}}

.masthead{border-bottom:2px solid var(--ink);padding-bottom:26px;display:flex;
  align-items:flex-end;justify-content:space-between;gap:36px;flex-wrap:wrap}
.brandline{font:600 11px/1 'Inter Tight',sans-serif;letter-spacing:.34em;text-transform:uppercase;color:var(--seal)}
h1{font:700 clamp(34px,4.4vw,58px)/1.02 Fraunces,Georgia,serif;letter-spacing:-.02em;margin-top:18px;max-width:18ch}
.sub{margin-top:16px;color:var(--ink-2);font-size:16.5px;max-width:52ch}
.issued{text-align:right;flex:none}
.issued .lbl{font:600 10px/1 'Inter Tight',sans-serif;letter-spacing:.26em;text-transform:uppercase;color:var(--ink-3)}
.issued .d{font:600 26px/1.1 Fraunces,Georgia,serif;margin-top:10px;font-variant-numeric:tabular-nums}

.lede{margin-top:38px;padding-left:22px;border-left:3px solid var(--seal);
  font:400 21px/1.5 Fraunces,Georgia,serif;max-width:62ch;letter-spacing:-.008em}
.lede b{font-weight:700;color:var(--seal)}

.figures{margin-top:52px;display:grid;grid-template-columns:repeat(auto-fit,minmax(158px,1fr));gap:0;
  border-top:1px solid var(--rule-2);border-bottom:1px solid var(--rule-2)}
.fig{padding:26px 24px;border-right:1px solid var(--rule)}
.fig:last-child{border-right:0}
.fig .n{font:600 46px/1 Fraunces,Georgia,serif;letter-spacing:-.03em;font-variant-numeric:tabular-nums}
.fig .l{margin-top:12px;font:600 10.5px/1.3 'Inter Tight',sans-serif;letter-spacing:.2em;text-transform:uppercase;color:var(--ink-3)}
.fig .f{margin-top:8px;font-size:13.5px;color:var(--ink-2)}
.fig.alert .n{color:var(--seal)}

section{margin-top:56px}
h2{font:600 12px/1 'Inter Tight',sans-serif;letter-spacing:.3em;text-transform:uppercase;color:var(--ink-3);
  padding-bottom:12px;border-bottom:1px solid var(--rule-2)}

.entry{display:grid;grid-template-columns:1fr auto;gap:20px;align-items:baseline;
  padding:20px 0;border-bottom:1px solid var(--rule)}
.entry:last-child{border-bottom:0}
.entry .t{font:600 19px/1.35 Fraunces,Georgia,serif;letter-spacing:-.01em}
.entry .m{margin-top:6px;color:var(--ink-2);font-size:14.5px}
.entry .r{font:600 13px/1 'Inter Tight',sans-serif;white-space:nowrap;font-variant-numeric:tabular-nums}
.r.late{color:var(--seal)} .r.soon{color:var(--warn)} .r.ok{color:var(--ink-3)}

.two{display:grid;grid-template-columns:1fr 1fr;gap:48px}
@media(max-width:860px){.two{grid-template-columns:1fr;gap:40px}}

.ledger{width:100%;border-collapse:collapse;margin-top:6px}
.ledger td{padding:14px 0;border-bottom:1px solid var(--rule);font-size:15px}
.ledger tr:last-child td{border-bottom:0}
.ledger td:last-child{text-align:right;font-variant-numeric:tabular-nums;font-weight:600}
.ledger .tot td{border-top:2px solid var(--ink);border-bottom:0;padding-top:16px;font-weight:700;font-size:16.5px}

.meter{height:8px;background:var(--rule);display:flex;margin:22px 0 14px;border-radius:1px;overflow:hidden}
.meter i{display:block;height:100%}
.note{font-size:14px;color:var(--ink-2)}

.sig{margin-top:64px;padding-top:24px;border-top:1px solid var(--rule-2);display:flex;
  justify-content:space-between;gap:24px;flex-wrap:wrap;font-size:13.5px;color:var(--ink-3)}
</style>
</head>
<body>
<div class="sheet">

  <div class="masthead">
    <div>
      <div class="brandline">Event Dossier</div>
      <h1>{{ $event->name }}</h1>
      <p class="sub">{{ $event->starts_at->format('j F') }} – {{ ($event->ends_at ?? $event->starts_at)->format('j F Y') }} · {{ $event->venue?->name ?? $event->city }}@if ($event->client) · prepared for {{ $event->client->name }}@endif</p>
    </div>
    <div class="issued">
      <div class="lbl">Issued</div>
      <div class="d">{{ now()->format('j M Y') }}</div>
    </div>
  </div>

  <p class="lede">
    @if ($overdue)
      <b>{{ $overdue }} tasks have passed their date</b> and {{ $supplierIssues }} suppliers are flagged, with {{ $daysOut }} days remaining. Everything else is proceeding to plan.
    @else
      All {{ $tasksOpen }} open items are within their dates, with {{ $daysOut }} days remaining.
    @endif
  </p>

  <div class="figures">
    <div class="fig"><div class="n">{{ number_format($attendees) }}</div><div class="l">Participants</div><div class="f">{{ $confirmed }} confirmed</div></div>
    <div class="fig"><div class="n">{{ $daysOut }}</div><div class="l">Days remaining</div><div class="f">{{ $event->starts_at->format('j M') }}</div></div>
    <div class="fig {{ $overdue ? 'alert' : '' }}"><div class="n">{{ $tasksOpen }}</div><div class="l">Open items</div><div class="f">{{ $overdue }} overdue</div></div>
    <div class="fig"><div class="n">{{ $spentPct }}%</div><div class="l">Budget drawn</div><div class="f">of JD {{ number_format($budget / 100) }}</div></div>
    <div class="fig"><div class="n">{{ $speakers }}</div><div class="l">Speakers</div><div class="f">{{ $speakersConfirmed }} confirmed</div></div>
  </div>

  <section>
    <h2>Matters requiring attention</h2>
    @foreach ($topTasks as $t)
      @php $late = $t->due_on?->isPast(); $soon = ! $late && $t->due_on?->lte(now()->addDays(7)); @endphp
      <div class="entry">
        <div>
          <div class="t">{{ $t->title }}</div>
          <div class="m">{{ $t->assignee?->name ?? 'Unassigned' }} — {{ \App\Models\Event::moduleLabel($t->area) }}</div>
        </div>
        <div class="r {{ $late ? 'late' : ($soon ? 'soon' : 'ok') }}">
          {{ $late ? 'Overdue '.$t->due_on->diffInDays(now()).'d' : ($t->due_on?->format('j M') ?? '—') }}
        </div>
      </div>
    @endforeach
    @foreach ($issues as $s)
      <div class="entry">
        <div>
          <div class="t">{{ $s->name }}</div>
          <div class="m">{{ str($s->category)->replace('_', ' ')->title() }} — supplier flagged for review</div>
        </div>
        <div class="r late">Escalated</div>
      </div>
    @endforeach
  </section>

  <div class="two">
    <section>
      <h2>Financial position</h2>
      <div class="meter">
        <i style="width:{{ $spentPct }}%;background:var(--seal)"></i>
        <i style="width:{{ 100 - $spentPct }}%;background:var(--rule-2)"></i>
      </div>
      <p class="note">JD {{ number_format($spent / 100) }} drawn against a committed budget of JD {{ number_format($budget / 100) }}.</p>
      <table class="ledger">
        <tr><td>Sponsorship committed</td><td>{{ number_format($sponsorship / 100) }}</td></tr>
        <tr><td>Sponsorship received</td><td>{{ number_format($sponsorsPaid / 100) }}</td></tr>
        <tr><td>Expenditure to date</td><td>({{ number_format($spent / 100) }})</td></tr>
        <tr class="tot"><td>Net position</td><td>{{ number_format(($sponsorsPaid - $spent) / 100) }}</td></tr>
      </table>
    </section>

    <section>
      <h2>Programme &amp; partners</h2>
      <table class="ledger">
        <tr><td>Sessions scheduled</td><td>{{ $sessions }}</td></tr>
        <tr><td>Speakers confirmed</td><td>{{ $speakersConfirmed }} of {{ $speakers }}</td></tr>
        <tr><td>Suppliers engaged</td><td>{{ $suppliers }}</td></tr>
        <tr><td>Suppliers flagged</td><td>{{ $supplierIssues }}</td></tr>
        <tr><td>Participants confirmed</td><td>{{ $confirmed }}</td></tr>
        <tr><td>Awaiting confirmation</td><td>{{ $pending }}</td></tr>
        <tr class="tot"><td>Total registered</td><td>{{ number_format($attendees) }}</td></tr>
      </table>
    </section>
  </div>

  <div class="sig">
    <span>Prepared by {{ $event->projectManager?->name ?? 'Elite Event Hub' }}</span>
    <span>{{ $event->name }} · {{ now()->format('j M Y, H:i') }}</span>
  </div>
</div>
</body>
</html>
