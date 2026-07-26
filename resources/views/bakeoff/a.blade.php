<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>A · Broadcast</title>
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=archivo:400,500,600,700,800,900|archivo-narrow:600,700&display=swap" rel="stylesheet">
<style>
/* ═══ A · BROADCAST ═══════════════════════════════════════════════════
   The pit wall. One screen you could put on the office wall and read from
   across the room. Enormous figures, a single hot accent, deep space behind
   everything. Built to be looked at, not just used.                      */
*{box-sizing:border-box;margin:0}
:root{
  --bg:#07070A; --panel:#0E0F14; --line:#1C1E27;
  --ink:#F4F4F6; --dim:#8A8D9C; --faint:#4B4E5C;
  --hot:#FF4D2E;        /* one accent, used like a warning light */
  --cool:#4DA8FF; --good:#2FD98C; --warn:#FFB020;
}
body{background:var(--bg);color:var(--ink);font:400 15px/1.5 Archivo,system-ui,sans-serif;
  -webkit-font-smoothing:antialiased;overflow-x:hidden}
.grain{position:fixed;inset:0;pointer-events:none;z-index:1;opacity:.5;
  background:radial-gradient(120% 80% at 50% -10%,rgba(255,77,46,.10),transparent 60%),
             radial-gradient(80% 60% at 90% 110%,rgba(77,168,255,.07),transparent 60%)}
.wrap{position:relative;z-index:2;max-width:1500px;margin:0 auto;padding:34px 40px 90px}

