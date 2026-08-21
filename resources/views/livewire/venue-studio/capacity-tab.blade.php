<div class="eo-soft-card p-5">
    <p class="eo-label">Capacity Intelligence</p>
    <p class="mt-1 text-[12px] text-eo-muted">Utilization over the {{ $windowDays }} days either side of today, off real bookings — not a forecast.</p>

    @if ($rows->isEmpty())
        <x-eo.empty-state title="No spaces to measure" icon="chart" class="mt-4"
            hint="Add spaces in Space Explorer to see utilization here." />
    @else
        <div class="mt-4 space-y-2.5">
            @foreach ($rows as $row)
                <div class="eo-soft-card p-3.5">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-[12.5px] font-bold text-eo-text">{{ $row['space']->name }}</p>
                            <p class="text-[10.5px] text-eo-muted">{{ $row['booked_days'] }} of {{ $windowDays }} days booked</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @if ($row['flag'])
                                <span class="hubx-pill" style="background: {{ $row['flag']['tone'] === 'risk' ? 'var(--color-eo-risk-soft)' : 'var(--color-eo-warn-soft)' }}; color: {{ $row['flag']['tone'] === 'risk' ? '#b91c1c' : '#92400e' }};">
                                    {{ $row['flag']['label'] }}
                                </span>
                            @endif
                            <span class="text-[15px] font-bold tabular-nums text-eo-text">{{ $row['pct'] }}%</span>
                        </div>
                    </div>
                    <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-eo-bg">
                        <div class="h-full rounded-full" style="width: {{ $row['pct'] }}%; background: {{ $row['pct'] >= 85 ? 'var(--color-eo-risk)' : 'var(--color-eo-teal-ink)' }};"></div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
