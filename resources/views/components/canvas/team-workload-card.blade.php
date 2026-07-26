@props(['teams' => []])
<section class="rounded-[22px] border border-cc-line bg-white p-5 cc-lift-2">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="text-[12px] font-extrabold uppercase tracking-[0.13em] text-cc-navy">Team Workload</h2>
            <p class="mt-0.5 text-[11px] text-cc-ink-3">Department Overview</p>
        </div>
        <a href="#" class="shrink-0 text-[11px] font-bold text-cc-blue transition hover:text-cc-navy">View All →</a>
    </div>

    <ul class="mt-4 space-y-3">
        @foreach ($teams as $t)
            @php $bar = $t['pct'] >= 90 ? 'bg-cc-risk' : ($t['pct'] >= 80 ? 'bg-cc-warn' : 'bg-cc-blue'); @endphp
            <li class="grid grid-cols-[minmax(0,88px)_1fr_auto] items-center gap-3">
                <span class="flex items-center gap-1.5 truncate text-[11.5px] text-cc-ink-2">
                    <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $bar }}"></span>{{ $t['team'] }}
                </span>
                <span class="h-1.5 overflow-hidden rounded-full bg-cc-line">
                    <span class="block h-full rounded-full {{ $bar }} transition-all duration-700" style="width:{{ $t['pct'] }}%"></span>
                </span>
                <span class="text-[11.5px] font-bold tabular-nums text-cc-navy">{{ $t['pct'] }}%</span>
            </li>
        @endforeach
    </ul>
</section>