.top{display:flex;align-items:flex-end;justify-content:space-between;gap:30px;flex-wrap:wrap}
.kicker{font:700 11px/1 'Archivo Narrow',sans-serif;letter-spacing:.42em;text-transform:uppercase;color:var(--hot)}
h1{font:900 clamp(38px,5.2vw,74px)/0.94 Archivo,sans-serif;letter-spacing:-.035em;margin-top:14px;max-width:15ch}
.where{margin-top:14px;color:var(--dim);font-size:15px;letter-spacing:.01em}
.count{text-align:right;flex:none}
.count b{display:block;font:900 clamp(64px,9vw,132px)/0.82 Archivo,sans-serif;letter-spacing:-.055em;
  background:linear-gradient(180deg,#fff 30%,#6E7180);-webkit-background-clip:text;background-clip:text;color:transparent}
.count span{display:block;font:700 11px/1 'Archivo Narrow',sans-serif;letter-spacing:.36em;text-transform:uppercase;color:var(--faint);margin-top:12px}

.status{margin-top:40px;display:flex;align-items:center;gap:16px;padding:20px 26px;border-radius:4px;
  background:linear-gradient(90deg,rgba(255,77,46,.14),rgba(255,77,46,0));border-left:3px solid var(--hot)}
.status .dot{width:9px;height:9px;border-radius:99px;background:var(--hot);flex:none;
  box-shadow:0 0 0 0 rgba(255,77,46,.6);animation:ping 2.4s cubic-bezier(.22,1,.36,1) infinite}
@keyframes ping{70%{box-shadow:0 0 0 16px rgba(255,77,46,0)}100%{box-shadow:0 0 0 0 rgba(255,77,46,0)}}
.status p{font-size:17px;font-weight:600;letter-spacing:-.01em}
.status em{font-style:normal;color:var(--hot)}

.rail{margin-top:44px;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
  border-top:1px solid var(--line);border-bottom:1px solid var(--line)}
.cell{padding:26px 26px 24px;border-right:1px solid var(--line);position:relative}
.cell:last-child{border-right:0}
.cell .k{font:700 10px/1 'Archivo Narrow',sans-serif;letter-spacing:.3em;text-transform:uppercase;color:var(--faint)}
.cell .v{margin-top:16px;font:800 44px/0.9 Archivo,sans-serif;letter-spacing:-.04em;font-variant-numeric:tabular-nums}
.cell .s{margin-top:12px;font-size:12.5px;color:var(--dim)}
.cell.hot .v{color:var(--hot)} .cell.good .v{color:var(--good)} .cell.warn .v{color:var(--warn)}

.grid{margin-top:44px;display:grid;grid-template-columns:1.35fr 1fr;gap:20px}
@media(max-width:1000px){.grid{grid-template-columns:1fr}}
.panel{background:var(--panel);border:1px solid var(--line);border-radius:6px;overflow:hidden}
.ph{display:flex;align-items:baseline;justify-content:space-between;padding:22px 26px;border-bottom:1px solid var(--line)}
.ph h2{font:700 13px/1 'Archivo Narrow',sans-serif;letter-spacing:.26em;text-transform:uppercase}
.ph span{font-size:12px;color:var(--faint)}

.row{display:flex;align-items:center;gap:18px;padding:18px 26px;border-bottom:1px solid var(--line);transition:background .18s}
.row:last-child{border-bottom:0}
.row:hover{background:#12131A}
.row .n{font:800 15px/1.2 Archivo,sans-serif;letter-spacing:-.01em;flex:1;min-width:0}
.row .m{font-size:12.5px;color:var(--dim);margin-top:4px}
.row .t{font:700 11px/1 'Archivo Narrow',sans-serif;letter-spacing:.16em;text-transform:uppercase;
  padding:6px 10px;border-radius:3px;flex:none}
.t.late{background:rgba(255,77,46,.16);color:var(--hot)}
.t.soon{background:rgba(255,176,32,.14);color:var(--warn)}
.t.ok{background:rgba(47,217,140,.13);color:var(--good)}

.bar{height:3px;background:#191B23;display:flex;margin-top:0}
.bar i{display:block;height:100%}
.money{padding:26px}
.money .big{font:800 52px/0.9 Archivo,sans-serif;letter-spacing:-.045em;font-variant-numeric:tabular-nums}
.money .sub{margin-top:10px;color:var(--dim);font-size:13px}
.legend{margin-top:22px;display:grid;gap:12px}
.legend div{display:flex;align-items:center;gap:11px;font-size:13.5px;color:var(--dim)}
.legend i{width:10px;height:10px;border-radius:2px;flex:none}
.legend b{margin-left:auto;color:var(--ink);font-variant-numeric:tabular-nums;font-weight:700}
</style>
</head>
<body>
<div class="grain"></div>
<div class="wrap">

  <div class="top">
    <div>
      <div class="kicker">Live Operations</div>
      <h1>{{ $event->name }}</h1>
      <p class="where">{{ $event->starts_at->format('j F') }} – {{ ($event->ends_at ?? $event->starts_at)->format('j F Y') }} · {{ $event->venue?->name ?? $event->city }}</p>
    </div>
    <div class="count">
      <b>{{ $daysOut }}</b>
      <span>Days to go</span>
    </div>
  </div>

  <div class="status">
    <span class="dot"></span>
    <p>@if ($overdue)<em>{{ $overdue }} tasks are overdue</em> and {{ $supplierIssues }} suppliers need chasing.@else Everything is on plan.@endif</p>
  </div>

  <div class="rail">
    <div class="cell"><div class="k">Participants</div><div class="v">{{ number_format($attendees) }}</div><div class="s">{{ $confirmed }} confirmed · {{ $waitlist }} waitlisted</div></div>
    <div class="cell {{ $spentPct > 75 ? 'warn' : '' }}"><div class="k">Budget burnt</div><div class="v">{{ $spentPct }}%</div><div class="s">JD {{ number_format($spent / 100) }} of {{ number_format($budget / 100) }}</div></div>
    <div class="cell {{ $overdue ? 'hot' : 'good' }}"><div class="k">Open tasks</div><div class="v">{{ $tasksOpen }}</div><div class="s">{{ $overdue }} overdue</div></div>
    <div class="cell {{ $supplierIssues ? 'hot' : '' }}"><div class="k">Suppliers</div><div class="v">{{ $suppliers }}</div><div class="s">{{ $supplierIssues }} with issues</div></div>
    <div class="cell good"><div class="k">Sponsorship</div><div class="v">{{ number_format($sponsorship / 100000, 0) }}K</div><div class="s">JD {{ number_format($sponsorsPaid / 100) }} received</div></div>
  </div>

  <div class="grid">
    <div class="panel">
      <div class="ph"><h2>Critical path</h2><span>{{ $tasksOpen }} open</span></div>
      @foreach ($topTasks as $t)
        @php $late = $t->due_on?->isPast(); $soon = ! $late && $t->due_on?->lte(now()->addDays(7)); @endphp
        <div class="row">
          <div style="flex:1;min-width:0">
            <div class="n">{{ $t->title }}</div>
            <div class="m">{{ $t->assignee?->name ?? 'Unassigned' }} · {{ $t->due_on?->format('j M Y') ?? 'no date' }}</div>
          </div>
          <span class="t {{ $late ? 'late' : ($soon ? 'soon' : 'ok') }}">{{ $late ? $t->due_on->diffInDays(now()).'d late' : ($soon ? 'this week' : 'on track') }}</span>
        </div>
      @endforeach
      @foreach ($issues as $s)
        <div class="row">
          <div style="flex:1;min-width:0">
            <div class="n">{{ $s->name }}</div>
            <div class="m">{{ str($s->category)->replace('_', ' ')->title() }} · flagged</div>
          </div>
          <span class="t late">Supplier</span>
        </div>
      @endforeach
    </div>

    <div class="panel">
      <div class="ph"><h2>Money</h2><span>JD</span></div>
      <div class="bar">
        <i style="width:{{ $spentPct }}%;background:var(--hot)"></i>
        <i style="width:{{ 100 - $spentPct }}%;background:#252833"></i>
      </div>
      <div class="money">
        <div class="big">{{ number_format($spent / 100) }}</div>
        <div class="sub">spent of {{ number_format($budget / 100) }} committed</div>
        <div class="legend">
          <div><i style="background:var(--hot)"></i>Spent<b>{{ number_format($spent / 100) }}</b></div>
          <div><i style="background:#252833"></i>Remaining<b>{{ number_format(max(0, $budget - $spent) / 100) }}</b></div>
          <div><i style="background:var(--good)"></i>Sponsorship secured<b>{{ number_format($sponsorship / 100) }}</b></div>
          <div><i style="background:var(--cool)"></i>Speakers confirmed<b>{{ $speakersConfirmed }} / {{ $speakers }}</b></div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
